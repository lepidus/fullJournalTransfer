<?php

/**
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

/**
 * Inspects and removes the directory where an import package was extracted, never following links.
 *
 * GNU tar keeps the package members inside the extraction directory, but still creates the
 * symbolic links stored in the package. FileManager::rmtree() follows a link to a directory and
 * deletes the files of its target, so links are refused and the directory is removed without
 * following them.
 */
class ExtractedPackageDirectory
{
    /**
     * Return the first entry that is not a regular file or a directory.
     *
     * @param string $path Extraction directory
     *
     * @return string|null Path relative to the package, or null when every entry is supported
     */
    public function findUnsupportedEntry($path)
    {
        return $this->findUnsupportedEntryIn($path, '');
    }

    /**
     * Remove the directory and its contents, deleting links instead of following them.
     *
     * @param string $path
     *
     * @return bool
     */
    public function remove($path)
    {
        if (is_link($path) || !is_dir($path)) {
            return !file_exists($path) && !is_link($path) ? true : unlink($path);
        }

        // Package directories may come without permissions for their owner.
        @chmod($path, 0700);
        $entries = $this->listEntries($path);
        if ($entries === null) {
            return false;
        }
        $removed = true;
        foreach ($entries as $entry) {
            $removed = $this->remove($path . DIRECTORY_SEPARATOR . $entry) && $removed;
        }

        return rmdir($path) && $removed;
    }

    private function findUnsupportedEntryIn($path, $relativePath)
    {
        $entries = $this->listEntries($path);
        if ($entries === null) {
            return $relativePath === '' ? '.' : $relativePath;
        }
        foreach ($entries as $entry) {
            $entryPath = $path . DIRECTORY_SEPARATOR . $entry;
            $relativeEntry = $relativePath === '' ? $entry : $relativePath . '/' . $entry;
            if (is_link($entryPath)) {
                return $relativeEntry;
            }
            if (is_dir($entryPath)) {
                $unsupportedEntry = $this->findUnsupportedEntryIn($entryPath, $relativeEntry);
                if ($unsupportedEntry !== null) {
                    return $unsupportedEntry;
                }
                continue;
            }
            if (!is_file($entryPath)) {
                return $relativeEntry;
            }
        }

        return null;
    }

    /**
     * @return string[]|null
     */
    private function listEntries($path)
    {
        $entries = @scandir($path);
        if ($entries === false) {
            return null;
        }

        return array_values(array_diff($entries, ['.', '..']));
    }
}
