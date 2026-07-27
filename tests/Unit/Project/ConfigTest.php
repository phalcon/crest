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
use Crest\Project\Config;
use Crest\Project\Flavor;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;

final class ConfigTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('project', 'src/Action');
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    public function testCrestPhpMayDeclareTheNamespaceExplicitly(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        mkdir($this->root . '/app/Handlers', 0o775, true);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['flavor' => 'mvc', 'namespace' => 'Shop', "
            . "'paths' => ['action' => 'app/Handlers'], "
            . "'namespaces' => ['action' => 'Shop\\\\Handlers']];\n"
        );

        $config = Config::discover($this->root);

        $this->assertSame(Flavor::Mvc, $config->flavor());
        $this->assertSame('Shop', $config->namespace());
        $this->assertSame($this->root . '/app/Handlers', $config->path('action'));
        $this->assertSame('Shop\Handlers', $config->namespaceFor('action'));
    }

    public function testExplicitConfigFileWins(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        file_put_contents(
            $this->root . '/elsewhere.php',
            "<?php\n\nreturn ['namespace' => 'Other'];\n"
        );

        $config = Config::discover($this->root, $this->root . '/elsewhere.php');

        $this->assertSame('Other', $config->namespace());
    }

    public function testInfersNamespaceAndActionPathFromComposerJson(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);

        $config = Config::discover($this->root);

        $this->assertSame(Flavor::Adr, $config->flavor());
        $this->assertSame('App', $config->namespace());
        $this->assertSame($this->root . '/src/Action', $config->path('action'));
        $this->assertSame($this->root, $config->root());
    }

    public function testMissingComposerJsonThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no crest.php and no composer.json found');

        Config::discover($this->root);
    }

    public function testNamespaceForDerivesFromThePsr4Pairing(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);

        $this->assertSame('App\Action', Config::discover($this->root)->namespaceFor('action'));
    }

    public function testNamespaceForPrefersTheLongestMatchingPsr4Directory(): void
    {
        $this->writeComposerJson(['App\\' => 'src/', 'Deep\\' => 'src/Action/']);
        mkdir($this->root . '/src/Action/Company', 0o775, true);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['action' => 'src/Action/Company']];\n"
        );

        $this->assertSame('Deep\Company', Config::discover($this->root)->namespaceFor('action'));
    }

    public function testNamespaceForThrowsWhenNoPsr4EntryCoversThePath(): void
    {
        // The exact configuration that previously produced a silently wrong
        // namespace: an action path no autoload rule reaches.
        $this->writeComposerJson(['App\\' => 'src/']);
        mkdir($this->root . '/app/Handlers', 0o775, true);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['namespace' => 'Shop', 'paths' => ['action' => 'app/Handlers']];\n"
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no psr-4 autoload entry covers 'app/Handlers'");

        Config::discover($this->root)->namespaceFor('action');
    }

    public function testSkipsPsr4EntriesWhoseDirectoryIsAbsent(): void
    {
        $this->writeComposerJson(['Ghost\\' => 'missing/', 'App\\' => 'src/']);

        $this->assertSame('App', Config::discover($this->root)->namespace());
    }

    public function testUnknownPathKeyThrows(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown path 'views'");

        Config::discover($this->root)->path('views');
    }
}
