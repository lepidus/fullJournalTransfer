<?php

declare(strict_types=1);

namespace APP\plugins\importexport\fullJournalTransfer\tests\unit\validation;

use APP\plugins\importexport\fullJournalTransfer\FullJournalImportExportDeployment;
use APP\plugins\importexport\fullJournalTransfer\filter\workflow\NativeXmlWorkflowSubmissionFileFilter;
use APP\plugins\importexport\fullJournalTransfer\tests\support\LoadsPluginLocale;
use APP\plugins\importexport\fullJournalTransfer\validation\PackageFileReferenceValidator;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PackageFileReferenceValidatorTest extends TestCase
{
    use LoadsPluginLocale;

    private string $root;
    private string $package;
    private string $outsideFile;

    protected function setUp(): void
    {
        parent::setUp();
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
        unlink($this->package . '/journals/1/escape-dir');
        unlink($this->package . '/journals/1/escape.txt');
        unlink($this->package . '/journals/1/articles/file.pdf');
        rmdir($this->package . '/journals/1/articles');
        rmdir($this->package . '/journals/1');
        rmdir($this->package . '/journals');
        rmdir($this->package);
        unlink($this->outsideFile);
        rmdir($this->root);
        parent::tearDown();
    }

    public function testItResolvesARelativeReferenceToAFileInsideThePackage(): void
    {
        $resolved = (new PackageFileReferenceValidator())->validate(
            $this->href('journals/1/articles/file.pdf'),
            $this->package
        );

        $this->assertSame(realpath($this->package . '/journals/1/articles/file.pdf'), $resolved);
    }

    /**
     * @dataProvider unsafeReferences
     */
    public function testItRejectsReferencesOutsideThePackage(string $source): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PackageFileReferenceValidator())->validate($this->href($source), $this->package);
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

    public function testItRejectsReferencesWithoutAPackagePath(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new PackageFileReferenceValidator())->validate($this->href('journals/1/articles/file.pdf'), '');
    }

    /**
     * @dataProvider unsafeWorkflowReferences
     */
    public function testWorkflowSubmissionFileImportRejectsUnsafeReferencesBeforeReadingThem(string $source): void
    {
        $deployment = $this->createMock(FullJournalImportExportDeployment::class);
        $deployment->method('getImportPath')->willReturn($this->package);
        $filter = $this->getMockBuilder(NativeXmlWorkflowSubmissionFileFilter::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getDeployment'])
            ->getMock();
        $filter->method('getDeployment')->willReturn($deployment);

        $this->expectException(InvalidArgumentException::class);

        $filter->handleRevisionElement($this->href($source)->parentNode);
    }

    public function unsafeWorkflowReferences(): array
    {
        return [
            'absolute path' => ['/etc/passwd'],
            'parent traversal' => ['../outside.txt'],
            'http url' => ['http://127.0.0.1/'],
        ];
    }

    private function href(string $source): DOMElement
    {
        $document = new DOMDocument();
        $file = $document->createElementNS('http://pkp.sfu.ca', 'file');
        $document->appendChild($file);
        $href = $document->createElementNS('http://pkp.sfu.ca', 'href');
        $href->setAttribute('src', $source);
        $file->appendChild($href);
        return $href;
    }
}
