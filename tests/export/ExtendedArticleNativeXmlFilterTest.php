<?php

import('classes.submission.Submission');
import('classes.workflow.EditorDecisionActionsManager');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');
import('plugins.importexport.fullJournalTransfer.filter.export.ExtendedArticleNativeXmlFilter');

class ExtendedArticleNativeXmlFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'extended-article=>native-xml';
    }

    protected function getNativeImportExportFilterClass()
    {
        return ExtendedArticleNativeXmlFilter::class;
    }

    protected function getMockedDAOs()
    {
        return ['UserDAO', 'ReviewRoundDAO', 'EditDecisionDAO'];
    }

    private function registerMockReviewRoundDAO()
    {
        $mockDAO = $this->getMockBuilder(ReviewRoundDAO::class)
            ->setMethods(['getBySubmissionId'])
            ->getMock();

        $reviewRound = $mockDAO->newDataObject();
        $reviewRound->setId(563);
        $reviewRound->setSubmissionId(16);
        $reviewRound->setStageId(3);
        $reviewRound->setRound(1);
        $reviewRound->setStatus(1);

        $mockResult = $this->getMockBuilder(DAOResultFactory::class)
            ->setMethods(['toArray'])
            ->disableOriginalConstructor()
            ->getMock();

        $mockResult->expects($this->any())
            ->method('toArray')
            ->will($this->returnValue([$reviewRound]));

        $mockDAO->expects($this->any())
            ->method('getBySubmissionId')
            ->will($this->returnValue($mockResult));

        DAORegistry::registerDAO('ReviewRoundDAO', $mockDAO);
    }

    private function registerMockEditorDecisionDAO()
    {
        $mockDAO = $this->getMockBuilder(EditDecisionDAO::class)
            ->setMethods(['getEditorDecisions'])
            ->getMock();

        $editorDecision = [
            'editDecisionId' => 123,
            'reviewRoundId' => 0,
            'stageId' => 1,
            'round' => 0,
            'editorId' => 784,
            'decision' => 8,
            'dateDecided' => '2015-03-04 13:39:11'
        ];

        $mockDAO->expects($this->any())
            ->method('getEditorDecisions')
            ->will($this->returnValue([$editorDecision]));

        DAORegistry::registerDAO('EditDecisionDAO', $mockDAO);
    }

    private function registerMockUserDAO()
    {
        $mockDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['getById'])
            ->getMock();

        $user = $mockDAO->newDataObject();
        $user->setUsername('journaleditor');

        $mockDAO->expects($this->any())
            ->method('getById')
            ->will($this->returnValue($user));

        DAORegistry::registerDAO('UserDAO', $mockDAO);
    }

    public function createRootNode($doc, $deployment, $tagName)
    {
        $rootNode = $doc->createElementNS($deployment->getNamespace(), $tagName);
        $rootNode->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $rootNode->setAttribute(
            'xsi:schemaLocation',
            $deployment->getNamespace() . ' ' . $deployment->getSchemaFilename()
        );

        return $rootNode;
    }

    public function createEditorDecisionsNode($doc, $deployment)
    {
        $editorDecisionsNode = $doc->createElementNS($deployment->getNamespace(), 'editor_decisions');
        $editorDecisionsNode->appendChild($editorDecisionNode = $doc->createElementNS(
            $deployment->getNamespace(),
            'editor_decision'
        ));

        $editorDecisionNode->setAttribute('submission_id', 143);
        $editorDecisionNode->setAttribute('round', 0);
        $editorDecisionNode->setAttribute('review_round_id', 0);
        $editorDecisionNode->setAttribute('stage_id', 1);
        $editorDecisionNode->setAttribute('decision', SUBMISSION_EDITOR_DECISION_EXTERNAL_REVIEW);

        $editorDecisionNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'editor',
            htmlspecialchars('journaleditor', ENT_COMPAT, 'UTF-8')
        ));
        $editorDecisionNode->appendChild($node = $doc->createElementNS(
            $deployment->getNamespace(),
            'date_decided',
            strftime('%Y-%m-%d %H:%M:%S', strtotime('2015-03-04 13:39:11'))
        ));

        return $editorDecisionsNode;
    }

    public function testAddReviewRounds()
    {
        $extendedArticleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $extendedArticleExportFilter->getDeployment();

        $reviewRoundNativeXmlFilterTest = new ReviewRoundNativeXmlFilterTest();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $expectedSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');

        $expectedSubmissionNode->appendChild($reviewRoundsNode = $this->createRootNode(
            $doc,
            $deployment,
            'review_rounds'
        ));
        $reviewRoundsNode->appendChild(
            $reviewRoundNode = $reviewRoundNativeXmlFilterTest->createReviewRoundNode($doc, $deployment)
        );
        $reviewRoundNode->appendChild($this->createRootNode($doc, $deployment, 'review_assignments'));

        $actualSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $submission = new Submission();
        $submission->setId(143);
        $this->registerMockReviewRoundDAO();

        $extendedArticleExportFilter->addReviewRounds($doc, $actualSubmissionNode, $submission);

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedSubmissionNode),
            $doc->saveXML($actualSubmissionNode),
            "actual xml is equal to expected xml"
        );
    }

    public function testAddEditorDecisions()
    {
        $extendedArticleExportFilter = $this->getNativeImportExportFilter();
        $deployment = $extendedArticleExportFilter->getDeployment();

        $doc = new DOMDocument('1.0');
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;

        $submission = new Submission();
        $submission->setId(143);

        $this->registerMockEditorDecisionDAO();
        $this->registerMockUserDAO();

        $expectedSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $expectedSubmissionNode->appendChild($this->createEditorDecisionsNode($doc, $deployment));

        $actualSubmissionNode = $doc->createElementNS($deployment->getNamespace(), 'extended_article');
        $extendedArticleExportFilter->addEditorDecisions(
            $doc,
            $actualSubmissionNode,
            $submission
        );

        $this->assertXmlStringEqualsXmlString(
            $doc->saveXML($expectedSubmissionNode),
            $doc->saveXML($actualSubmissionNode),
            "actual xml is equal to expected xml"
        );
    }
}
