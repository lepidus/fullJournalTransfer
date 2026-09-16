<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\theme;

use InvalidArgumentException;
use PKP\plugins\PluginRegistry;
use PKP\plugins\ThemePlugin;

class ThemeSettingsTransfer
{
    private const DEFAULT_THEME_PATH = 'default';

    public function findInstalledTheme(string $pluginPath): ThemePlugin
    {
        $theme = $this->findTheme($pluginPath);
        if ($theme) {
            return $theme;
        }
        throw new InvalidArgumentException(__(
            'plugins.importexport.fullJournal.error.selectedThemeNotInstalled',
            [
                'pluginPath' => $pluginPath,
            ]
        ));
    }

    public function findInstalledThemeOrDefault(string $pluginPath): ThemePlugin
    {
        $theme = $this->findTheme($pluginPath);
        return $theme ?? $this->findInstalledTheme(self::DEFAULT_THEME_PATH);
    }

    private function findTheme(string $pluginPath): ?ThemePlugin
    {
        if (preg_match('/^[a-zA-Z0-9]+(?:[-_][a-zA-Z0-9]+)*$/', $pluginPath) !== 1) {
            throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.selectedThemePathInvalid'));
        }
        foreach (PluginRegistry::loadCategory('themes', false) as $theme) {
            if ($theme instanceof ThemePlugin && $theme->getDirName() === $pluginPath) {
                return $theme;
            }
        }
        return null;
    }
}
