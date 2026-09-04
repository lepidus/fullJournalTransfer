<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\schema;

use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\TestCase;

class FullJournalSchemaTest extends TestCase
{
    public function testItAcceptsAFullJournalDocumentForOjs34(): void
    {
        $document = $this->loadXml(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<journal xmlns="http://pkp.sfu.ca" primary_locale="pt_BR" url_path="journal" '
            . 'sequence="1" source_enabled="true">'
            . '<locales>'
            . '<locale code="pt_BR" enabled_for_ui="true" enabled_for_forms="true" form_order="2" '
            . 'enabled_for_submissions="true" submission_order="1"/>'
            . '<locale code="en" enabled_for_ui="true" enabled_for_forms="true" form_order="1" '
            . 'enabled_for_submissions="false"/>'
            . '</locales>'
            . '<submission_checklist>'
            . '<content locale="pt_BR">&lt;ul&gt;&lt;li&gt;O arquivo está no formato aceito.&lt;/li&gt;&lt;/ul&gt;</content>'
            . '</submission_checklist>'
            . '<context_settings>'
            . '<setting name="name" type="string" locale="pt_BR">Periódico</setting>'
            . '<setting name="contactName" type="string">Equipe editorial</setting>'
            . '<setting name="contactEmail" type="string">editor@example.com</setting>'
            . '<setting name="enabledDoiTypes" type="string-list">["publication","issue"]</setting>'
            . '</context_settings>'
            . '<theme plugin_path="default" plugin_name="defaultthemeplugin">'
            . '<option name="baseColour">&quot;#123456&quot;</option></theme>'
            . '<native_data><issue_orders/><issues/><articles/><author_metadata>'
            . '<author author_ref="42"><preferred_public_name locale="en">Ada L.</preferred_public_name>'
            . '<competing_interests locale="pt_BR">Nenhum</competing_interests></author>'
            . '</author_metadata><historical_dates><issues/><submissions/></historical_dates></native_data>'
            . '<workflow_history><review_round submission_ref="submission-1" round="1"/></workflow_history>'
            . '<metrics><context_metrics><context_metric load_id="usage.log" date="2026-08-10" metric="3"/>'
            . '</context_metrics><submission_metrics/><issue_metrics/><geo_metrics/><counter_metrics/>'
            . '<institution_metrics/></metrics>'
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

    public function testItRejectsALocaleWithoutAnExplicitUiFlag(): void
    {
        $this->assertInvalidDocument(
            '<journal xmlns="http://pkp.sfu.ca" primary_locale="en" url_path="journal" '
            . 'sequence="1" source_enabled="false"><locales>'
            . '<locale code="en" enabled_for_forms="true" form_order="1" '
            . 'enabled_for_submissions="true" submission_order="1"/></locales>'
            . '<context_settings><setting name="name" type="string" locale="en">Journal</setting>'
            . '<setting name="contactName" type="string">Editor</setting>'
            . '<setting name="contactEmail" type="string">editor@example.com</setting>'
            . '</context_settings></journal>'
        );
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
            . '<metrics><context_metrics><context_metric load_id="usage.log" date="2026-08-10" metric="-1"/>'
            . '</context_metrics><submission_metrics/><issue_metrics/><geo_metrics/><counter_metrics/>'
            . '<institution_metrics/></metrics></journal>'
        );
    }

    public function testItRejectsNativeDataWithoutTypedSourceReferences(): void
    {
        $this->assertInvalidDocument(
            '<journal xmlns="http://pkp.sfu.ca" primary_locale="en" url_path="journal" '
            . 'sequence="1" source_enabled="false">'
            . '<locales><locale code="en" enabled_for_ui="true" enabled_for_forms="true" form_order="1" '
            . 'enabled_for_submissions="true" submission_order="1"/></locales>'
            . '<context_settings><setting name="name" type="string" locale="en">Journal</setting>'
            . '<setting name="contactName" type="string">Editor</setting>'
            . '<setting name="contactEmail" type="string">editor@example.com</setting></context_settings>'
            . '<native_data><issues><issue_record><issue/></issue_record></issues>'
            . '<submissions/></native_data></journal>'
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
        return dirname(__DIR__, 3) . '/fullJournal.xsd';
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
