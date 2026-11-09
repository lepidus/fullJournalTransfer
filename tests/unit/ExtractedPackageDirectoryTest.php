<?php

require_once dirname(__DIR__, 2) . '/classes/ExtractedPackageDirectory.inc.php';

class ExtractedPackageDirectoryTest extends \PHPUnit\Framework\TestCase
{
    private $root;
    private $package;
    private $outside;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/fjt-extracted-' . bin2hex(random_bytes(8));
        $this->package = $this->root . '/package';
        $this->outside = $this->root . '/outside';
        mkdir($this->package . '/journals/1/articles', 0700, true);
        mkdir($this->outside . '/nested', 0700, true);
        file_put_contents($this->package . '/journal.xml', '<journal/>');
        file_put_contents($this->package . '/journals/1/articles/file.pdf', 'package file');
        file_put_contents($this->outside . '/sentinel.txt', 'outside file');
        file_put_contents($this->outside . '/nested/inner.txt', 'outside nested file');
    }

    protected function tearDown(): void
    {
        (new ExtractedPackageDirectory())->remove($this->root);
    }

    public function testAcceptsPackageWithOnlyRegularFilesAndDirectories()
    {
        $this->assertNull((new ExtractedPackageDirectory())->findUnsupportedEntry($this->package));
    }

    public function testFindsSymbolicLinksAnywhereInThePackage()
    {
        symlink($this->outside, $this->package . '/journals/1/escape-dir');

        $this->assertSame(
            'journals/1/escape-dir',
            (new ExtractedPackageDirectory())->findUnsupportedEntry($this->package)
        );
    }

    public function testFindsSymbolicLinksToFiles()
    {
        symlink($this->outside . '/sentinel.txt', $this->package . '/escape.txt');

        $this->assertSame('escape.txt', (new ExtractedPackageDirectory())->findUnsupportedEntry($this->package));
    }

    public function testFindsSpecialFiles()
    {
        if (!function_exists('posix_mkfifo')) {
            $this->markTestSkipped('posix_mkfifo is not available.');
        }
        posix_mkfifo($this->package . '/journals/fifo', 0600);

        $this->assertSame('journals/fifo', (new ExtractedPackageDirectory())->findUnsupportedEntry($this->package));
    }

    public function testRemovesLinksWithoutDeletingTheirTargets()
    {
        symlink($this->outside, $this->package . '/escape-dir');
        symlink($this->outside . '/sentinel.txt', $this->package . '/journals/escape.txt');
        symlink($this->root . '/missing', $this->package . '/dangling');

        $this->assertTrue((new ExtractedPackageDirectory())->remove($this->package));

        $this->assertFalse(file_exists($this->package) || is_link($this->package));
        $this->assertSame('outside file', file_get_contents($this->outside . '/sentinel.txt'));
        $this->assertSame('outside nested file', file_get_contents($this->outside . '/nested/inner.txt'));
    }

    public function testRemovesDirectoriesWithoutOwnerPermissions()
    {
        chmod($this->package . '/journals/1/articles', 0000);

        $this->assertTrue((new ExtractedPackageDirectory())->remove($this->package));

        $this->assertDirectoryDoesNotExist($this->package);
    }
}
