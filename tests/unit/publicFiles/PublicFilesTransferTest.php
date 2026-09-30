<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\publicFiles;

use APP\journal\Journal;
use APP\plugins\importexport\fullJournalTransfer\publicFiles\PublicFilesTransfer;
use APP\plugins\importexport\fullJournalTransfer\tests\support\LoadsPluginLocale;
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
