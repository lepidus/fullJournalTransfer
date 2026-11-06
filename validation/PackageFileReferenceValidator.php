<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\validation;

use DOMElement;
use InvalidArgumentException;

/**
 * Accepts only file references that point to a regular file inside the extracted package.
 *
 * The native import filter reads an href "src" as a URL, an absolute path or a path relative
 * to the import path, so every href must be checked before that filter receives it.
 */
class PackageFileReferenceValidator
{
    public static function isSafeRelativeSource(string $source): bool
    {
        $segments = preg_split('~[/\\\\]+~', $source);
        return $source !== ''
            && !str_starts_with($source, '/')
            && !preg_match('~^[a-z][a-z0-9+.-]*:~i', $source)
            && !preg_match('~\w+://~', $source)
            && !in_array('..', $segments, true)
            && !in_array('.', $segments, true)
            && !str_contains($source, "\0");
    }

    /**
     * @return string The resolved absolute path of the referenced package file.
     */
    public function validate(DOMElement $href, string $packagePath): string
    {
        $source = $href->getAttribute('src');
        if (!self::isSafeRelativeSource($source)) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.unsafeNativeFileReferenceLine',
                [
                    'source' => $source,
                    'line' => $href->getLineNo(),
                ]
            ));
        }
        $packageRoot = $packagePath === '' ? false : realpath($packagePath);
        $resolvedPath = $packageRoot === false ? false : realpath($packageRoot . DIRECTORY_SEPARATOR . $source);
        if ($resolvedPath === false
            || !str_starts_with($resolvedPath, rtrim($packageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)
            || !is_file($resolvedPath)
        ) {
            throw new InvalidArgumentException(__(
                'plugins.importexport.fullJournal.error.packageFileReferenceNotFoundLine',
                [
                    'source' => $source,
                    'line' => $href->getLineNo(),
                ]
            ));
        }
        return $resolvedPath;
    }
}
