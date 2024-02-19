<?php

import('plugins.importexport.native.filter.IssueNativeXmlFilter');

class ExtendedIssueNativeXmlFilter extends IssueNativeXmlFilter
{
    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.export.ExtendedIssueNativeXmlFilter';
    }

    public function &process(&$issues)
    {
        $doc = new DOMDocument('1.0', 'utf-8');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $deployment = $this->getDeployment();

        if (count($issues) == 1) {
            $rootNode = $this->createIssueNode($doc, $issues[0]);
        } else {
            $rootNode = $doc->createElementNS($deployment->getNamespace(), 'extended_issues');
            foreach ($issues as $issue) {
                $rootNode->appendChild($this->createIssueNode($doc, $issue));
            }
        }
        $doc->appendChild($rootNode);
        $rootNode->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $rootNode->setAttribute('xsi:schemaLocation', $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename());

        return $doc;
    }

    public function createIssueNode($doc, $issue)
    {
        $deployment = $this->getDeployment();
        $deployment->setIssue($issue);

        $issueNode = $doc->createElementNS($deployment->getNamespace(), 'extended_issue');
        $this->addIdentifiers($doc, $issueNode, $issue);

        $issueNode->setAttribute('published', $issue->getPublished());
        $issueNode->setAttribute('current', $issue->getCurrent());
        $issueNode->setAttribute('access_status', $issue->getAccessStatus());
        $issueNode->setAttribute('url_path', $issue->getData('urlPath'));

        $this->createLocalizedNodes($doc, $issueNode, 'description', $issue->getDescription(null));
        import('plugins.importexport.native.filter.NativeFilterHelper');
        $nativeFilterHelper = new NativeFilterHelper();
        $issueNode->appendChild($nativeFilterHelper->createIssueIdentificationNode($this, $doc, $issue));

        $this->addDates($doc, $issueNode, $issue);
        $this->addSections($doc, $issueNode, $issue);

        import('plugins.importexport.native.filter.NativeFilterHelper');
        $nativeFilterHelper = new NativeFilterHelper();
        $coversNode = $nativeFilterHelper->createIssueCoversNode($this, $doc, $issue);
        if ($coversNode) {
            $issueNode->appendChild($coversNode);
        }

        $this->addIssueGalleys($doc, $issueNode, $issue);
        $this->addArticles($doc, $issueNode, $issue);

        return $issueNode;
    }

    public function addArticles($doc, $issueNode, $issue)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $nativeExportFilters = $filterDao->getObjectsByGroup('extended-article=>native-xml');
        assert(count($nativeExportFilters) == 1);
        $exportFilter = array_shift($nativeExportFilters);
        $exportFilter->setOpts($this->opts);
        $exportFilter->setDeployment($this->getDeployment());
        $exportFilter->setIncludeSubmissionsNode(true);

        $submissionsArray = [];
        $submissions = Services::get('submission')->getMany([
            'contextId' => $issue->getJournalId(),
            'issueIds' => $issue->getId(),
        ]);

        foreach ($submissions as $submission) {
            if ($this->getDeployment()->validateSubmission($submission)) {
                $submissionsArray[] = $submission;
            }
        }

        $articlesDoc = $exportFilter->execute($submissionsArray);
        if ($articlesDoc->documentElement instanceof DOMElement) {
            $clone = $doc->importNode($articlesDoc->documentElement, true);
            $issueNode->appendChild($clone);
        }
    }
}
