<?php

/**
* @file plugins/importexport/fullJournalTransfer/filter/import/NativeXmlUserFilter.inc.php
*
* Copyright (c) 2014-2021 Simon Fraser University
* Copyright (c) 2000-2021 John Willinsky
* Copyright (c) 2014-2024 Lepidus Tecnologia
* Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
*
* @class NativeXmlUserFilter
* @ingroup plugins_importexport_fullJournalTransfer
*
* @brief Class that converts a Native XML document to an user.
*/

import('lib.pkp.plugins.importexport.users.filter.UserXmlPKPUserFilter');

class NativeXmlUserFilter extends UserXmlPKPUserFilter
{
    public function __construct($filterGroup)
    {
        $this->setDisplayName('Native XML user import');
        parent::__construct($filterGroup);
    }

    public function getClassName()
    {
        return 'plugins.importexport.fullJournalTransfer.filter.import.NativeXmlUserFilter';
    }

    public function parseUser($node)
    {
        $user = parent::parseUser($node);
        $deployment = $this->getDeployment();
        $context = $deployment->getContext();

        $userDao = DAORegistry::getDAO('UserDAO');
        $userByEmail = $userDao->getUserByEmail($user->getEmail(), true);

        if ($userByEmail) {
            $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
            $userGroupsFactory = $userGroupDao->getByContextId($context->getId());
            $userGroups = $userGroupsFactory->toArray();

            $userGroupNodeList = $node->getElementsByTagNameNS($deployment->getNamespace(), 'user_group_ref');
            if ($userGroupNodeList->length > 0) {
                for ($i = 0; $i < $userGroupNodeList->length; $i++) {
                    $n = $userGroupNodeList->item($i);
                    foreach ($userGroups as $userGroup) {
                        if (in_array($n->textContent, $userGroup->getName(null))) {
                            $userGroupDao->assignUserToGroup($userByEmail->getId(), $userGroup->getId());
                        }
                    }
                }
            }
        }
        return $user;
    }

    public function importUserPasswordValidation($userToImport, $encryption)
    {
        $password = parent::importUserPasswordValidation($userToImport, $encryption);

        $this->generateUsername($userToImport);

        return $password;
    }

    public function generateUsername($user)
    {
        $userDAO = DAORegistry::getDAO('UserDAO');
        $baseUsername = preg_replace('/[^A-Z0-9]/i', '', $user->getUsername());
        for ($username = $baseUsername, $i = 1; $userDAO->userExistsByUsername($username); $i++) {
            $username = $baseUsername . $i;
        }

        $user->setUsername($username);
    }
}
