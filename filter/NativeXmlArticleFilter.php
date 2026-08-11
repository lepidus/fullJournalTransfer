<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use PKP\plugins\importexport\PKPImportExportFilter;

class NativeXmlArticleFilter extends \APP\plugins\importexport\native\filter\NativeXmlArticleFilter
{
    public function getImportFilter($elementName)
    {
        if ($elementName === 'publication') {
            return PKPImportExportFilter::getFilter(
                'full-journal-native-xml=>publication',
                $this->getDeployment()
            );
        }
        if ($elementName === 'submission_file') {
            return PKPImportExportFilter::getFilter(
                'full-journal-native-xml=>submission-file',
                $this->getDeployment()
            );
        }
        return parent::getImportFilter($elementName);
    }
}
