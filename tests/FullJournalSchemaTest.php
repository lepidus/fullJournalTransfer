<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class FullJournalSchemaTest extends TestCase
{
    public function testItAcceptsAFullJournalDocumentForOjs34(): void
    {
        $document = $this->loadXml(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<journal xmlns="http://pkp.sfu.ca" primary_locale="pt_BR">'
            . '<locales><locale code="pt_BR"/><locale code="en"/></locales>'
            . '<submission_checklist>'
            . '<item locale="pt_BR" order="1">O arquivo está no formato aceito.</item>'
            . '</submission_checklist>'
            . '<workflow_history><review_round submission_ref="submission-1" round="1"/></workflow_history>'
            . '<metrics><metric family="context" value="3" date="2026-08-10">'
            . '<dimension name="country" value="BR"/></metric></metrics>'
            . '</journal>'
        );

        $this->assertTrue($document->schemaValidate($this->schemaPath()));
    }

    public function testPluginSchemaTypeNamesUseSnakeCase(): void
    {
        $document = $this->loadXml((string) file_get_contents($this->schemaPath()));
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
        $camelCaseNames = [];

        foreach ($xpath->query('//xs:simpleType[@name] | //xs:complexType[@name]') ?: [] as $type) {
            $name = $type->attributes?->getNamedItem('name')?->nodeValue;
            if (is_string($name) && preg_match('/[A-Z]/', $name)) {
                $camelCaseNames[] = $name;
            }
        }

        $this->assertSame([], $camelCaseNames);
    }

    public function testItRejectsAnInvalidRoot(): void
    {
        $this->assertInvalidDocument(
            '<package xmlns="http://pkp.sfu.ca" primary_locale="pt_BR">'
            . '<locales><locale code="pt_BR"/></locales></package>'
        );
    }

    public function testItRejectsMissingLocales(): void
    {
        $this->assertInvalidDocument('<journal xmlns="http://pkp.sfu.ca" primary_locale="pt_BR"/>');
    }

    public function testItRejectsDuplicateSingletons(): void
    {
        $this->assertInvalidDocument(
            '<journal xmlns="http://pkp.sfu.ca" primary_locale="pt_BR">'
            . '<locales><locale code="pt_BR"/></locales>'
            . '<locales><locale code="en"/></locales></journal>'
        );
    }

    public function testItRejectsAnInvalidMetric(): void
    {
        $this->assertInvalidDocument(
            '<journal xmlns="http://pkp.sfu.ca" primary_locale="pt_BR">'
            . '<locales><locale code="pt_BR"/></locales>'
            . '<metrics><metric family="context" value="-1"/></metrics></journal>'
        );
    }

    public function testItRejectsDuplicatedPackageMetadata(): void
    {
        $this->assertInvalidDocument(
            '<journal xmlns="http://pkp.sfu.ca" primary_locale="pt_BR" '
            . 'application_version="3.4.0.10" format_version="1.0">'
            . '<locales><locale code="pt_BR"/></locales></journal>'
        );
    }

    private function loadXml(string $xml): DOMDocument
    {
        $document = new DOMDocument();
        $this->assertTrue($document->loadXML($xml, LIBXML_NONET));
        return $document;
    }

    private function schemaPath(): string
    {
        return dirname(__DIR__) . '/fullJournal.xsd';
    }

    private function assertInvalidDocument(string $xml): void
    {
        $document = $this->loadXml($xml);

        $previous = libxml_use_internal_errors(true);
        $valid = $document->schemaValidate($this->schemaPath());
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $this->assertFalse($valid);
        $this->assertNotEmpty($errors);
    }
}
