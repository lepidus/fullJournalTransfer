<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('plugins.importexport.native.NativeImportExportDeployment');

class FullJournalImportExportDeployment extends NativeImportExportDeployment
{
    private $reviewForm;
    private $navigationMenuItemDBIds;
    private $reviewRound;

    public function __construct($context, $user = null)
    {
        parent::__construct($context, $user);
        $this->setNavigationMenuItemDBIds([]);
    }

    public function getSchemaFilename()
    {
        return 'fullJournal.xsd';
    }

    public function getSubmissionNodeName()
    {
        return 'extended_article';
    }

    public function getSubmissionsNodeName()
    {
        return 'extended_articles';
    }

    public function validateSubmission($submission)
    {
        $isComplete = 0;
        if ($submission->getSubmissionProgress() != $isComplete) {
            return false;
        }

        $publications = $submission->getData('publications');
        if (empty($publications)) {
            return false;
        }
        foreach ($publications as $publication) {
            $titles = $publication->getData('title');
            $authors = $publication->getData('authors');

            if (empty($titles) || empty($authors)) {
                return false;
            }

            foreach ($authors as $author) {
                $givenNames = $author->getGivenName(null);
                $userGroup = $author->getUserGroup();

                if (is_null($givenNames)) {
                    return false;
                }

                if (!$userGroup) {
                    return false;
                }
            }
        }

        return true;
    }

    public function setReviewRound($reviewRound)
    {
        $this->reviewRound = $reviewRound;
    }

    public function getReviewRound()
    {
        return $this->reviewRound;
    }

    public function setReviewForm($reviewForm)
    {
        $this->reviewForm = $reviewForm;
    }

    public function getReviewForm()
    {
        return $this->reviewForm;
    }

    public function getNavigationMenuItemDBIds()
    {
        return $this->navigationMenuItemDBIds;
    }

    public function setNavigationMenuItemDBIds($navigationMenuItemDBIds)
    {
        return $this->navigationMenuItemDBIds = $navigationMenuItemDBIds;
    }

    public function getNavigationMenuItemDBId($navigationMenuItemDBId)
    {
        if (array_key_exists($navigationMenuItemDBId, $this->navigationMenuItemDBIds)) {
            return $this->navigationMenuItemDBIds[$navigationMenuItemDBId];
        }
        return null;
    }

    public function setNavigationMenuItemDBId($navigationMenuItemDBId, $DBId)
    {
        return $this->navigationMenuItemDBIds[$navigationMenuItemDBId] = $DBId;
    }
}
