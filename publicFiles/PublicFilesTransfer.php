<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\publicFiles;

use APP\journal\Journal;
use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

class PublicFilesTransfer
{
    private const NAMESPACE = 'http://pkp.sfu.ca';
    private const SETTINGS = [
        'pageHeaderLogoImage' => true,
        'homepageImage' => true,
        'favicon' => true,
        'journalThumbnail' => true,
        'styleSheet' => false,
    ];
    private const STATIC_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'svg', 'ico', 'bmp', 'tif', 'tiff',
        'css', 'js', 'map', 'woff', 'woff2', 'ttf', 'otf', 'eot',
        'pdf', 'doc', 'docx', 'odt', 'rtf', 'txt', 'csv', 'tsv', 'xls', 'xlsx', 'ods',
        'ppt', 'pptx', 'odp', 'zip', 'gz', 'bz2', 'tar', 'tgz', '7z', 'rar',
        'xml', 'json', 'html', 'htm', 'mp3', 'mp4', 'webm', 'ogg', 'wav', 'ogv', 'epub',
    ];
    private const IMAGE_MIME_TYPES = [
        'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'image/avif', 'image/svg+xml',
        'image/x-icon', 'image/vnd.microsoft.icon', 'image/bmp', 'image/x-ms-bmp', 'image/tiff',
    ];
    private const METADATA = ['name', 'uploadName', 'width', 'height', 'dateUploaded', 'altText'];

    /** Convert an in-application filesystem path to a relative public URL path. */
    public function publicPath(string $path): string
    {
        if (str_starts_with($path, '/')) {
            $applicationRoot = dirname(INDEX_FILE_LOCATION) . '/';
            if (!str_starts_with($path, $applicationRoot)) {
                $this->invalid();
            }
            $path = substr($path, strlen($applicationRoot));
        }
        $this->validatePath($path);
        return $path;
    }

    public function export(
        DOMDocument $document,
        Journal $journal,
        string $path,
        string $url,
        array $acceptedLocales
    ): DOMElement {
        $node = $document->createElementNS(self::NAMESPACE, 'public_files');
        $node->setAttribute('source_path', $this->publicPath($path));
        $node->setAttribute('source_url', $url);
        if (is_link($path) || is_link(dirname($path))) {
            $this->invalid();
        }
        $paths = [];
        if (is_dir($path)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
                $path,
                RecursiveDirectoryIterator::SKIP_DOTS
            ), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $entry) {
                if ($entry->isLink() || (!$entry->isDir() && !$entry->isFile())) {
                    $this->invalid();
                }
                if ($entry->isFile()) {
                    $relative = substr($entry->getPathname(), strlen($path) + 1);
                    $this->validateFilePath($relative);
                    $this->validateExtensionlessImage($entry->getPathname());
                    $paths[] = $relative;
                }
            }
        }
        sort($paths);
        foreach (self::SETTINGS as $name => $localized) {
            $value = $journal->getData($name);
            if ($value === null || $value === []) {
                continue;
            }
            if (!is_array($value)) {
                $this->invalid();
            }
            foreach ($localized ? $value : ['' => $value] as $locale => $metadata) {
                if (($localized && !in_array($locale, $acceptedLocales, true))
                    || $metadata === null || $metadata === []
                ) {
                    continue;
                }
                if (!is_array($metadata)) {
                    $this->invalid();
                }
                $metadata = array_intersect_key($metadata, array_flip(self::METADATA));
                $setting = $document->createElementNS(self::NAMESPACE, 'setting');
                $setting->setAttribute('name', $name);
                if ($localized) {
                    $setting->setAttribute('locale', $locale);
                }
                $setting->appendChild($document->createTextNode(json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
                )));
                $node->appendChild($setting);
            }
        }
        foreach ($paths as $relative) {
            $file = $document->createElementNS(self::NAMESPACE, 'file');
            $file->setAttribute('path', $relative);
            $node->appendChild($file);
        }
        $this->validate($node);
        return $node;
    }

    /** Validate before reading the source or writing any destination files. */
    public function validate(DOMElement $node): array
    {
        $path = $node->getAttribute('source_path');
        $url = $node->getAttribute('source_url');
        if (preg_match('~^.+/journals/[1-9][0-9]*$~D', $path) !== 1
            || !filter_var($url, FILTER_VALIDATE_URL)
            || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            || !str_ends_with(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '/' . $path)
            || parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null
            || parse_url($url, PHP_URL_USER) !== null
        ) {
            $this->invalid();
        }
        $this->validatePath($path);
        $files = [];
        $settings = [];
        foreach ($node->childNodes as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if ($child->localName === 'file') {
                $relative = $child->getAttribute('path');
                $this->validateFilePath($relative);
                if (isset($files[$relative])) {
                    $this->invalid();
                }
                $files[$relative] = true;
            } elseif ($child->localName === 'setting') {
                $name = $child->getAttribute('name');
                $locale = $child->getAttribute('locale');
                if (!array_key_exists($name, self::SETTINGS)
                    || (self::SETTINGS[$name] && preg_match('/^[a-z]{2}(_[A-Z]{2})?$/D', $locale) !== 1)
                    || (!self::SETTINGS[$name] && $locale !== '')
                    || isset($settings[$name . ':' . $locale])
                ) {
                    $this->invalid();
                }
                $metadata = json_decode($child->textContent, true, 32, JSON_THROW_ON_ERROR);
                if (!is_array($metadata) || !isset($metadata['uploadName'])
                    || !is_string($metadata['uploadName'])
                    || array_diff(array_keys($metadata), self::METADATA) !== []
                ) {
                    $this->invalid();
                }
                $this->validatePath($metadata['uploadName']);
                foreach ($metadata as $key => $value) {
                    if (in_array($key, ['width', 'height'], true) ? !is_int($value) : !is_string($value)) {
                        $this->invalid();
                    }
                }
                $settings[$name . ':' . $locale] = ['name' => $name, 'locale' => $locale, 'value' => $metadata];
            } else {
                $this->invalid();
            }
        }
        foreach ($settings as $setting) {
            if (!isset($files[$setting['value']['uploadName']])) {
                $this->invalid();
            }
        }
        return ['files' => array_keys($files), 'settings' => array_values($settings)];
    }

    public function stage(DOMElement $node, string $publicDirectory, string $stagingPath): array
    {
        $data = $this->validate($node);
        $id = basename($node->getAttribute('source_path'));
        $sourceRoot = rtrim($publicDirectory, '/') . '/journals/' . $id;
        if (is_link($publicDirectory) || is_link(dirname($sourceRoot))) {
            $this->invalid();
        }
        $entries = [];
        foreach ($data['files'] as $relative) {
            $source = $this->regularFile($sourceRoot, $relative);
            $entry = 'public-files/' . $relative;
            $destination = $stagingPath . '/' . $entry;
            if (!is_dir(dirname($destination)) && !mkdir(dirname($destination), 0700, true)) {
                $this->ioError();
            }
            if (!copy($source, $destination)) {
                $this->ioError();
            }
            $entries[] = $entry;
        }
        return $entries;
    }

    public function applySettings(DOMElement $node, Journal $journal, array $acceptedLocales): void
    {
        foreach ($this->validate($node)['settings'] as $setting) {
            if ($setting['locale'] === '') {
                $journal->setData($setting['name'], $setting['value']);
            } elseif (in_array($setting['locale'], $acceptedLocales, true)) {
                $journal->setData($setting['name'], $setting['value'], $setting['locale']);
            }
        }
    }

    public function restore(
        DOMElement $node,
        string $packagePath,
        string $destinationRoot,
        string $destinationUrl,
        callable $recordFile,
        callable $recordDirectory
    ): void {
        $data = $this->validate($node);
        $destination = realpath($destinationRoot);
        if ($destination === false || is_link($destinationRoot) || is_link(dirname($destinationRoot))) {
            $this->invalid();
        }
        $sources = [];
        foreach ($data['files'] as $relative) {
            $sources[$relative] = $this->regularFile($packagePath . '/public-files', $relative);
            $target = $destination;
            foreach (explode('/', $relative) as $component) {
                $target .= '/' . $component;
                if (is_link($target)) {
                    $this->invalid();
                }
            }
            if (file_exists($target)) {
                $this->invalid();
            }
        }
        foreach ($sources as $relative => $source) {
            $target = $destination . '/' . $relative;
            $parent = $destination;
            $components = explode('/', $relative);
            array_pop($components);
            foreach ($components as $component) {
                $parent .= '/' . $component;
                if (!is_dir($parent)) {
                    if (!mkdir($parent, 0755)) {
                        $this->ioError();
                    }
                    $recordDirectory($parent);
                }
                if (is_link($parent)) {
                    $this->invalid();
                }
            }
            $output = fopen($target, 'xb');
            if ($output === false) {
                $this->ioError();
            }
            try {
                $recordFile($target);
                if (in_array(
                    strtolower(pathinfo($relative, PATHINFO_EXTENSION)),
                    ['css', 'html', 'htm', 'svg', 'js', 'json', 'xml', 'map'],
                    true
                ) || (pathinfo($relative, PATHINFO_EXTENSION) === ''
                    && (new \finfo(FILEINFO_MIME_TYPE))->file($source) === 'image/svg+xml')
                ) {
                    $content = file_get_contents($source);
                    if ($content === false) {
                        $this->ioError();
                    }
                    $content = $this->rewriteText($content, $node, $destinationUrl);
                    if (fwrite($output, $content) !== strlen($content)) {
                        $this->ioError();
                    }
                } else {
                    $input = fopen($source, 'rb');
                    if ($input === false) {
                        $this->ioError();
                    }
                    try {
                        if (stream_copy_to_stream($input, $output) !== filesize($source)) {
                            $this->ioError();
                        }
                    } finally {
                        fclose($input);
                    }
                }
            } finally {
                fclose($output);
            }
        }
    }

    /** Rewrite exported content before any child filter persists it. */
    public function rewriteReferences(DOMNode $node, DOMElement $publicFiles, string $destinationUrl): void
    {
        if ($node === $publicFiles) {
            return;
        }
        if ($node instanceof DOMElement) {
            foreach ($node->attributes as $attribute) {
                $attribute->value = $this->rewriteText($attribute->value, $publicFiles, $destinationUrl);
            }
        } elseif (in_array($node->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
            $node->nodeValue = $this->rewriteText($node->nodeValue, $publicFiles, $destinationUrl);
        }
        foreach ($node->childNodes as $child) {
            $this->rewriteReferences($child, $publicFiles, $destinationUrl);
        }
    }

    private function rewriteText(string $text, DOMElement $node, string $destinationUrl): string
    {
        if (!str_contains($text, 'journals') && !str_contains($text, '%')) {
            return $text;
        }
        $sourceUrl = $node->getAttribute('source_url');
        $sourcePath = $node->getAttribute('source_path');
        $sourceRootPath = (string) parse_url($sourceUrl, PHP_URL_PATH);
        $destinationRootPath = (string) parse_url($destinationUrl, PHP_URL_PATH);
        $maps = [
            $sourceUrl . '/' => $destinationUrl . '/',
            rawurldecode($sourceUrl) . '/' => rawurldecode($destinationUrl) . '/',
            preg_replace('~^https?:~', '', $sourceUrl) . '/'
                => preg_replace('~^https?:~', '', $destinationUrl) . '/',
            $sourceRootPath . '/' => $destinationRootPath . '/',
            rawurldecode($sourceRootPath) . '/' => rawurldecode($destinationRootPath) . '/',
            $sourcePath . '/' => ltrim($destinationRootPath, '/') . '/',
        ];
        // JSON theme options may escape slashes; do not decode arbitrary JSON or HTML.
        foreach ($maps as $from => $to) {
            $maps[str_replace('/', '\\/', $from)] = str_replace('/', '\\/', $to);
        }
        uksort($maps, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $pattern = '~(?<![a-zA-Z0-9_/:.\\\\-])(?:'
            . implode('|', array_map(static fn ($value): string => preg_quote($value, '~'), array_keys($maps)))
            . ')~';
        return preg_replace_callback($pattern, static fn (array $match): string => $maps[$match[0]], $text);
    }

    private function regularFile(string $root, string $relative): string
    {
        $this->validatePath($relative);
        $base = realpath($root);
        $path = $root;
        if (is_link($root)) {
            $this->invalid();
        }
        foreach (explode('/', $relative) as $component) {
            $path .= '/' . $component;
            if (is_link($path) || is_link(dirname($path))) {
                $this->invalid();
            }
        }
        $real = realpath($path);
        if ($base === false || $real === false || !str_starts_with($real, $base . '/')
            || !is_file($real) || !is_readable($real)
        ) {
            $this->invalid();
        }
        $this->validateExtensionlessImage($real);
        return $real;
    }

    private function validateFilePath(string $path): void
    {
        $this->validatePath($path);
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension !== '' && !in_array($extension, self::STATIC_EXTENSIONS, true)) {
            $this->invalid();
        }
    }

    private function validateExtensionlessImage(string $path): void
    {
        if (pathinfo($path, PATHINFO_EXTENSION) !== '') {
            return;
        }
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mimeType, self::IMAGE_MIME_TYPES, true)) {
            $this->invalid();
        }
    }

    private function validatePath(string $path): void
    {
        if ($path === '' || $path[0] === '/' || preg_match('/[\\\\\x00-\x1f\x7f]/', $path)) {
            $this->invalid();
        }
        foreach (explode('/', $path) as $component) {
            if (in_array($component, ['', '.', '..'], true)
                || in_array(strtolower($component), ['.htaccess', '.user.ini', 'web.config'], true)
                || preg_match(
                    '/\.(php[0-9]*|phtml|pht|phar|cgi|fcgi|pl|py|sh|shtml|shtm|stm|asp[x]?|jsp[x]?)($|\.)/i',
                    $component
                )
            ) {
                $this->invalid();
            }
        }
    }

    private function invalid(): void
    {
        throw new InvalidArgumentException(__('plugins.importexport.fullJournal.error.publicFilesInvalid'));
    }

    private function ioError(): void
    {
        throw new RuntimeException(__('plugins.importexport.fullJournal.error.publicFilesCopyFailed'));
    }
}
