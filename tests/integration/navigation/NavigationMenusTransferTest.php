<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\integration\navigation;

use APP\core\Application;
use APP\core\Services;
use APP\file\PublicFileManager;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use APP\plugins\importexport\fullJournalTransfer\navigation\NavigationMenusTransfer;
use APP\plugins\importexport\fullJournalTransfer\tests\support\LoadsPluginLocale;
use DOMDocument;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\navigationMenu\NavigationMenuItem;
use PKP\tests\DatabaseTestCase;

class NavigationMenusTransferTest extends DatabaseTestCase
{
    use LoadsPluginLocale;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function testItTransfersOnlyItemsUsedInMenusWithAnArea(): void
    {
        $contextDao = Application::get()->getContextDAO();
        $source = $contextDao->newDataObject();
        $source->setPath('navigation-source-' . bin2hex(random_bytes(4)));
        $source->setPrimaryLocale('en');
        $source->setSequence(1);
        $contextDao->insertObject($source);
        $target = $contextDao->newDataObject();
        $target->setPath('navigation-target-' . bin2hex(random_bytes(4)));
        $target->setPrimaryLocale('en');
        $target->setSequence(1);
        $contextDao->insertObject($target);
        $items = DAORegistry::getDAO('NavigationMenuItemDAO');
        $menus = DAORegistry::getDAO('NavigationMenuDAO');
        $assignments = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
        $ids = [];
        foreach (['parent', 'child', 'unused', 'unassigned'] as $path) {
            $item = $items->newDataObject();
            $item->setContextId($source->getId());
            $item->setType(NavigationMenuItem::NMI_TYPE_CUSTOM);
            $item->setPath($path);
            $item->setTitle(['en' => $path, 'pt_BR' => 'Título ' . $path], null);
            $item->setContent('<p>Page & content</p>', 'en');
            $ids[$path] = $items->insertObject($item);
        }
        foreach (['primary', 'user'] as $area) {
            $menu = $menus->newDataObject();
            $menu->setContextId($source->getId());
            $menu->setTitle($area);
            $menu->setAreaName($area);
            $menus->insertObject($menu);
            foreach (['child', 'parent'] as $index => $path) {
                $assignment = $assignments->newDataObject();
                $assignment->setMenuId($menu->getId());
                $assignment->setMenuItemId($ids[$path]);
                $assignment->setParentId($path === 'child' ? $ids['parent'] : 0);
                $assignment->setSequence($index + 1);
                $assignments->insertObject($assignment);
                $assignment->setTitle(['en' => $area . ' ' . $path], null);
                $assignments->updateLocaleFields($assignment);
            }
        }
        $menu = $menus->newDataObject();
        $menu->setContextId($source->getId());
        $menu->setTitle('Unassigned');
        $menu->setAreaName('');
        $menus->insertObject($menu);
        $assignment = $assignments->newDataObject();
        $assignment->setMenuId($menu->getId());
        $assignment->setMenuItemId($ids['unassigned']);
        $assignment->setParentId(0);
        $assignment->setSequence(1);
        $assignments->insertObject($assignment);
        foreach (['supportedLocales', 'supportedFormLocales', 'supportedSubmissionLocales'] as $name) {
            $source->setData($name, ['en', 'pt_BR']);
        }
        $source->setData('name', ['en' => 'Navigation journal']);
        $source->setData('contactName', 'Editor');
        $source->setData('contactEmail', 'editor@example.com');
        $contextDao->updateObject($source);
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $this->assertTrue($document->schemaValidate(dirname(__DIR__, 3) . '/fullJournal.xsd'));
        $node = $document->getElementsByTagNameNS('http://pkp.sfu.ca', 'navigation_menus')->item(0);
        $this->assertSame(2, $node->getElementsByTagNameNS('http://pkp.sfu.ca', 'item')->length);
        $this->assertSame(2, $node->getElementsByTagNameNS('http://pkp.sfu.ca', 'menu')->length);
        // Older packages may contain unused items and unassigned menus; ignore them on import too.
        $extraItems = $document->createDocumentFragment();
        $extraItems->appendXML('<item xmlns="http://pkp.sfu.ca" source_ref="999999" '
            . 'type="UNAVAILABLE_PLUGIN_TYPE" path="unused" title_locale_key=""/>'
            . '<item xmlns="http://pkp.sfu.ca" source_ref="999998" '
            . 'type="NMI_TYPE_CUSTOM" path="unassigned" title_locale_key=""/>');
        $node->getElementsByTagNameNS('http://pkp.sfu.ca', 'items')->item(0)->appendChild($extraItems);
        $extraMenu = $document->createDocumentFragment();
        $extraMenu->appendXML('<menu xmlns="http://pkp.sfu.ca" title="Unassigned" area="">'
            . '<assignment item_ref="999998" parent_ref="0" seq="1"/></menu>');
        $node->appendChild($extraMenu);
        $transfer = new NavigationMenusTransfer();
        $transfer->import($node, (int) $target->getId(), ['en', 'pt_BR']);
        $newItems = $items->getByContextId($target->getId())->toArray();
        $this->assertCount(2, $newItems);
        $this->assertNull($items->getByPath($target->getId(), 'unused'));
        $this->assertNull($items->getByPath($target->getId(), 'unassigned'));
        $this->assertCount(2, $menus->getByContextId($target->getId())->toArray());
        $newParent = $items->getByPath($target->getId(), 'parent');
        $this->assertNotEquals($ids['parent'], $newParent->getId());
        $this->assertSame('Título parent', $newParent->getTitle('pt_BR'));
        $this->assertSame('<p>Page & content</p>', $newParent->getContent('en'));
        foreach ($menus->getByContextId($target->getId())->toArray() as $menu) {
            $links = array_values($assignments->getByMenuId($menu->getId())->toArray());
            $this->assertCount(2, $links);
            $this->assertSame((int) $newParent->getId(), (int) $links[0]->getParentId());
            $this->assertSame($menu->getTitle() . ' child', $links[0]->getTitle('en'));
            $this->assertSame(1, (int) $links[0]->getSequence());
            Services::get('navigationMenu')->loadMenuTree($menu);
            $this->assertCount(1, $menu->menuTree);
            $this->assertCount(1, $menu->menuTree[0]->children);
            $this->assertSame('child', $menu->menuTree[0]->children[0]->getMenuItem()->getPath());
        }
    }

    public function testItImportsNavigationThroughTheJournalFilter(): void
    {
        $source = new Journal();
        $source->setPath('navigation-import-' . bin2hex(random_bytes(4)));
        $source->setSequence(1);
        $source->setPrimaryLocale('en');
        foreach (['supportedLocales', 'supportedFormLocales', 'supportedSubmissionLocales'] as $name) {
            $source->setData($name, ['en']);
        }
        $source->setData('name', ['en' => 'Navigation journal']);
        $source->setData('contactName', 'Editor');
        $source->setData('contactEmail', 'editor@example.com');
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();
        $fragment = $document->createDocumentFragment();
        $fragment->appendXML('<navigation_menus xmlns="http://pkp.sfu.ca"><items>'
            . '<item source_ref="1" type="NMI_TYPE_REMOTE_URL" path="" title_locale_key="">'
            . '<field name="title" locale="en">External</field>'
            . '<field name="remoteUrl" locale="en">https://example.com/page</field></item></items>'
            . '<menu title="Primary" area="primary"><assignment item_ref="1" parent_ref="0" seq="1"/>'
            . '</menu></navigation_menus>');
        $document->documentElement->appendChild($fragment);
        $created = (new FullJournalImportExportDeployment(new Journal(), null))
            ->createContextData($document->documentElement);
        try {
            $items = DAORegistry::getDAO('NavigationMenuItemDAO')->getByContextId($created->getId())->toArray();
            $this->assertCount(1, $items);
            $item = array_values($items)[0];
            $this->assertSame('https://example.com/page', $item->getRemoteUrl('en'));
            $this->assertCount(1, DAORegistry::getDAO('NavigationMenuDAO')
                ->getByContextId($created->getId())->toArray());
        } finally {
            $manager = new PublicFileManager();
            $manager->rmtree($manager->getContextFilesPath($created->getId()));
        }
    }

    /** @dataProvider invalidNavigation */
    public function testItRejectsInvalidReferencesBeforeChangingTheDestination(string $xml): void
    {
        $document = new DOMDocument();
        $document->loadXML($xml);
        $this->expectException(InvalidArgumentException::class);
        (new NavigationMenusTransfer())->import($document->documentElement, 0, ['en']);
    }
    public static function invalidNavigation(): array
    {
        $item = '<item source_ref="1" type="NMI_TYPE_CUSTOM" path="page" title_locale_key=""/>';
        $start = '<navigation_menus xmlns="http://pkp.sfu.ca"><items>';
        $menu = '</items><menu title="Primary" area="primary">';
        $end = '</menu></navigation_menus>';
        return [
            'unsafe URL' => [$start . str_replace('/>', '><field name="remoteUrl" locale="en">'
                . 'javascript:alert(1)</field></item>', $item) . $menu . '<assignment item_ref="1" parent_ref="0" seq="1"/>' . $end],
            'invalid path' => [$start . str_replace('path="page"', 'path="bad?path"', $item) . $menu . '<assignment item_ref="1" parent_ref="0" seq="1"/>' . $end],
            'missing item' => [$start . $menu
                . '<assignment item_ref="42" parent_ref="0" seq="1"/>' . $end],
            'cycle' => [$start . $item . $menu
                . '<assignment item_ref="1" parent_ref="1" seq="1"/>' . $end],
            'missing parent' => [$start . $item . $menu
                . '<assignment item_ref="1" parent_ref="42" seq="1"/>' . $end],
            'duplicate item' => [$start . $item . $item . $menu . '<assignment item_ref="1" parent_ref="0" seq="1"/>' . $end],
            'unknown type' => [$start . str_replace('NMI_TYPE_CUSTOM', 'MISSING_TYPE', $item) . $menu . '<assignment item_ref="1" parent_ref="0" seq="1"/>' . $end],
        ];
    }
}
