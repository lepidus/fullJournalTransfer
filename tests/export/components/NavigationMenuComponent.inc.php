<?php

trait NavigationMenuComponent
{
    public function getNavigationMenuId()
    {
        $navigationMenuDAO = DAORegistry::getDAO('NavigationMenuDAO');
        $navigationMenu = $navigationMenuDAO->newDataObject();
        $navigationMenu->setTitle('Test Menu');
        $navigationMenu->setAreaName('primary_user');
        return [$navigationMenuDAO->insertObject($navigationMenu), $navigationMenu];
    }

    public function getChildNavigationMenuItemId()
    {
        $navigationMenuItemDAO = DAORegistry::getDAO('NavigationMenuItemDAO');
        $navigationMenuItem = $navigationMenuItemDAO->newDataObject();
        $navigationMenuItem->setType(NMI_TYPE_CUSTOM);
        $navigationMenuItem->setPath('childItem');
        $navigationMenuItem->setTitle('Child Item', 'en_US');

        return $navigationMenuItemDAO->insertObject($navigationMenuItem);
    }

    public function getParentNavigationMenuItemId()
    {
        $navigationMenuItemDAO = DAORegistry::getDAO('NavigationMenuItemDAO');
        $navigationMenuItem = $navigationMenuItemDAO->newDataObject();
        $navigationMenuItem->setPath('parentItem');
        $navigationMenuItem->setTitle('Parent Item', 'en_US');
        return $navigationMenuItemDAO->insertObject($navigationMenuItem);
    }

    public function insertNavigationMenuItemAssignment($navigationMenuId, $childMenuItemId, $parentMenuItemId)
    {
        $navigationMenuItemAssignmentDAO = DAORegistry::getDAO('NavigationMenuItemAssignmentDAO');
        $assignment = $navigationMenuItemAssignmentDAO->newDataObject();
        $assignment->setMenuId($navigationMenuId);
        $assignment->setMenuItemId($childMenuItemId);
        $assignment->setParentId($parentMenuItemId);
        $assignment->setSequence(2);
        $navigationMenuItemAssignmentDAO->insertObject($assignment);
    }
}
