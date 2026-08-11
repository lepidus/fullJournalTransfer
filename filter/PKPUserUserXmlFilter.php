<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\filter;

use APP\facades\Repo;
use DOMElement;
use PKP\plugins\importexport\PKPImportExportFilter;
use PKP\plugins\importexport\users\filter\PKPUserUserXmlFilter as BasePKPUserUserXmlFilter;

class PKPUserUserXmlFilter extends BasePKPUserUserXmlFilter
{
    public function createPKPUserNode($doc, $user)
    {
        $node = parent::createPKPUserNode($doc, $user);
        $node->setAttribute('source_ref', (string) $user->getId());
        $references = [];
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'user_group_ref') {
                $references[] = $child;
            }
        }
        foreach ($references as $reference) {
            $node->removeChild($reference);
        }
        $context = $this->getDeployment()->getContext();
        $userGroups = Repo::userGroup()->getCollector()
            ->filterByUserIds([$user->getId()])
            ->filterByContextIds([$context->getId()])
            ->getMany();
        foreach ($userGroups as $userGroup) {
            $reference = $doc->createElementNS(
                $this->getDeployment()->getNamespace(),
                'user_group_ref',
                $userGroup->getName($context->getPrimaryLocale())
            );
            $reference->setAttribute('source_ref', (string) $userGroup->getId());
            $node->appendChild($reference);
        }
        return $node;
    }

    public function addUserGroups($doc, $rootNode)
    {
        $context = $this->getDeployment()->getContext();
        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany()
            ->toArray();
        $filter = PKPImportExportFilter::getFilter('user-group=>full-journal-user-xml', $this->getDeployment());
        $document = $filter->execute($userGroups);
        $rootNode->appendChild($doc->importNode($document->documentElement, true));
    }
}
