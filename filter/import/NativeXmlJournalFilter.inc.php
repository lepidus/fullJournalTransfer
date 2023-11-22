<?php

/**
 * Copyright (c) 2019-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');
import('lib.pkp.classes.services.PKPSchemaService');

class NativeXmlJournalFilter extends NativeImportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML journal import');
        parent::__construct($filterGroup);
    }

    public function getSingularElementName()
    {
        return 'journal';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlJournalFilter';
    }

    public function handleChildElement($n, $journal)
    {
        $deployment = $this->getDeployment();

        $simpleNodeMapping = $this->getSimpleJournalNodeMapping();
        $localizedNodeMapping = $this->getLocalizedJournalNodeMapping();
        $localesNodeMapping = $this->getLocalesJournalNodeMapping();

        $propName = $this->snakeToCamel($n->tagName);
        if (in_array($n->tagName, $simpleNodeMapping)) {
            $journal->setData($propName, $n->textContent);
        }
        if (in_array($n->tagName, $localizedNodeMapping)) {
            list($locale, $value) = $this->parseLocalizedContent($n);
            if (empty($locale)) {
                $locale = $journal->getPrimaryLocale();
            }
            $journal->setData($propName, $value, $locale);
        }
        if (in_array($n->tagName, $localesNodeMapping)) {
            $locales = preg_split('/:/', $n->textContent);
            $journal->setData($propName, $locales);
        }
    }

    private function getSimpleJournalNodeMapping()
    {
        return [
            'email_signature',
            'contact_email',
            'contact_name',
            'contact_phone',
            'mailing_address',
            'online_issn',
            'print_issn',
            'publisher_institution',
            'support_email',
            'support_name',
            'support_phone'
        ];
    }

    private function getLocalizedJournalNodeMapping()
    {
        return [
            'acronym',
            'author_information',
            'clockss_license',
            'librarian_information',
            'lockss_license',
            'name',
            'open_access_policy',
            'privacy_statement',
            'reader_information',
            'abbreviation',
            'about',
            'contact_affiliation',
            'description',
            'editorial_team',
        ];
    }

    private function getLocalesJournalNodeMapping()
    {
        return [
            'supported_locales',
            'supported_form_locales',
            'supported_submission_locales',
        ];
    }

    private function snakeToCamel($text)
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $text))));
    }
}
