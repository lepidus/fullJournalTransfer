<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\publicFiles;

use APP\file\PublicFileManager;
use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\publicFiles\PublicFilesTransfer;
use APP\plugins\importexport\fullJournalTransfer\tests\support\LoadsPluginLocale;
use APP\plugins\importexport\fullJournalTransfer\transfer\ImportedResourceJournal;
use DOMDocument;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PublicFilesTransferTest extends TestCase
{
    use LoadsPluginLocale;

    public function testItExportsLocalizedAppearanceMetadataWithoutTemporaryIds(): void
    {
        $directory = 'public transfer-' . bin2hex(random_bytes(8));
        $path = $directory . '/journals/7';
        mkdir($path, 0700, true);
        file_put_contents($path . '/logo.png', 'logo');
        $journal = new Journal();
        $journal->setData('pageHeaderLogoImage', ['zh_Hant' => ['uploadName' => 'missing.png'], 'pt_BR' => [
            'uploadName' => 'logo.png', 'altText' => 'Revista', 'temporaryFileId' => 42,
        ]]);
        try {
            $document = new DOMDocument();
            $node = (new PublicFilesTransfer())->export($document, $journal, $path, 'https://example.com/' . str_replace(' ', '%20', $path), ['pt_BR']);
            $data = (new PublicFilesTransfer())->validate($node);
            $this->assertSame(['logo.png'], $data['files']);
            $this->assertSame([['name' => 'pageHeaderLogoImage', 'locale' => 'pt_BR',
                'value' => ['uploadName' => 'logo.png', 'altText' => 'Revista']]], $data['settings']);
        } finally {
            unlink($path . '/logo.png');
            rmdir($path);
            rmdir($directory . '/journals');
            rmdir($directory);
        }
    }

    public function testItSeparatesAbsoluteFilesystemPathsFromPublicUrls(): void
    {
        $this->assertSame('public/journals/7', (new PublicFilesTransfer())->publicPath(
            dirname(INDEX_FILE_LOCATION) . '/public/journals/7'
        ));
    }

    public function testItRestoresNestedFilesAndUpdatesOnlySourceJournalReferences(): void
    {
        $directory = sys_get_temp_dir() . '/public-import-' . bin2hex(random_bytes(8));
        mkdir($directory . '/package/public-files/assets', 0700, true);
        mkdir($directory . '/destination', 0700, true);
        $source = 'https://source.example/ojs/public/journals/7';
        $target = 'https://target.example/portal/media/journals/12';
        file_put_contents($directory . '/package/public-files/assets/logo.png', 'unchanged image');
        file_put_contents(
            $directory . '/package/public-files/style.css',
            'a{background:url(' . $source . '/assets/logo.png)}'
            . 'b{background:url(/ojs/public/journals/7/assets/logo.png)}'
            . 'c{background:url(assets/logo.png)}'
        );
        $document = new DOMDocument();
        $document->loadXML('<journal><description>'
            . htmlspecialchars('<img src="' . $source . '/assets/logo.png">', ENT_QUOTES)
            . '</description><other>https://other.example/ojs/public/journals/7/logo.png</other>'
            . '<neighbor>https://source.example/ojs/public/journals/70/logo.png</neighbor>'
            . '<public_files source_path="public/journals/7" source_url="' . $source . '">'
            . '<file path="assets/logo.png"/><file path="style.css"/></public_files></journal>');
        $node = $document->getElementsByTagName('public_files')->item(0);
        $recorded = [];
        try {
            (new PublicFilesTransfer())->restore(
                $node,
                $directory . '/package',
                $directory . '/destination',
                $target,
                function (string $path) use (&$recorded): void { $recorded[] = $path; },
                static function (string $path): void {}
            );
            (new PublicFilesTransfer())->rewriteReferences($document->documentElement, $node, $target);
            $this->assertSame('unchanged image', file_get_contents($directory . '/destination/assets/logo.png'));
            $this->assertSame('a{background:url(' . $target . '/assets/logo.png)}'
                . 'b{background:url(/portal/media/journals/12/assets/logo.png)}'
                . 'c{background:url(assets/logo.png)}', file_get_contents($directory . '/destination/style.css'));
            $this->assertSame(
                '<img src="' . $target . '/assets/logo.png">',
                $document->getElementsByTagName('description')->item(0)->textContent
            );
            $this->assertSame(
                'https://other.example/ojs/public/journals/7/logo.png',
                $document->getElementsByTagName('other')->item(0)->textContent
            );
            $this->assertSame(
                'https://source.example/ojs/public/journals/70/logo.png',
                $document->getElementsByTagName('neighbor')->item(0)->textContent
            );
            $this->assertCount(2, $recorded);
        } finally {
            foreach (['package/public-files/assets/logo.png', 'package/public-files/style.css',
                'destination/assets/logo.png', 'destination/style.css'] as $file) {
                if (is_file($directory . '/' . $file)) {
                    unlink($directory . '/' . $file);
                }
            }
            foreach (['package/public-files/assets', 'package/public-files', 'package',
                'destination/assets', 'destination', ''] as $path) {
                if (is_dir($directory . '/' . $path)) {
                    rmdir($directory . '/' . $path);
                }
            }
        }
    }

    public function testItRejectsAnExistingDestinationWithoutChangingItsContents(): void
    {
        $directory = sys_get_temp_dir() . '/public-collision-' . bin2hex(random_bytes(8));
        mkdir($directory . '/package/public-files', 0700, true);
        mkdir($directory . '/destination', 0700, true);
        file_put_contents($directory . '/package/public-files/logo.png', 'new');
        file_put_contents($directory . '/destination/logo.png', 'existing');
        $document = new DOMDocument();
        $document->loadXML('<public_files source_path="public/journals/7" '
            . 'source_url="https://source.example/public/journals/7"><file path="logo.png"/></public_files>');
        try {
            $this->expectException(InvalidArgumentException::class);
            (new PublicFilesTransfer())->restore(
                $document->documentElement,
                $directory . '/package',
                $directory . '/destination',
                'https://target.example/public/journals/12',
                static function (string $path): void {},
                static function (string $path): void {}
            );
        } finally {
            $this->assertSame('existing', file_get_contents($directory . '/destination/logo.png'));
            unlink($directory . '/package/public-files/logo.png');
            unlink($directory . '/destination/logo.png');
            rmdir($directory . '/package/public-files');
            rmdir($directory . '/package');
            rmdir($directory . '/destination');
            rmdir($directory);
        }
    }

    public function testRestoredFilesAndDirectoriesParticipateInCompensation(): void
    {
        $directory = sys_get_temp_dir() . '/public-compensation-' . bin2hex(random_bytes(8));
        mkdir($directory . '/package/public-files/assets', 0700, true);
        mkdir($directory . '/destination', 0700, true);
        file_put_contents($directory . '/package/public-files/assets/logo.png', 'logo');
        $document = new DOMDocument();
        $document->loadXML('<public_files source_path="public/journals/7" '
            . 'source_url="https://source.example/public/journals/7"><file path="assets/logo.png"/></public_files>');
        $resources = new ImportedResourceJournal();
        try {
            (new PublicFilesTransfer())->restore(
                $document->documentElement,
                $directory . '/package',
                $directory . '/destination',
                'https://target.example/public/journals/12',
                [$resources, 'recordFile'],
                [$resources, 'recordDirectory']
            );
            $this->assertFileExists($directory . '/destination/assets/logo.png');
            $this->assertSame([], $resources->compensate(
                static fn (string $path): bool => (new PublicFileManager())->rmtree($path)
            ));
            $this->assertDirectoryDoesNotExist($directory . '/destination/assets');
        } finally {
            (new PublicFileManager())->rmtree($directory);
        }
    }

    /** @dataProvider publicReferences */
    public function testItRewritesReferencesWithoutChangingOtherUrls(string $source, string $expected): void
    {
        $document = new DOMDocument();
        $document->loadXML('<journal><content/><public_files source_path="public/journals/7" '
            . 'source_url="https://source.example/ojs/public/journals/7"/></journal>');
        $content = $document->getElementsByTagName('content')->item(0);
        $content->appendChild($document->createTextNode($source));
        (new PublicFilesTransfer())->rewriteReferences(
            $document->documentElement,
            $document->getElementsByTagName('public_files')->item(0),
            'https://target.example/portal/media/journals/12'
        );
        $this->assertSame($expected, $content->textContent);
    }

    public static function publicReferences(): array
    {
        return [
            ['//source.example/ojs/public/journals/7/logo.png', '//target.example/portal/media/journals/12/logo.png'],
            ['/ojs/public/journals/7/logo.png', '/portal/media/journals/12/logo.png'],
            ['public/journals/7/logo.png', 'portal/media/journals/12/logo.png'],
            [json_encode('https://source.example/ojs/public/journals/7/logo.png'),
                json_encode('https://target.example/portal/media/journals/12/logo.png')],
            ['https://other.example/ojs/public/journals/7/logo.png',
                'https://other.example/ojs/public/journals/7/logo.png'],
            ['https://source.example/ojs/public/journals/70/logo.png',
                'https://source.example/ojs/public/journals/70/logo.png'],
            ['//other.example/ojs/public/journals/7/logo.png', '//other.example/ojs/public/journals/7/logo.png'],
        ];
    }

    /** @dataProvider unsafePaths */
    public function testItRejectsUnsafePublicFilePaths(string $path): void
    {
        $document = new DOMDocument();
        $document->loadXML('<public_files source_path="public/journals/7" '
            . 'source_url="https://source.example/public/journals/7"><file path="'
            . htmlspecialchars($path, ENT_QUOTES) . '"/></public_files>');
        $this->expectException(InvalidArgumentException::class);
        (new PublicFilesTransfer())->validate($document->documentElement);
    }

    public static function unsafePaths(): array
    {
        return array_map(static fn ($path): array => [$path], [
            '../site/logo.png', '/logo.png', 'assets/../logo.png', 'assets//logo.png',
            'assets\\logo.png', 'shell.php', 'shell.PHP.png', 'shell.pht', 'shell.jsp', 'unknown.exe', '.htaccess', 'assets/.user.ini',
        ]);
    }

    public function testItRejectsAppearanceMetadataWithoutItsFile(): void
    {
        $document = new DOMDocument();
        $document->loadXML('<public_files source_path="public/journals/7" '
            . 'source_url="https://source.example/public/journals/7">'
            . '<setting name="styleSheet">{"uploadName":"missing.css"}</setting></public_files>');
        $this->expectException(InvalidArgumentException::class);
        (new PublicFilesTransfer())->validate($document->documentElement);
    }
}
