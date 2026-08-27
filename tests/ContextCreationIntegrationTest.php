<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\core\Application;
use APP\file\PublicFileManager;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use InvalidArgumentException;
use PKP\tests\DatabaseTestCase;

class ContextCreationIntegrationTest extends DatabaseTestCase
{
    private ?Journal $createdContext = null;

    protected function getAffectedTables()
    {
        return [];
    }

    protected function tearDown(): void
    {
        if ($this->createdContext && $this->createdContext->getId()) {
            $publicFileManager = new PublicFileManager();
            $publicFileManager->rmtree(
                $publicFileManager->getContextFilesPath((int) $this->createdContext->getId())
            );
            Application::get()->getContextDAO()->deleteObject($this->createdContext);
        }
        parent::tearDown();
    }

    public function testItCreatesTheImportedContextDisabledAndRejectsAConflictingPath(): void
    {
        $source = new Journal();
        $source->setPath('created-journal-' . bin2hex(random_bytes(4)));
        $source->setSequence(3);
        $source->setEnabled(true);
        $source->setPrimaryLocale('en');
        $source->setData('supportedLocales', ['en']);
        $source->setData('supportedFormLocales', ['en']);
        $source->setData('supportedSubmissionLocales', ['en']);
        $source->setData('submissionChecklist', ['en' => '<ul><li>Ready</li></ul>']);
        $source->setData('name', ['en' => 'Created Journal']);
        $source->setData('contactName', 'Editorial Team');
        $source->setData('contactEmail', 'editor@example.com');
        $document = (new FullJournalImportExportDeployment($source, null))->exportContextData();

        $deployment = new FullJournalImportExportDeployment($source, null);
        $created = $deployment->createContextData($document->documentElement);
        $this->createdContext = $created;

        $this->assertGreaterThan(0, (int) $created->getId());
        $this->assertFalse($created->getEnabled());
        $this->assertSame('Created Journal', $created->getData('name', 'en'));
        $publicFileManager = new PublicFileManager();
        $this->assertDirectoryExists($publicFileManager->getContextFilesPath((int) $created->getId()));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A context with this path already exists');

        $deployment->createContextData($document->documentElement);
    }
}
