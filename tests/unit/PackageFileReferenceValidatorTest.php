<?php

require_once dirname(__DIR__, 2) . '/classes/PackageFileReferenceValidator.inc.php';

class PackageFileReferenceValidatorTest extends \PHPUnit\Framework\TestCase
{
    private $root;
    private $package;
    private $outsideFile;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/fjt-href-' . bin2hex(random_bytes(8));
        $this->package = $this->root . '/package';
        mkdir($this->package . '/journals/1/articles', 0700, true);
        file_put_contents($this->package . '/journals/1/articles/file.pdf', 'package file');
        $this->outsideFile = $this->root . '/outside.txt';
        file_put_contents($this->outsideFile, 'secret');
        symlink($this->outsideFile, $this->package . '/journals/1/escape.txt');
        symlink($this->root, $this->package . '/journals/1/escape-dir');
    }

    protected function tearDown(): void
    {
        if (is_file($this->package . '/journal.xml')) {
            unlink($this->package . '/journal.xml');
        }
        unlink($this->package . '/journals/1/escape-dir');
        unlink($this->package . '/journals/1/escape.txt');
        unlink($this->package . '/journals/1/articles/file.pdf');
        rmdir($this->package . '/journals/1/articles');
        rmdir($this->package . '/journals/1');
        rmdir($this->package . '/journals');
        rmdir($this->package);
        unlink($this->outsideFile);
        rmdir($this->root);
    }

    public function testAcceptsReferencesToFilesInsideThePackage(): void
    {
        $xmlPath = $this->journalXml(['journals/1/articles/file.pdf', 'journals/1/articles/file.pdf']);

        $this->assertNull((new PackageFileReferenceValidator())->findUnsafeReference($xmlPath, $this->package));
    }

    /**
     * @dataProvider unsafeReferences
     */
    public function testReportsTheFirstReferenceOutsideThePackage(string $source): void
    {
        $xmlPath = $this->journalXml(['journals/1/articles/file.pdf', $source]);

        $unsafeReference = (new PackageFileReferenceValidator())->findUnsafeReference($xmlPath, $this->package);

        $this->assertSame($source, $unsafeReference['source']);
        $this->assertSame(5, $unsafeReference['line']);
    }

    public function unsafeReferences(): array
    {
        return [
            'absolute path' => ['/etc/passwd'],
            'parent traversal' => ['journals/../../outside.txt'],
            'current directory segment' => ['./journals/1/articles/file.pdf'],
            'http url' => ['http://169.254.169.254/latest/meta-data/'],
            'file url' => ['file:///etc/passwd'],
            'url after a relative segment' => ['journals/http://example.test/file'],
            'backslash traversal' => ['journals\\..\\..\\outside.txt'],
            'empty source' => [''],
            'missing file' => ['journals/1/articles/missing.pdf'],
            'directory' => ['journals/1/articles'],
            'symlink to an outside file' => ['journals/1/escape.txt'],
            'symlink to an outside directory' => ['journals/1/escape-dir/outside.txt'],
        ];
    }

    public function testChecksTheExpandedValueOfEntityReferences(): void
    {
        $xmlPath = $this->package . '/journal.xml';
        file_put_contents(
            $xmlPath,
            '<?xml version="1.0"?>' . "\n"
            . '<!DOCTYPE journal [<!ENTITY target "/etc/passwd">]>' . "\n"
            . '<journal xmlns="http://pkp.sfu.ca"><file><href src="&target;"/></file></journal>'
        );

        $unsafeReference = (new PackageFileReferenceValidator())->findUnsafeReference($xmlPath, $this->package);

        $this->assertSame('/etc/passwd', $unsafeReference['source']);
    }

    public function testRejectsMalformedXml(): void
    {
        $xmlPath = $this->package . '/journal.xml';
        file_put_contents($xmlPath, '<journal><file><href src="journals/1/articles/file.pdf"/></journal>');

        $this->expectException(RuntimeException::class);

        (new PackageFileReferenceValidator())->findUnsafeReference($xmlPath, $this->package);
    }

    private function journalXml(array $sources): string
    {
        $files = '';
        foreach ($sources as $source) {
            $files .= "\n" . '<file><href src="' . htmlspecialchars($source, ENT_QUOTES) . '" mime_type="text/plain"/></file>';
        }
        $xmlPath = $this->package . '/journal.xml';
        file_put_contents(
            $xmlPath,
            '<?xml version="1.0"?>' . "\n" . '<journal xmlns="http://pkp.sfu.ca">' . "\n"
            . '<articles>' . $files . "\n" . '</articles></journal>'
        );
        return $xmlPath;
    }
}
