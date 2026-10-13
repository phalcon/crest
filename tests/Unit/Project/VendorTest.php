<?php

/**
 * This file is part of the Phalcon Crest.
 *
 * (c) Phalcon Team <team@phalcon.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Crest\Tests\Unit\Project;

use Crest\Console\Exceptions\Exception;
use Crest\Project\Vendor;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function json_encode;
use function putenv;

final class VendorTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('vendor');
    }

    protected function tearDown(): void
    {
        putenv('COMPOSER_BIN_DIR');
        putenv('COMPOSER_VENDOR_DIR');

        $this->removeScratchDirectory();
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'a tilde' => ['vendor-dir', '~/lib', "unsupported vendor-dir '~/lib' in composer.json"];
        yield 'a variable' => ['vendor-dir', '$HOME/lib', "unsupported vendor-dir '\$HOME/lib' in composer.json"];
        yield 'another placeholder' => [
            'bin-dir',
            '{$home}/bin',
            "unsupported bin-dir '{\$home}/bin' in composer.json",
        ];
    }

    public function testAnAbsoluteFolderStaysAsItIs(): void
    {
        $this->config(['vendor-dir' => '/opt/vendor']);

        $this->assertSame('/opt/vendor', Vendor::vendorDir($this->root, true));
        $this->assertSame('/opt/vendor', Vendor::path($this->root, '/opt/vendor'));
        $this->assertTrue(Vendor::isAbsolute('/opt/vendor'));
        $this->assertFalse(Vendor::isAbsolute('vendor'));
    }

    public function testAnEmptyValueIsTheDefault(): void
    {
        $this->config(['vendor-dir' => '']);
        putenv('COMPOSER_VENDOR_DIR=');

        $this->assertSame('vendor', Vendor::vendorDir($this->root, true));
    }

    public function testARelativeFolderIsInTheRoot(): void
    {
        $this->assertSame($this->root . '/lib', Vendor::path($this->root, 'lib'));
    }

    public function testTheBinDirComesFromComposerJson(): void
    {
        $this->config(['bin-dir' => 'tools/']);

        $this->assertSame('tools', Vendor::binDir($this->root, true));
    }

    public function testTheBinDirDefaultIsInTheVendorDir(): void
    {
        $this->config(['vendor-dir' => 'lib']);

        $this->assertSame('lib/bin', Vendor::binDir($this->root, true));
    }

    public function testTheDefaultsWithoutComposerJson(): void
    {
        $this->assertSame('vendor', Vendor::vendorDir($this->root, true));
        $this->assertSame('vendor/bin', Vendor::binDir($this->root, true));
    }

    public function testTheEnvironmentCanBeIgnored(): void
    {
        $this->config(['vendor-dir' => 'lib']);
        putenv('COMPOSER_VENDOR_DIR=env-lib');
        putenv('COMPOSER_BIN_DIR=env-bin');

        $this->assertSame('lib', Vendor::vendorDir($this->root, false));
        $this->assertSame('lib/bin', Vendor::binDir($this->root, false));
    }

    public function testTheEnvironmentComesFirst(): void
    {
        $this->config(['vendor-dir' => 'lib', 'bin-dir' => 'tools']);
        putenv('COMPOSER_VENDOR_DIR=env-lib');
        putenv('COMPOSER_BIN_DIR=env-bin');

        $this->assertSame('env-lib', Vendor::vendorDir($this->root, true));
        $this->assertSame('env-bin', Vendor::binDir($this->root, true));
    }

    public function testThePlaceholderIsReplaced(): void
    {
        $this->config(['vendor-dir' => 'lib', 'bin-dir' => '{$vendor-dir}/tools']);

        $this->assertSame('lib/tools', Vendor::binDir($this->root, true));
    }

    public function testTheSourceOfAnErrorIsTheVariable(): void
    {
        putenv('COMPOSER_BIN_DIR=~/bin');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "unsupported bin-dir '~/bin' in COMPOSER_BIN_DIR; use a relative or an absolute path"
        );

        Vendor::binDir($this->root, true);
    }

    /**
     * @dataProvider unreadable
     */
    public function testUnreadableValuesAreRefused(string $key, string $value, string $message): void
    {
        $this->config([$key => $value]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($message . '; use a relative or an absolute path');

        Vendor::binDir($this->root, true);
    }

    /**
     * @param array<string, string> $config
     */
    private function config(array $config): void
    {
        file_put_contents($this->root . '/composer.json', (string) json_encode(['config' => $config]));
    }
}
