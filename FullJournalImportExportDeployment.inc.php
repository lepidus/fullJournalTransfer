<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('plugins.importexport.native.NativeImportExportDeployment');

class FullJournalImportExportDeployment extends NativeImportExportDeployment
{
    public $isTestEnv;
    private $reviewForm;
    private $reviewRound;
    private $reviewAssignment;
    private $note;
    private $currentIssue;
    private $navigationMenuItemDBIds;
    private $reviewFormDBIds;
    private $reviewFormElementDBIds;
    private $representationDBIds;
    private $issueDBIds;
    private $issueGalleyDBIds;
    private $submissionDBIds;

    public function __construct($context, $user = null)
    {
        parent::__construct($context, $user);
        $this->setNavigationMenuItemDBIds([]);
        $this->setReviewFormDBIds([]);
        $this->setReviewFormElementDBIds([]);
        $this->setRepresentationDBIds([]);
        $this->setIssueDBIds([]);
        $this->setIssueGalleyDBIds([]);
        $this->setSubmissionDBIds([]);
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

            if (empty($titles)) {
                return false;
            }

            if (empty($authors)) {
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

    public function setReviewAssignment($reviewAssignment)
    {
        $this->reviewAssignment = $reviewAssignment;
    }

    public function getReviewAssignment()
    {
        return $this->reviewAssignment;
    }

    public function setNote($note)
    {
        $this->note = $note;
    }

    public function getNote()
    {
        return $this->note;
    }

    public function setCurrentIssue($currentIssue)
    {
        $this->currentIssue = $currentIssue;
    }

    public function getCurrentIssue()
    {
        return $this->currentIssue;
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

    public function getReviewFormDBIds()
    {
        return $this->reviewFormDBIds;
    }

    public function setReviewFormDBIds($reviewFormDBIds)
    {
        return $this->reviewFormDBIds = $reviewFormDBIds;
    }

    public function getReviewFormDBId($reviewFormDBId)
    {
        if (array_key_exists($reviewFormDBId, $this->reviewFormDBIds)) {
            return $this->reviewFormDBIds[$reviewFormDBId];
        }
        return null;
    }

    public function setReviewFormDBId($reviewFormDBId, $DBId)
    {
        return $this->reviewFormDBIds[$reviewFormDBId] = $DBId;
    }

    public function getReviewFormElementDBIds()
    {
        return $this->reviewFormElementDBIds;
    }

    public function setReviewFormElementDBIds($reviewFormElementDBIds)
    {
        return $this->reviewFormElementDBIds = $reviewFormElementDBIds;
    }

    public function getReviewFormElementDBId($reviewFormElementDBId)
    {
        if (array_key_exists($reviewFormElementDBId, $this->reviewFormElementDBIds)) {
            return $this->reviewFormElementDBIds[$reviewFormElementDBId];
        }
        return null;
    }

    public function setReviewFormElementDBId($reviewFormElementDBId, $DBId)
    {
        return $this->reviewFormElementDBIds[$reviewFormElementDBId] = $DBId;
    }

    public function getRepresentationDBIds()
    {
        return $this->representationDBIds;
    }

    public function setRepresentationDBIds($representationDBIds)
    {
        return $this->representationDBIds = $representationDBIds;
    }

    public function getRepresentationDBId($representationDBId)
    {
        if (array_key_exists($representationDBId, $this->representationDBIds)) {
            return $this->representationDBIds[$representationDBId];
        }
        return null;
    }

    public function setRepresentationDBId($representationDBId, $DBId)
    {
        return $this->representationDBIds[$representationDBId] = $DBId;
    }

    public function getIssueDBIds()
    {
        return $this->issueDBIds;
    }

    public function setIssueDBIds($issueDBIds)
    {
        return $this->issueDBIds = $issueDBIds;
    }

    public function getIssueDBId($issueDBId)
    {
        if (array_key_exists($issueDBId, $this->issueDBIds)) {
            return $this->issueDBIds[$issueDBId];
        }
        return null;
    }

    public function setIssueDBId($issueDBId, $DBId)
    {
        return $this->issueDBIds[$issueDBId] = $DBId;
    }

    public function getIssueGalleyDBIds()
    {
        return $this->issueGalleyDBIds;
    }

    public function setIssueGalleyDBIds($issueGalleyDBIds)
    {
        return $this->issueGalleyDBIds = $issueGalleyDBIds;
    }

    public function getIssueGalleyDBId($issueGalleyDBId)
    {
        if (array_key_exists($issueGalleyDBId, $this->issueGalleyDBIds)) {
            return $this->issueGalleyDBIds[$issueGalleyDBId];
        }
        return null;
    }

    public function setIssueGalleyDBId($issueGalleyDBId, $DBId)
    {
        return $this->issueGalleyDBIds[$issueGalleyDBId] = $DBId;
    }

    public function getSubmissionDBIds()
    {
        return $this->submissionDBIds;
    }

    public function setSubmissionDBIds($submissionDBIds)
    {
        return $this->submissionDBIds = $submissionDBIds;
    }

    public function getSubmissionDBId($submissionDBId)
    {
        if (array_key_exists($submissionDBId, $this->submissionDBIds)) {
            return $this->submissionDBIds[$submissionDBId];
        }
        return null;
    }

    public function setSubmissionDBId($submissionDBId, $DBId)
    {
        return $this->submissionDBIds[$submissionDBId] = $DBId;
    }
}
