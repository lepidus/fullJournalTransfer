<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\support;

use PKP\facades\Locale;

trait LoadsPluginLocale
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Direct class tests do not register the plugin through PluginRegistry.
        Locale::registerPath(dirname(__DIR__, 2) . '/locale');
    }
}
