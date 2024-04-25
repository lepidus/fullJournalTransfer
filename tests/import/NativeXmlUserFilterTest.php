<?php

import('plugins.importexport.fullJournalTransfer.filter.import.NativeXmlUserFilter');
import('plugins.importexport.fullJournalTransfer.tests.NativeImportExportFilterTestCase');

class NativeXmlUserFilterTest extends NativeImportExportFilterTestCase
{
    protected function getSymbolicFilterGroup()
    {
        return 'native-xml=>user';
    }

    protected function getNativeImportExportFilterClass()
    {
        return NativeXmlUserFilter::class;
    }

    protected function getMockedDAOs()
    {
        return ['UserDAO'];
    }

    public function testGenerateUsername()
    {
        $userImportFilter = $this->getNativeImportExportFilter();

        $mockUserDAO = $this->getMockBuilder(UserDAO::class)
            ->setMethods(['userExistsByUsername'])
            ->getMock();
        $mockUserDAO->expects($this->any())
            ->method('userExistsByUsername')
            ->will($this->onConsecutiveCalls(true, false));

        DAORegistry::registerDAO('UserDAO', $mockUserDAO);

        $user = new User();
        $user->setUsername('reviewer');

        $userImportFilter->generateUsername($user);

        $this->assertEquals('reviewer1', $user->getUsername());
    }
}
