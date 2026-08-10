<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportPlugin;
use APP\plugins\importexport\native\NativeImportExportPlugin;
use PHPUnit\Framework\TestCase;

class FullJournalImportExportPluginTest extends TestCase
{
    public function testEntrypointLoadsNativeOjs34Plugin(): void
    {
        $plugin = require dirname(__DIR__) . '/index.php';

        $this->assertInstanceOf(FullJournalImportExportPlugin::class, $plugin);
        $this->assertInstanceOf(NativeImportExportPlugin::class, $plugin);
        $this->assertSame('FullJournalImportExportPlugin', $plugin->getName());
    }

    public function testDeploymentUsesNativeOjsSubmissionNodes(): void
    {
        $plugin = new FullJournalImportExportPlugin();
        $deployment = $plugin->getAppSpecificDeployment(new Journal(), null);

        $this->assertInstanceOf(FullJournalImportExportDeployment::class, $deployment);
        $this->assertSame('article', $deployment->getSubmissionNodeName());
        $this->assertSame('articles', $deployment->getSubmissionsNodeName());
        $this->assertSame('article_galley', $deployment->getRepresentationNodeName());
    }
}
