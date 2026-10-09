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

use Crest\Project\Manifest;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;

final class ManifestTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('manifest');
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    public function testAListTargetGivesItsFirstFolder(): void
    {
        // composer allows "App\\": ["src/", "lib/"].
        file_put_contents($this->root . '/composer.json', '{"autoload":{"psr-4":{"App\\\\":["src/","lib/"]}}}');

        $this->assertSame(['App\\' => 'src/'], Manifest::psr4($this->root));
    }

    public function testAnEmptyTargetIsSkipped(): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"Empty\\\\":"","App\\\\":"src/"}}}'
        );

        $this->assertSame(['App\\' => 'src/'], Manifest::psr4($this->root));
    }

    public function testAnotherPackageIsNotRequired(): void
    {
        file_put_contents($this->root . '/composer.json', '{"require":{"php":"^8.1"}}');

        $this->assertFalse(Manifest::requires($this->root, 'phalcon/crest'));
    }

    public function testInvalidJsonReadsAsEmpty(): void
    {
        file_put_contents($this->root . '/composer.json', '{');

        $this->assertSame([], Manifest::psr4($this->root));
        $this->assertFalse(Manifest::requires($this->root, 'phalcon/crest'));
    }

    public function testNoComposerJsonReadsAsEmpty(): void
    {
        $this->assertSame([], Manifest::psr4($this->root));
        $this->assertFalse(Manifest::requires($this->root, 'phalcon/crest'));
    }

    public function testRequireDevNamesThePackage(): void
    {
        file_put_contents($this->root . '/composer.json', '{"require-dev":{"phalcon/crest":"dev-master"}}');

        $this->assertTrue(Manifest::requires($this->root, 'phalcon/crest'));
    }

    public function testRequireNamesThePackage(): void
    {
        file_put_contents($this->root . '/composer.json', '{"require":{"phalcon/crest":"dev-master"}}');

        $this->assertTrue(Manifest::requires($this->root, 'phalcon/crest'));
    }

    public function testThePsr4MapKeepsTheDeclarationOrder(): void
    {
        $this->writeComposerJson(['Ghost\\' => 'missing/', 'App\\' => 'src/']);

        $this->assertSame(['Ghost\\' => 'missing/', 'App\\' => 'src/'], Manifest::psr4($this->root));
    }
}
