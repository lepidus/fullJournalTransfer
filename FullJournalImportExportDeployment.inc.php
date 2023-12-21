<?php

/**
 * Copyright (c) 2014-2023 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

import('plugins.importexport.native.NativeImportExportDeployment');

class FullJournalImportExportDeployment extends NativeImportExportDeployment
{
    private $reviewForm;
    private $navigationMenuItemDBIds;
    private $reviewRoundDBIds;

    public function __construct($context, $user = null)
    {
        parent::__construct($context, $user);
        $this->setNavigationMenuItemDBIds([]);
        $this->setReviewRoundDBIds([]);
    }

    public function getSchemaFilename()
    {
        return 'fullJournal.xsd';
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

    public function getReviewRoundDBIds()
    {
        return $this->reviewRoundDBIds;
    }

    public function setReviewRoundDBIds($reviewRoundDBIds)
    {
        return $this->reviewRoundDBIds = $reviewRoundDBIds;
    }

    public function getReviewRoundDBId($reviewRoundDBId)
    {
        if (array_key_exists($reviewRoundDBId, $this->reviewRoundDBIds)) {
            return $this->reviewRoundDBIds[$reviewRoundDBId];
        }
        return null;
    }

    public function setReviewRoundDBId($reviewRoundDBId, $DBId)
    {
        return $this->reviewRoundDBIds[$reviewRoundDBId] = $DBId;
    }
}
