<?php

/**
 * Copyright (c) 2014-2024 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('lib.pkp.plugins.importexport.native.filter.NativeImportFilter');

class NativeXmlReviewAssignmentFilter extends NativeImportFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML review assignment import');
        parent::__construct($filterGroup);
    }

    public function getPluralElementName()
    {
        return 'review_assignments';
    }

    public function getSingularElementName()
    {
        return 'review_assignment';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlReviewAssignmentFilter';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $reviewAssignmentDAO = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignment = $reviewAssignmentDAO->newDataObject();

        $userDAO = DAORegistry::getDAO('UserDAO');
        $reviewer = $userDAO->getByUsername($node->getAttribute('reviewer'));

        $reviewAssignment->setReviewerId($reviewer->getId());
        $reviewAssignment->setSubmissionId($deployment->getSubmissionDBId($node->getAttribute('submission_id')));
        $reviewAssignment->setReviewRoundId($deployment->getReviewRoundDBId($node->getAttribute('review_round_id')));
        $reviewAssignment->setStageId($node->getAttribute('stage_id'));
        $reviewAssignment->setRecommendation($node->getAttribute('recommendation') ?: null);
        $reviewAssignment->setQuality($node->getAttribute('quality') ?: null);
        $reviewAssignment->setRound($node->getAttribute('round'));
        $reviewAssignment->setReviewMethod($node->getAttribute('review_method'));
        $reviewAssignment->setCompetingInterests($node->getAttribute('competing_interests') ?: null);

        $tagMappings = [
            'date_rated' => 'setDateRated',
            'date_reminded' => 'setDateReminded',
            'date_assigned' => 'setDateAssigned',
            'date_notified' => 'setDateNotified',
            'date_confirmed' => 'setDateConfirmed',
            'date_completed' => 'setDateCompleted',
            'date_acknowledged' => 'setDateAcknowledged',
            'date_due' => 'setDateDue',
            'date_response_due' => 'setDateResponseDue',
            'last_modified' => 'setLastModified',
            'declined' => 'setDeclined',
            'cancelled' => 'setCancelled',
            'reminder_was_automatic' => 'setReminderWasAutomatic',
            'unconsidered' => 'setUnconsidered',
        ];

        for ($n = $node->firstChild; $n !== null; $n = $n->nextSibling) {
            if (is_a($n, 'DOMElement') && isset($tagMappings[$n->tagName])) {
                $methodName = $tagMappings[$n->tagName];
                $textContent = $n->textContent;

                if (
                    $methodName === 'setDeclined'
                    || $methodName === 'setCancelled'
                    || $methodName === 'setReminderWasAutomatic'
                    || $methodName === 'setUnconsidered'
                ) {
                    $reviewAssignment->$methodName($textContent === 'true');
                } else {
                    $reviewAssignment->$methodName($textContent);
                }
            }
        }

        $reviewAssignmentId = $reviewAssignmentDAO->insertObject($reviewAssignment);

        return $reviewAssignment;
    }
}
