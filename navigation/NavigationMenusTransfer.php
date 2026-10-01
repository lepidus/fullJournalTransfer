<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\navigation;

use APP\core\Services;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\navigationMenu\NavigationMenuItem;

class NavigationMenusTransfer
{
    private const NS = 'http://pkp.sfu.ca';

    public function export(DOMDocument $document, int $contextId, array $locales): DOMElement
    {
        $root = $document->createElementNS(self::NS, 'navigation_menus');
        $items = $document->createElementNS(self::NS, 'items');
        $root->appendChild($items);
        foreach (DAORegistry::getDAO('NavigationMenuItemDAO')->getByContextId($contextId)->toArray() as $item) {
            $node = $document->createElementNS(self::NS, 'item');
            $node->setAttribute('source_ref', (string) $item->getId());
            $node->setAttribute('type', (string) $item->getType());
            $node->setAttribute('path', (string) $item->getPath());
            $node->setAttribute('title_locale_key', (string) $item->getTitleLocaleKey());
            $this->exportFields($document, $node, $item, ['title', 'content', 'remoteUrl'], $locales);
            $items->appendChild($node);
        }
        foreach (DAORegistry::getDAO('NavigationMenuDAO')->getByContextId($contextId)->toArray() as $menu) {
            $node = $document->createElementNS(self::NS, 'menu');
            $node->setAttribute('title', (string) $menu->getTitle());
            $node->setAttribute('area', (string) $menu->getAreaName());
            foreach (DAORegistry::getDAO('NavigationMenuItemAssignmentDAO')->getByMenuId($menu->getId())->toArray() as $link) {
                $assignment = $document->createElementNS(self::NS, 'assignment');
                $assignment->setAttribute('item_ref', (string) $link->getMenuItemId());
                $assignment->setAttribute('parent_ref', (string) ((int) $link->getParentId()));
                $assignment->setAttribute('seq', (string) $link->getSequence());
                $this->exportFields($document, $assignment, $link, ['title'], $locales);
                $node->appendChild($assignment);
            }
            $root->appendChild($node);
        }
        $this->validate($root);
        return $root;
    }

    public function import(DOMElement $root, int $contextId, array $locales): void
    {
        $this->validate($root);
        DB::transaction(function () use ($root, $contextId, $locales): void {
            $itemDao = DAORegistry::getDAO('NavigationMenuItemDAO');
            $menuDao = DAORegistry::getDAO('NavigationMenuDAO');
            $assignmentDao = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
            // The caller imports into a newly created context; replace its default navigation only.
            $menuDao->deleteByContextId($contextId);
            $itemDao->deleteByContextId($contextId);
            $ids = [];
            foreach ($this->children($this->children($root, 'items')[0], 'item') as $node) {
                $item = $itemDao->newDataObject();
                $item->setContextId($contextId);
                $item->setType($node->getAttribute('type'));
                $item->setPath($node->getAttribute('path'));
                $item->setTitleLocaleKey($node->getAttribute('title_locale_key'));
                $this->importFields($node, $item, $locales);
                $ids[$node->getAttribute('source_ref')] = $itemDao->insertObject($item);
            }
            foreach ($this->children($root, 'menu') as $node) {
                $menu = $menuDao->newDataObject();
                $menu->setContextId($contextId);
                $menu->setTitle($node->getAttribute('title'));
                $menu->setAreaName($node->getAttribute('area'));
                $menuDao->insertObject($menu);
                foreach ($this->children($node, 'assignment') as $link) {
                    $assignment = $assignmentDao->newDataObject();
                    $assignment->setMenuId($menu->getId());
                    $assignment->setMenuItemId($ids[$link->getAttribute('item_ref')]);
                    $assignment->setParentId($ids[$link->getAttribute('parent_ref')] ?? 0);
                    $assignment->setSequence((int) $link->getAttribute('seq'));
                    $assignmentDao->insertObject($assignment);
                    // insertObject sets the item's default title; restore per-menu overrides afterwards.
                    $this->importFields($link, $assignment, $locales);
                    $assignmentDao->updateLocaleFields($assignment);
                }
            }
        });
    }

    private function validate(DOMElement $root): void
    {
        $containers = $this->children($root, 'items');
        if (count($containers) !== 1) {
            $this->invalid();
        }
        $types = Services::get('navigationMenu')->getMenuItemTypes();
        $items = [];
        $paths = [];
        foreach ($this->children($containers[0], 'item') as $node) {
            $id = $node->getAttribute('source_ref');
            $path = $node->getAttribute('path');
            if (!ctype_digit($id) || (int) $id < 1 || isset($items[$id])
                || !isset($types[$node->getAttribute('type')])
                || ($path !== '' && isset($paths[$path]))
                || ($node->getAttribute('type') === NavigationMenuItem::NMI_TYPE_CUSTOM
                    && !preg_match('/^[a-zA-Z0-9\/._-]+$/', $path))) {
                $this->invalid();
            }
            $items[$id] = true;
            if ($path !== '') {
                $paths[$path] = true;
            }
            $this->validateFields($node, ['title', 'content', 'remoteUrl']);
        }
        $areas = [];
        $titles = [];
        foreach ($this->children($root, 'menu') as $menu) {
            $area = $menu->getAttribute('area');
            $title = $menu->getAttribute('title');
            if ($title === '' || isset($titles[$title]) || ($area !== '' && isset($areas[$area]))) {
                $this->invalid();
            }
            $titles[$title] = true;
            if ($area !== '') {
                $areas[$area] = true;
            }
            $parents = [];
            foreach ($this->children($menu, 'assignment') as $link) {
                $id = $link->getAttribute('item_ref');
                $parent = $link->getAttribute('parent_ref');
                $seq = $link->getAttribute('seq');
                if (!isset($items[$id]) || isset($parents[$id]) || !ctype_digit($parent)
                    || !ctype_digit($seq)) {
                    $this->invalid();
                }
                $parents[$id] = $parent;
                $this->validateFields($link, ['title']);
            }
            foreach ($parents as $id => $parent) {
                $seen = [$id => true];
                while ((int) $parent !== 0) {
                    if (!isset($parents[$parent]) || isset($seen[$parent])) {
                        $this->invalid();
                    }
                    $seen[$parent] = true;
                    $parent = $parents[$parent];
                }
            }
        }
    }

    private function validateFields(DOMElement $node, array $allowed): void
    {
        $seen = [];
        foreach ($this->children($node, 'field') as $field) {
            $name = $field->getAttribute('name');
            $locale = $field->getAttribute('locale');
            $key = $name . ':' . $locale;
            if (!in_array($name, $allowed, true) || !preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $locale)
                || isset($seen[$key])
                || ($name === 'remoteUrl' && $field->textContent !== ''
                    && !filter_var($field->textContent, FILTER_VALIDATE_URL))) {
                $this->invalid();
            }
            $seen[$key] = true;
        }
    }

    private function exportFields(
        DOMDocument $document,
        DOMElement $node,
        $object,
        array $names,
        array $locales
    ): void {
        foreach ($names as $name) {
            foreach (($object->getData($name, null) ?? []) as $locale => $value) {
                if (!in_array($locale, $locales, true) || $value === null) {
                    continue;
                }
                $field = $document->createElementNS(self::NS, 'field');
                $field->setAttribute('name', $name);
                $field->setAttribute('locale', $locale);
                $field->appendChild($document->createTextNode($value));
                $node->appendChild($field);
            }
        }
    }

    private function importFields(DOMElement $node, $object, array $locales): void
    {
        foreach ($this->children($node, 'field') as $field) {
            $locale = $field->getAttribute('locale');
            if (in_array($locale, $locales, true)) {
                $object->setData($field->getAttribute('name'), $field->textContent, $locale);
            }
        }
    }

    private function children(DOMElement $node, string $name): array
    {
        $children = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::NS && $child->localName === $name) {
                $children[] = $child;
            }
        }
        return $children;
    }

    private function invalid(): void
    {
        throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.invalidNavigationMenus'));
    }
}
