<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\core\Application;
use APP\facades\Repo;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\ReferenceDataTransfer;
use InvalidArgumentException;
use PKP\db\DAORegistry;
use PKP\tests\DatabaseTestCase;

class ReferenceDataTransferIntegrationTest extends DatabaseTestCase
{
    private array $contexts = [];

    protected function getAffectedTables()
    {
        return [];
    }

    protected function tearDown(): void
    {
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $contextDao = Application::get()->getContextDAO();
        foreach (array_reverse($this->contexts) as $context) {
            Repo::section()->deleteByContextId((int) $context->getId());
            $genreDao->deleteByContextId($context->getId());
            $reviewFormDao->deleteByAssoc(Application::ASSOC_TYPE_JOURNAL, $context->getId());
            $contextDao->deleteObject($context);
        }
        parent::tearDown();
    }

    public function testItTransfersOrderedReferenceDataAndRemapsSectionReviewForm(): void
    {
        $source = $this->createContext('source');
        $destination = $this->createContext('destination');
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $reviewForm = $reviewFormDao->newDataObject();
        $reviewForm->setAssocType(Application::ASSOC_TYPE_JOURNAL);
        $reviewForm->setAssocId($source->getId());
        $reviewForm->setSequence(4.5);
        $reviewForm->setActive(1);
        $reviewForm->setTitle('H5 Review Form', 'en');
        $reviewForm->setDescription('H5 Description', 'en');
        $reviewFormId = $reviewFormDao->insertObject($reviewForm);
        $element = $reviewFormElementDao->newDataObject();
        $element->setReviewFormId($reviewFormId);
        $element->setSequence(2.5);
        $element->setElementType(4);
        $element->setRequired(true);
        $element->setIncluded(false);
        $element->setQuestion('Choose values', 'en');
        $element->setDescription('Element description', 'en');
        $element->setPossibleResponses(['One', 'Two'], 'en');
        $elementId = $reviewFormElementDao->insertObject($element);
        $genre = $genreDao->newDataObject();
        $genre->setContextId($source->getId());
        $genre->setKey('H5_GENRE_' . bin2hex(random_bytes(4)));
        $genre->setCategory(1);
        $genre->setDependent(false);
        $genre->setSupplementary(false);
        $genre->setRequired(true);
        $genre->setSequence(6);
        $genre->setEnabled(true);
        $genre->setName('H5 Genre', 'en');
        $genreId = $genreDao->insertObject($genre);
        $section = Repo::section()->newDataObject();
        $section->setContextId((int) $source->getId());
        $section->setSequence(7);
        $section->setTitle('H5 Section', 'en');
        $section->setAbbrev('H5S', 'en');
        $section->setPolicy('H5 Policy', 'en');
        $section->setReviewFormId($reviewFormId);
        $section->setEditorRestricted(false);
        $section->setMetaIndexed(true);
        $section->setMetaReviewed(true);
        $section->setAbstractsNotRequired(false);
        $section->setHideTitle(false);
        $section->setHideAuthor(false);
        $section->setIsInactive(false);
        $section->setAbstractWordCount(250);
        $sectionId = Repo::section()->add($section);

        $document = (new ReferenceDataTransfer())->export($source);
        $maps = (new ReferenceDataTransfer())->import($document->documentElement, $destination);

        $importedReviewForm = $reviewFormDao->getById(
            $maps['review_form_id_map'][(string) $reviewFormId],
            Application::ASSOC_TYPE_JOURNAL,
            $destination->getId()
        );
        $importedElement = $reviewFormElementDao->getById($maps['review_form_element_id_map'][(string) $elementId]);
        $importedGenre = $genreDao->getById($maps['genre_id_map'][(string) $genreId]);
        $importedSection = Repo::section()->get(
            $maps['section_id_map'][(string) $sectionId],
            (int) $destination->getId()
        );
        $this->assertSame(4.5, $importedReviewForm->getSequence());
        $this->assertSame($importedReviewForm->getId(), $importedElement->getReviewFormId());
        $this->assertSame(['One', 'Two'], $importedElement->getPossibleResponses('en'));
        $this->assertSame(6, $importedGenre->getSequence());
        $this->assertSame(7.0, $importedSection->getSequence());
        $this->assertSame($importedReviewForm->getId(), $importedSection->getReviewFormId());
        $this->assertSame((int) $destination->getId(), $importedSection->getContextId());
    }

    public function testItRejectsUnknownRelationsBeforeChangingDestinationData(): void
    {
        $destination = $this->createContext('invalid-destination');
        $section = Repo::section()->newDataObject();
        $section->setContextId((int) $destination->getId());
        $section->setSequence(1);
        $section->setTitle('Sentinel Section', 'en');
        $section->setAbbrev('SS', 'en');
        $section->setEditorRestricted(false);
        $section->setMetaIndexed(true);
        $section->setMetaReviewed(true);
        $section->setAbstractsNotRequired(false);
        $section->setHideTitle(false);
        $section->setHideAuthor(false);
        $section->setIsInactive(false);
        $section->setAbstractWordCount(0);
        $sectionId = Repo::section()->add($section);
        $document = new \DOMDocument();
        $this->assertTrue($document->loadXML(
            '<reference_data xmlns="http://pkp.sfu.ca">'
            . '<review_forms/><genres/><sections>'
            . '<section source_ref="section-1" sequence="1" editor_restricted="false" '
            . 'meta_indexed="true" meta_reviewed="true" abstracts_not_required="false" '
            . 'hide_title="false" hide_author="false" inactive="false" abstract_word_count="0" '
            . 'review_form_ref="missing-form"><title locale="en">Invalid</title>'
            . '<abbrev locale="en">INV</abbrev></section>'
            . '</sections></reference_data>'
        ));

        try {
            (new ReferenceDataTransfer())->import($document->documentElement, $destination);
            $this->fail('Unknown review form relation was accepted');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Unknown review form reference in section', $exception->getMessage());
        }

        $this->assertNotNull(Repo::section()->get($sectionId, (int) $destination->getId()));
    }

    private function createContext(string $label): Journal
    {
        $context = Application::get()->getContextDAO()->newDataObject();
        $context->setPath('h5-' . substr($label, 0, 5) . '-' . bin2hex(random_bytes(4)));
        $context->setPrimaryLocale('en');
        $context->setEnabled(false);
        Application::get()->getContextDAO()->insertObject($context);
        $this->contexts[] = $context;
        return $context;
    }
}
