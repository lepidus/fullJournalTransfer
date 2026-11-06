<?php

/**
 * Copyright (c) 2026 Lepidus Tecnologia
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 */

/**
 * Accepts only file references that point to a regular file inside the extracted package.
 *
 * The native import filter reads an href "src" as a URL, an absolute path or a path relative
 * to the import path, so every href must be checked before the journal XML is imported.
 */
class PackageFileReferenceValidator
{
    /**
     * Stream the journal XML and return the first href that does not resolve inside the package.
     *
     * @param string $xmlPath
     * @param string $packagePath
     *
     * @return array|null ['source' => string, 'line' => int] or null when every href is safe
     */
    public function findUnsafeReference($xmlPath, $packagePath)
    {
        $packageRoot = $packagePath === '' ? false : realpath($packagePath);
        if ($packageRoot === false || !is_dir($packageRoot)) {
            throw new RuntimeException('Could not resolve the package directory.');
        }

        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $reader = new XMLReader();
        try {
            if (!is_string($xmlPath) || !is_file($xmlPath) || !$reader->open($xmlPath, null, LIBXML_NONET)) {
                throw new RuntimeException('Could not read the journal XML.');
            }
            while ($reader->read()) {
                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'href') {
                    continue;
                }
                $source = (string) $reader->getAttribute('src');
                if (!$this->resolvesInsidePackage($source, $packageRoot)) {
                    $node = $reader->expand();
                    return [
                        'source' => $source,
                        'line' => $node ? $node->getLineNo() : 0,
                    ];
                }
            }
            foreach (libxml_get_errors() as $error) {
                if ($error->level === LIBXML_ERR_ERROR || $error->level === LIBXML_ERR_FATAL) {
                    throw new RuntimeException('Could not parse the journal XML.');
                }
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }

        return null;
    }

    /**
     * @param string $source
     * @param string $packageRoot Resolved package directory
     *
     * @return bool
     */
    public function resolvesInsidePackage($source, $packageRoot)
    {
        $segments = preg_split('~[/\\\\]+~', $source);
        if ($source === ''
            || $source[0] === '/'
            || preg_match('~^[a-z][a-z0-9+.-]*:~i', $source)
            || preg_match('~\w+://~', $source)
            || in_array('..', $segments, true)
            || in_array('.', $segments, true)
            || strpos($source, "\0") !== false
        ) {
            return false;
        }

        $resolvedPath = realpath($packageRoot . DIRECTORY_SEPARATOR . $source);
        $packagePrefix = rtrim($packageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return $resolvedPath !== false
            && strpos($resolvedPath, $packagePrefix) === 0
            && is_file($resolvedPath);
    }
}
