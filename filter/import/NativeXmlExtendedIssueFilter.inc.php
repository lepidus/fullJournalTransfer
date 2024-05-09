<?php

import('plugins.importexport.native.filter.NativeXmlIssueFilter');

class NativeXmlExtendedIssueFilter extends NativeXmlIssueFilter
{
    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlExtendedIssueFilter';
    }

    public function getPluralElementName()
    {
        return 'extended_issues';
    }

    public function getSingularElementName()
    {
        return 'extended_issue';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $journal = $deployment->getContext();
        $issue = parent::handleElement($node);

        if ($issue) {
            for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
                if (
                    is_a($childNode, 'DOMElement')
                    && $childNode->tagName == 'id'
                    && $childNode->getAttribute('type') == 'internal'
                ) {
                    $deployment->setIssueDBId($childNode->textContent, $issue->getId());
                }
            }

            if ($issue->getCurrent()) {
                $deployment->setCurrentIssue($issue);
            }

            if ($seq = $node->getAttribute('order')) {
                $issueDao = DAORegistry::getDAO('IssueDAO');
                $issueDao->moveCustomIssueOrder($journal->getId(), $issue->getId(), $seq);
            }
        }

        return $issue;
    }

    public function handleChildElement($node, $issue, $processOnlyChildren)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $localizedSetterMappings = $this->_getLocalizedIssueSetterMappings();
        $dateSetterMappings = $this->_getDateIssueSetterMappings();

        if (isset($localizedSetterMappings[$node->tagName])) {
            if (!$processOnlyChildren) {
                $setterFunction = $localizedSetterMappings[$node->tagName];
                list($locale, $value) = $this->parseLocalizedContent($node);
                if (empty($locale)) {
                    $locale = $context->getPrimaryLocale();
                }
                $issue->$setterFunction($value, $locale);
            }
        } elseif (isset($dateSetterMappings[$node->tagName])) {
            if (!$processOnlyChildren) {
                $setterFunction = $dateSetterMappings[$node->tagName];
                $issue->$setterFunction(strtotime($node->textContent));
            }
        } else {
            switch ($node->tagName) {
                case 'id':
                    if (!$processOnlyChildren) {
                        $this->parseIdentifier($node, $issue);
                    }
                    break;
                case 'extended_articles':
                    $this->parseArticles($node, $issue);
                    break;
                case 'issue_galleys':
                    if (!$processOnlyChildren) {
                        $this->parseIssueGalleys($node, $issue);
                    }
                    break;
                case 'sections':
                    $this->parseSections($node, $issue);
                    break;
                case 'covers':
                    if (!$processOnlyChildren) {
                        import('plugins.importexport.native.filter.NativeFilterHelper');
                        $nativeFilterHelper = new NativeFilterHelper();
                        $nativeFilterHelper->parseIssueCovers($this, $node, $issue, ASSOC_TYPE_ISSUE);
                    }
                    break;
                case 'issue_identification':
                    if (!$processOnlyChildren) {
                        $this->parseIssueIdentification($node, $issue);
                    }
                    break;
                default:
                    $deployment->addWarning(ASSOC_TYPE_ISSUE, $issue->getId(), __('plugins.importexport.common.error.unknownElement', array('param' => $n->tagName)));
            }
        }
    }

    public function parseArticles($node, $issue)
    {
        $deployment = $this->getDeployment();
        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement')) {
                switch ($n->tagName) {
                    case 'extended_article':
                        $this->parseArticle($n, $issue);
                        break;
                    default:
                        $deployment->addWarning(ASSOC_TYPE_ISSUE, $issue->getId(), __('plugins.importexport.common.error.unknownElement', array('param' => $n->tagName)));
                }
            }
        }
    }

    public function parseArticle($node, $issue)
    {
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $importFilters = $filterDao->getObjectsByGroup('native-xml=>extended-article');
        assert(count($importFilters) == 1);
        $importFilter = array_shift($importFilters);
        $importFilter->setDeployment($this->getDeployment());
        $articleDoc = new DOMDocument('1.0', 'utf-8');
        $articleDoc->appendChild($articleDoc->importNode($node, true));
        return $importFilter->execute($articleDoc);
    }

    public function parseIssueGalley($n, $issue)
    {
        $deployment = $this->getDeployment();
        $importedObjects = parent::parseIssueGalley($n, $issue);

        $issueGalley = array_shift($importedObjects);
        if (is_a($issueGalley, 'IssueGalley')) {
            for ($childNode = $n->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
                if (
                    is_a($childNode, 'DOMElement')
                    && $childNode->tagName == 'id'
                    && $childNode->getAttribute('type') == 'internal'
                ) {
                    $deployment->setIssueGalleyDBId($childNode->textContent, $issueGalley->getId());
                }
            }
        }

        return $importedObjects;
    }
}
