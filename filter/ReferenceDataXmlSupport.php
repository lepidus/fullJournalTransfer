<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\reviewForm\ReviewFormDAO;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\submission\Genre;
use PKP\submission\GenreDAO;

class ReferenceDataXmlSupport
{
    private const NAMESPACE = 'http://pkp.sfu.ca';

    public function export(Journal $context): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->formatOutput = true;
        $root = $document->createElementNS(self::NAMESPACE, 'reference_data');
        $document->appendChild($root);
        $this->exportReviewForms($document, $root, $context);
        $this->exportGenres($document, $root, $context);
        $this->exportSections($document, $root, $context);
        return $document;
    }

    public function import(DOMElement $root, Journal $context): array
    {
        $this->validate($root);
        $this->clearDestination($context);
        $reviewFormIdMap = [];
        $reviewFormElementIdMap = [];
        $genreIdMap = [];
        $sectionIdMap = [];
        $this->importReviewForms($root, $context, $reviewFormIdMap, $reviewFormElementIdMap);
        $this->importGenres($root, $context, $genreIdMap);
        $this->importSections($root, $context, $reviewFormIdMap, $sectionIdMap);
        return [
            'review_form_id_map' => $reviewFormIdMap,
            'review_form_element_id_map' => $reviewFormElementIdMap,
            'genre_id_map' => $genreIdMap,
            'section_id_map' => $sectionIdMap,
        ];
    }

    public function validate(DOMElement $root): void
    {
        if ($root->localName !== 'reference_data') {
            throw new InvalidArgumentException('Invalid reference data root');
        }
        $reviewFormReferences = [];
        $elementReferences = [];
        $genreReferences = [];
        $sectionReferences = [];
        foreach ($this->children($this->requiredContainer($root, 'review_forms'), 'review_form') as $formNode) {
            $reviewFormReferences[$this->sourceReference($formNode, $reviewFormReferences)] = true;
            $this->floatAttribute($formNode, 'sequence');
            $this->booleanAttribute($formNode, 'active');
            $this->validateLocalized($formNode, 'title', true);
            $this->validateLocalized($formNode, 'description', false);
            foreach ($this->children(
                $this->requiredContainer($formNode, 'review_form_elements'),
                'review_form_element'
            ) as $elementNode) {
                $elementReferences[$this->sourceReference($elementNode, $elementReferences)] = true;
                $this->floatAttribute($elementNode, 'sequence');
                $this->validateElementType($this->integerAttribute($elementNode, 'element_type'));
                $this->booleanAttribute($elementNode, 'required');
                $this->booleanAttribute($elementNode, 'included');
                $this->validateLocalized($elementNode, 'question', true);
                $this->validateLocalized($elementNode, 'description', false);
                foreach ($this->children($elementNode, 'possible_responses') as $responsesNode) {
                    $this->localeAttribute($responsesNode);
                    foreach ($this->children($responsesNode, 'response') as $responseNode) {
                        if ($responseNode->textContent === '') {
                            throw new InvalidArgumentException('Empty review form response');
                        }
                    }
                }
            }
        }
        foreach ($this->children($this->requiredContainer($root, 'genres'), 'genre') as $genreNode) {
            $genreReferences[$this->sourceReference($genreNode, $genreReferences)] = true;
            $this->requiredAttribute($genreNode, 'key');
            $this->validateGenreCategory($this->integerAttribute($genreNode, 'category'));
            $this->booleanAttribute($genreNode, 'dependent');
            $this->booleanAttribute($genreNode, 'supplementary');
            $this->booleanAttribute($genreNode, 'required');
            $this->floatAttribute($genreNode, 'sequence');
            $this->booleanAttribute($genreNode, 'enabled');
            $this->validateLocalized($genreNode, 'name', true);
        }
        foreach ($this->children($this->requiredContainer($root, 'sections'), 'section') as $sectionNode) {
            $sectionReferences[$this->sourceReference($sectionNode, $sectionReferences)] = true;
            $this->floatAttribute($sectionNode, 'sequence');
            foreach ([
                'editor_restricted',
                'meta_indexed',
                'meta_reviewed',
                'abstracts_not_required',
                'hide_title',
                'hide_author',
                'inactive',
            ] as $attribute) {
                $this->booleanAttribute($sectionNode, $attribute);
            }
            $this->integerAttribute($sectionNode, 'abstract_word_count');
            $reviewFormReference = trim($sectionNode->getAttribute('review_form_ref'));
            if ($reviewFormReference !== '' && !isset($reviewFormReferences[$reviewFormReference])) {
                throw new InvalidArgumentException('Unknown review form reference in section');
            }
            $this->validateLocalized($sectionNode, 'title', true);
            $this->validateLocalized($sectionNode, 'abbrev', true);
            $this->validateLocalized($sectionNode, 'policy', false);
        }
    }

    public function exportReviewForms(DOMDocument $document, DOMElement $root, Journal $context): void
    {
        $reviewFormDao = $this->reviewFormDao();
        $elementDao = $this->reviewFormElementDao();
        $container = $document->createElementNS(self::NAMESPACE, 'review_forms');
        foreach ($reviewFormDao->getByAssocId(Application::ASSOC_TYPE_JOURNAL, $context->getId())->toArray() as $form) {
            $formNode = $document->createElementNS(self::NAMESPACE, 'review_form');
            $formNode->setAttribute('source_ref', (string) $form->getId());
            $formNode->setAttribute('sequence', (string) $form->getSequence());
            $formNode->setAttribute('active', $form->getActive() ? 'true' : 'false');
            $this->appendLocalized($document, $formNode, 'title', $form->getTitle(null));
            $this->appendLocalized($document, $formNode, 'description', $form->getDescription(null));
            $elementsNode = $document->createElementNS(self::NAMESPACE, 'review_form_elements');
            foreach ($elementDao->getByReviewFormId($form->getId())->toArray() as $element) {
                $elementNode = $document->createElementNS(self::NAMESPACE, 'review_form_element');
                $elementNode->setAttribute('source_ref', (string) $element->getId());
                $elementNode->setAttribute('sequence', (string) $element->getSequence());
                $elementNode->setAttribute('element_type', (string) $element->getElementType());
                $elementNode->setAttribute('required', $element->getRequired() ? 'true' : 'false');
                $elementNode->setAttribute('included', $element->getIncluded() ? 'true' : 'false');
                $this->appendLocalized($document, $elementNode, 'question', $element->getQuestion(null));
                $this->appendLocalized($document, $elementNode, 'description', $element->getDescription(null));
                foreach ((array) $element->getPossibleResponses(null) as $locale => $responses) {
                    $responsesNode = $document->createElementNS(self::NAMESPACE, 'possible_responses');
                    $responsesNode->setAttribute('locale', (string) $locale);
                    foreach ((array) $responses as $response) {
                        $responseNode = $document->createElementNS(self::NAMESPACE, 'response');
                        $responseNode->appendChild($document->createTextNode((string) $response));
                        $responsesNode->appendChild($responseNode);
                    }
                    $elementNode->appendChild($responsesNode);
                }
                $elementsNode->appendChild($elementNode);
            }
            $formNode->appendChild($elementsNode);
            $container->appendChild($formNode);
        }
        $root->appendChild($container);
    }

    public function exportGenres(DOMDocument $document, DOMElement $root, Journal $context): void
    {
        $container = $document->createElementNS(self::NAMESPACE, 'genres');
        foreach ($this->genreDao()->getByContextId($context->getId())->toArray() as $genre) {
            $node = $document->createElementNS(self::NAMESPACE, 'genre');
            $node->setAttribute('source_ref', (string) $genre->getId());
            $node->setAttribute('key', (string) $genre->getKey());
            $node->setAttribute('category', (string) $genre->getCategory());
            $node->setAttribute('dependent', $genre->getDependent() ? 'true' : 'false');
            $node->setAttribute('supplementary', $genre->getSupplementary() ? 'true' : 'false');
            $node->setAttribute('required', $genre->getRequired() ? 'true' : 'false');
            $node->setAttribute('sequence', (string) $genre->getSequence());
            $node->setAttribute('enabled', $genre->getEnabled() ? 'true' : 'false');
            $this->appendLocalized($document, $node, 'name', $genre->getName(null));
            $container->appendChild($node);
        }
        $root->appendChild($container);
    }

    public function exportSections(DOMDocument $document, DOMElement $root, Journal $context): void
    {
        $container = $document->createElementNS(self::NAMESPACE, 'sections');
        $sections = Repo::section()->getCollector()->filterByContextIds([(int) $context->getId()])->getMany();
        foreach ($sections as $section) {
            $node = $document->createElementNS(self::NAMESPACE, 'section');
            $node->setAttribute('source_ref', (string) $section->getId());
            $node->setAttribute('sequence', (string) $section->getSequence());
            $node->setAttribute('editor_restricted', $section->getEditorRestricted() ? 'true' : 'false');
            $node->setAttribute('meta_indexed', $section->getMetaIndexed() ? 'true' : 'false');
            $node->setAttribute('meta_reviewed', $section->getMetaReviewed() ? 'true' : 'false');
            $node->setAttribute('abstracts_not_required', $section->getAbstractsNotRequired() ? 'true' : 'false');
            $node->setAttribute('hide_title', $section->getHideTitle() ? 'true' : 'false');
            $node->setAttribute('hide_author', $section->getHideAuthor() ? 'true' : 'false');
            $node->setAttribute('inactive', $section->getIsInactive() ? 'true' : 'false');
            $node->setAttribute('abstract_word_count', (string) ($section->getAbstractWordCount() ?? 0));
            if ($section->getReviewFormId()) {
                $node->setAttribute('review_form_ref', (string) $section->getReviewFormId());
            }
            $this->appendLocalized($document, $node, 'title', $section->getTitle(null));
            $this->appendLocalized($document, $node, 'abbrev', $section->getAbbrev(null));
            $this->appendLocalized($document, $node, 'policy', $section->getPolicy(null));
            $container->appendChild($node);
        }
        $root->appendChild($container);
    }

    public function importReviewForms(
        DOMElement $root,
        Journal $context,
        array &$reviewFormIdMap,
        array &$elementIdMap
    ): void {
        $reviewFormDao = $this->reviewFormDao();
        $elementDao = $this->reviewFormElementDao();
        foreach ($this->children($this->requiredContainer($root, 'review_forms'), 'review_form') as $formNode) {
            $sourceReference = $this->sourceReference($formNode, $reviewFormIdMap);
            $form = $reviewFormDao->newDataObject();
            $form->setAssocType(Application::ASSOC_TYPE_JOURNAL);
            $form->setAssocId($context->getId());
            $form->setSequence($this->floatAttribute($formNode, 'sequence'));
            $form->setActive($this->booleanAttribute($formNode, 'active') ? 1 : 0);
            $this->applyLocalized($formNode, 'title', [$form, 'setTitle'], true);
            $this->applyLocalized($formNode, 'description', [$form, 'setDescription'], false);
            $formId = $reviewFormDao->insertObject($form);
            $reviewFormIdMap[$sourceReference] = $formId;
            $elementsNode = $this->requiredContainer($formNode, 'review_form_elements');
            foreach ($this->children($elementsNode, 'review_form_element') as $elementNode) {
                $elementSourceReference = $this->sourceReference($elementNode, $elementIdMap);
                $element = $elementDao->newDataObject();
                $element->setReviewFormId($formId);
                $element->setSequence($this->floatAttribute($elementNode, 'sequence'));
                $elementType = $this->integerAttribute($elementNode, 'element_type');
                $this->validateElementType($elementType);
                $element->setElementType($elementType);
                $element->setRequired($this->booleanAttribute($elementNode, 'required'));
                $element->setIncluded($this->booleanAttribute($elementNode, 'included'));
                $this->applyLocalized($elementNode, 'question', [$element, 'setQuestion'], true);
                $this->applyLocalized($elementNode, 'description', [$element, 'setDescription'], false);
                foreach ($this->children($elementNode, 'possible_responses') as $responsesNode) {
                    $locale = $this->localeAttribute($responsesNode);
                    $responses = array_map(
                        static fn (DOMElement $response): string => $response->textContent,
                        $this->children($responsesNode, 'response')
                    );
                    $element->setPossibleResponses($responses, $locale);
                }
                $elementIdMap[$elementSourceReference] = $elementDao->insertObject($element);
            }
        }
    }

    public function importGenres(DOMElement $root, Journal $context, array &$genreIdMap): void
    {
        $genreDao = $this->genreDao();
        foreach ($this->children($this->requiredContainer($root, 'genres'), 'genre') as $node) {
            $sourceReference = $this->sourceReference($node, $genreIdMap);
            $category = $this->integerAttribute($node, 'category');
            $this->validateGenreCategory($category);
            $genre = $genreDao->newDataObject();
            $genre->setContextId($context->getId());
            $genre->setKey($this->requiredAttribute($node, 'key'));
            $genre->setCategory($category);
            $genre->setDependent($this->booleanAttribute($node, 'dependent'));
            $genre->setSupplementary($this->booleanAttribute($node, 'supplementary'));
            $genre->setRequired($this->booleanAttribute($node, 'required'));
            $genre->setSequence($this->floatAttribute($node, 'sequence'));
            $genre->setEnabled($this->booleanAttribute($node, 'enabled'));
            $this->applyLocalized($node, 'name', [$genre, 'setName'], true);
            $genreIdMap[$sourceReference] = $genreDao->insertObject($genre);
        }
    }

    public function importSections(
        DOMElement $root,
        Journal $context,
        array $reviewFormIdMap,
        array &$sectionIdMap
    ): void {
        foreach ($this->children($this->requiredContainer($root, 'sections'), 'section') as $node) {
            $sourceReference = $this->sourceReference($node, $sectionIdMap);
            $section = Repo::section()->newDataObject();
            $section->setContextId((int) $context->getId());
            $section->setSequence($this->floatAttribute($node, 'sequence'));
            $section->setEditorRestricted($this->booleanAttribute($node, 'editor_restricted'));
            $section->setMetaIndexed($this->booleanAttribute($node, 'meta_indexed'));
            $section->setMetaReviewed($this->booleanAttribute($node, 'meta_reviewed'));
            $section->setAbstractsNotRequired($this->booleanAttribute($node, 'abstracts_not_required'));
            $section->setHideTitle($this->booleanAttribute($node, 'hide_title'));
            $section->setHideAuthor($this->booleanAttribute($node, 'hide_author'));
            $section->setIsInactive($this->booleanAttribute($node, 'inactive'));
            $section->setAbstractWordCount($this->integerAttribute($node, 'abstract_word_count'));
            $reviewFormReference = trim($node->getAttribute('review_form_ref'));
            if ($reviewFormReference !== '') {
                if (!isset($reviewFormIdMap[$reviewFormReference])) {
                    throw new InvalidArgumentException('Unknown review form reference in section');
                }
                $section->setReviewFormId($reviewFormIdMap[$reviewFormReference]);
            }
            $this->applyLocalized($node, 'title', [$section, 'setTitle'], true);
            $this->applyLocalized($node, 'abbrev', [$section, 'setAbbrev'], true);
            $this->applyLocalized($node, 'policy', [$section, 'setPolicy'], false);
            $sectionIdMap[$sourceReference] = Repo::section()->add($section);
        }
    }

    private function clearDestination(Journal $context): void
    {
        Repo::section()->deleteByContextId((int) $context->getId());
        $this->genreDao()->deleteByContextId($context->getId());
        $this->reviewFormDao()->deleteByAssoc(Application::ASSOC_TYPE_JOURNAL, $context->getId());
    }

    private function appendLocalized(
        DOMDocument $document,
        DOMElement $parent,
        string $name,
        $values
    ): void {
        foreach ((array) $values as $locale => $value) {
            if ($value === null) {
                continue;
            }
            $node = $document->createElementNS(self::NAMESPACE, $name);
            $node->setAttribute('locale', (string) $locale);
            $node->appendChild($document->createTextNode((string) $value));
            $parent->appendChild($node);
        }
    }

    private function applyLocalized(DOMElement $parent, string $name, callable $setter, bool $required): void
    {
        $nodes = $this->children($parent, $name);
        if ($required && $nodes === []) {
            throw new InvalidArgumentException('Missing localized reference data: ' . $name);
        }
        $locales = [];
        foreach ($nodes as $node) {
            $locale = $this->localeAttribute($node);
            if (isset($locales[$locale])) {
                throw new InvalidArgumentException('Duplicated localized reference data: ' . $name);
            }
            $locales[$locale] = true;
            $setter($node->textContent, $locale);
        }
    }

    private function validateLocalized(DOMElement $parent, string $name, bool $required): void
    {
        $nodes = $this->children($parent, $name);
        if ($required && $nodes === []) {
            throw new InvalidArgumentException('Missing localized reference data: ' . $name);
        }
        $locales = [];
        foreach ($nodes as $node) {
            $locale = $this->localeAttribute($node);
            if (isset($locales[$locale])) {
                throw new InvalidArgumentException('Duplicated localized reference data: ' . $name);
            }
            $locales[$locale] = true;
        }
    }

    private function children(DOMElement $parent, string $name): array
    {
        $children = [];
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                $children[] = $child;
            }
        }
        return $children;
    }

    private function requiredContainer(DOMElement $parent, string $name): DOMElement
    {
        $matches = $this->children($parent, $name);
        if (count($matches) !== 1) {
            throw new InvalidArgumentException('Expected exactly one reference data container: ' . $name);
        }
        return $matches[0];
    }

    private function sourceReference(DOMElement $node, array $map): string
    {
        $sourceReference = $this->requiredAttribute($node, 'source_ref');
        if (isset($map[$sourceReference])) {
            throw new InvalidArgumentException('Duplicated source reference in reference data');
        }
        return $sourceReference;
    }

    private function requiredAttribute(DOMElement $node, string $name): string
    {
        $value = trim($node->getAttribute($name));
        if ($value === '') {
            throw new InvalidArgumentException('Missing reference data attribute: ' . $name);
        }
        return $value;
    }

    private function booleanAttribute(DOMElement $node, string $name): bool
    {
        $value = $this->requiredAttribute($node, $name);
        if (!in_array($value, ['true', 'false'], true)) {
            throw new InvalidArgumentException('Invalid boolean reference data attribute: ' . $name);
        }
        return $value === 'true';
    }

    private function integerAttribute(DOMElement $node, string $name): int
    {
        $value = $this->requiredAttribute($node, $name);
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Invalid integer reference data attribute: ' . $name);
        }
        return (int) $value;
    }

    private function floatAttribute(DOMElement $node, string $name): float
    {
        $value = $this->requiredAttribute($node, $name);
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Invalid numeric reference data attribute: ' . $name);
        }
        return (float) $value;
    }

    private function localeAttribute(DOMElement $node): string
    {
        $locale = $this->requiredAttribute($node, 'locale');
        if (preg_match('/^[a-z]{2}(?:_[A-Z]{2})?$/', $locale) !== 1) {
            throw new InvalidArgumentException('Invalid locale in reference data');
        }
        return $locale;
    }

    private function validateElementType(int $elementType): void
    {
        if (!in_array($elementType, [
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX,
        ], true)) {
            throw new InvalidArgumentException('Invalid review form element type');
        }
    }

    private function validateGenreCategory(int $category): void
    {
        if (!in_array($category, [
            Genre::GENRE_CATEGORY_DOCUMENT,
            Genre::GENRE_CATEGORY_ARTWORK,
            Genre::GENRE_CATEGORY_SUPPLEMENTARY,
        ], true)) {
            throw new InvalidArgumentException('Invalid genre category');
        }
    }

    private function reviewFormDao(): ReviewFormDAO
    {
        return DAORegistry::getDAO('ReviewFormDAO');
    }

    private function reviewFormElementDao(): ReviewFormElementDAO
    {
        return DAORegistry::getDAO('ReviewFormElementDAO');
    }

    private function genreDao(): GenreDAO
    {
        return DAORegistry::getDAO('GenreDAO');
    }
}
