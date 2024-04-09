<?php

/**
* @file plugins/importexport/fullJournalTransfer/filter/import/NativeXmlReviewFileFilter.inc.php
*
* Copyright (c) 2014-2021 Simon Fraser University
* Copyright (c) 2000-2021 John Willinsky
* Copyright (c) 2014-2024 Lepidus Tecnologia
* Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
*
* @class NativeXmlReviewFileFilter
* @ingroup plugins_importexport_fullJournalTransfer
*
* @brief Class that converts a Native XML document to an review file.
*/

import('plugins.importexport.native.filter.NativeXmlArticleFileFilter');

class NativeXmlReviewFileFilter extends NativeXmlArticleFileFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML review round import');
        parent::__construct($filterGroup);
    }

    public function getPluralElementName()
    {
        return 'review_files';
    }

    public function getSingularElementName()
    {
        return 'review_file';
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlReviewFileFilter';
    }

    public function handleElement($node)
    {
        $deployment = $this->getDeployment();
        $submission = $deployment->getSubmission();
        $context = $deployment->getContext();
        $stageName = $node->getAttribute('stage');
        $submissionFileId = $node->getAttribute('id');
        $stageNameIdMapping = $deployment->getStageNameStageIdMapping();
        assert(isset($stageNameIdMapping[$stageName]));
        $stageId = $stageNameIdMapping[$stageName];
        $request = Application::get()->getRequest();
        $errorOcurred = false;

        $genreId = null;
        $genreName = $node->getAttribute('genre');
        if ($genreName) {
            if (!isset($genresByContextId[$context->getId()])) {
                $genreDao = DAORegistry::getDAO('GenreDAO');
                $genres = $genreDao->getByContextId($context->getId());
                while ($genre = $genres->next()) {
                    foreach ($genre->getName(null) as $locale => $name) {
                        $genresByContextId[$context->getId()][$name] = $genre;
                    }
                }
            }
            if (!isset($genresByContextId[$context->getId()][$genreName])) {
                $deployment->addError(
                    ASSOC_TYPE_SUBMISSION,
                    $submission->getId(),
                    __('plugins.importexport.common.error.unknownGenre', array('param' => $genreName))
                );
                $errorOcurred = true;
            } else {
                $genre = $genresByContextId[$context->getId()][$genreName];
                $genreId = $genre->getId();
            }
        }

        $uploaderUsername = $node->getAttribute('uploader');
        $uploaderUserId = null;
        if (!$uploaderUsername) {
            $user = $deployment->getUser();
        } else {
            $userDao = DAORegistry::getDAO('UserDAO');
            $user = $userDao->getByUsername($uploaderUsername);
        }
        $uploaderUserId = $user
            ? (int) $user->getId()
            : Application::get()->getRequest()->getUser()->getId();

        $submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
        $submissionFile = $submissionFileDao->newDataObject();
        $submissionFile->setData('submissionId', $submission->getId());
        $submissionFile->setData('locale', $submission->getLocale());
        $submissionFile->setData('fileStage', $stageId);
        $submissionFile->setData('createdAt', Core::getCurrentDate());
        $submissionFile->setData('updatedAt', Core::getCurrentDate());

        $dateCreated = $node->getAttribute('date_created');
        if (!empty($dateCreated)) {
            $submissionFile->setData('dateCreated', $dateCreated);
        }
        if ($language = $node->getAttribute('language')) {
            $submissionFile->setData('language', $language);
        }
        if ($caption = $node->getAttribute('caption')) {
            $submissionFile->setData('caption', $caption);
        }
        if ($copyrightOwner = $node->getAttribute('copyright_owner')) {
            $submissionFile->setData('copyrightOwner', $copyrightOwner);
        }
        if ($credit = $node->getAttribute('credit')) {
            $submissionFile->setData('credit', $credit);
        }
        if (strlen($directSalesPrice = $node->getAttribute('direct_sales_price'))) {
            $submissionFile->setData('directSalesPrice', $directSalesPrice);
        }
        if ($genreId) {
            $submissionFile->setData('genreId', $genreId);
        }
        if ($salesType = $node->getAttribute('sales_type')) {
            $submissionFile->setData('salesType', $salesType);
        }
        if ($sourceSubmissionFileId = $node->getAttribute('source_submission_file_id')) {
            $submissionFile->setData('sourceSubmissionFileId', $sourceSubmissionFileId);
        }
        if ($terms = $node->getAttribute('terms')) {
            $submissionFile->setData('terms', $terms);
        }
        if ($uploaderUserId) {
            $submissionFile->setData('uploaderUserId', $uploaderUserId);
        }
        if ($node->getAttribute('viewable') == 'true') {
            $submissionFile->setViewable(true);
        }

        $allRevisionIds = [];
        for ($childNode = $node->firstChild; $childNode !== null; $childNode = $childNode->nextSibling) {
            if (is_a($childNode, 'DOMElement')) {
                switch ($childNode->tagName) {
                    case 'creator':
                    case 'description':
                    case 'name':
                    case 'publisher':
                    case 'source':
                    case 'sponsor':
                    case 'subject':
                        list($locale, $value) = $this->parseLocalizedContent($childNode);
                        $submissionFile->setData($childNode->tagName, $value, $locale);
                        break;
                    case 'submission_file_ref':
                        if ($submissionFile->getData('fileStage') == SUBMISSION_FILE_DEPENDENT) {
                            $oldAssocId = $childNode->getAttribute('id');
                            $newAssocId = $deployment->getSubmissionFileDBId($oldAssocId);
                            if ($newAssocId) {
                                $submissionFile->setData('assocType', ASSOC_TYPE_SUBMISSION_FILE);
                                $submissionFile->setData('assocId', $newAssocId);
                            }
                        }
                        break;
                    case 'file':
                        if ($deployment->getFileDBId($childNode->getAttribute('id'))) {
                            $newFileId = $deployment->getFileDBId($childNode->getAttribute('id'));
                        } else {
                            $newFileId = $this->handleRevisionElement($childNode);
                        }
                        if ($newFileId) {
                            $allRevisionIds[] = $newFileId;
                        }
                        if ($childNode->getAttribute('id') == $node->getAttribute('file_id')) {
                            $submissionFile->setData('fileId', $newFileId);
                        }
                        unset($newFileId);
                        break;
                    default:
                        $deployment->addWarning(ASSOC_TYPE_SUBMISSION, $submission->getId(), __('plugins.importexport.common.error.unknownElement', array('param' => $node->tagName)));
                }
            }
        }

        if ($errorOcurred) {
            return null;
        }

        if (count($allRevisionIds) < 2) {
            $submissionFile = Services::get('submissionFile')->add($submissionFile, $request);
        } else {
            $currentFileId = $submissionFile->getData('fileId');
            $allRevisionIds = array_filter($allRevisionIds, function ($fileId) use ($currentFileId) {
                return $fileId !== $currentFileId;
            });
            $allRevisionIds = array_values($allRevisionIds);
            foreach ($allRevisionIds as $i => $fileId) {
                if ($i === 0) {
                    $submissionFile->setData('fileId', $fileId);
                    $submissionFile = Services::get('submissionFile')->add($submissionFile, $request);
                } else {
                    $submissionFile = Services::get('submissionFile')->edit($submissionFile, ['fileId' => $fileId], $request);
                }
            }
            $submissionFile = Services::get('submissionFile')->edit($submissionFile, ['fileId' => $currentFileId], $request);
        }

        $reviewRound = $deployment->getReviewRound();
        $submissionFileDao->assignRevisionToReviewRound($submissionFile->getId(), $reviewRound);
        $deployment->setSubmissionFileDBId($node->getAttribute('id'), $submissionFile->getId());

        return $submissionFile;
    }
}
