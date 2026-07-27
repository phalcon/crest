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

    public function testNamespaceForReturnsThePrefixAloneWhenThePathIsTheRoot(): void
    {
        // paths.action equals the psr-4 directory exactly, so there is no
        // remainder to append.
        $this->writeComposerJson(['App\\' => 'src/']);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['action' => 'src']];\n"
        );

        $this->assertSame('App', Config::discover($this->root)->namespaceFor('action'));
    }

    public function testNamespaceForKeepsTheLongestMatchWhenAShorterOneComesLater(): void
    {
        // Declaration order puts the deeper directory first, so the second,
        // shorter match must not displace it.
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"Deep\\\\":"src/Action/","App\\\\":"src/"}}}'
        );
        mkdir($this->root . '/src/Action/Company', 0o775, true);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['action' => 'src/Action/Company']];\n"
        );

        $this->assertSame('Deep\Company', Config::discover($this->root)->namespaceFor('action'));
    }

    public function testCrestPhpWithoutAComposerJsonHasAnEmptyPsr4Map(): void
    {
        // No composer.json at all: crest.php still loads, but namespaceFor()
        // has nothing to resolve against and must say so rather than guess.
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['namespace' => 'Shop'];\n"
        );

        $config = Config::discover($this->root);

        $this->assertSame('Shop', $config->namespace());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no psr-4 autoload entry covers 'src/Action'");

        $config->namespaceFor('action');
    }

    public function testExplicitConfigFileBeatsADiscoveredOne(): void
    {
        // Both exist, so this pins the precedence rather than relying on the
        // walk-up finding nothing.
        $this->writeComposerJson(['App\\' => 'src/']);
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn ['namespace' => 'Discovered'];\n");
        file_put_contents($this->root . '/elsewhere.php', "<?php\n\nreturn ['namespace' => 'Explicit'];\n");

        $config = Config::discover($this->root, $this->root . '/elsewhere.php');

        $this->assertSame('Explicit', $config->namespace());
    }

    public function testTrailingSlashOnTheDirectoryIsIgnored(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);

        $config = Config::discover($this->root . '/');

        $this->assertSame($this->root, $config->root());
        $this->assertSame($this->root . '/src/Action', $config->path('action'));
    }

    public function testDeclaredNamespaceIsStrippedOfSurroundingBackslashes(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['namespace' => '\\\\Shop\\\\'];\n"
        );

        $this->assertSame('Shop', Config::discover($this->root)->namespace());
    }

    public function testDeclaredNamespaceForIsStrippedOfSurroundingBackslashes(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['namespaces' => ['action' => '\\\\Shop\\\\Handlers\\\\']];\n"
        );

        $this->assertSame('Shop\Handlers', Config::discover($this->root)->namespaceFor('action'));
    }

    public function testDeclaredPathIsStrippedOfSurroundingSlashes(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['action' => '/src/Action/']];\n"
        );

        $this->assertSame($this->root . '/src/Action', Config::discover($this->root)->path('action'));
    }

    public function testDeclaredPathsAreMergedOverTheDefaultsNotReplaced(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['views' => 'templates']];\n"
        );

        $config = Config::discover($this->root);

        // The declared key is added and the default 'action' survives.
        $this->assertSame($this->root . '/templates', $config->path('views'));
        $this->assertSame($this->root . '/src/Action', $config->path('action'));
    }

    public function testPsr4DirectoryMatchOnlyCountsWholeSegments(): void
    {
        // 'src' must not be treated as covering 'srcextra' - that is a
        // different directory that happens to share a prefix.
        $this->writeComposerJson(['App\\' => 'src/']);
        mkdir($this->root . '/srcextra', 0o775, true);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['action' => 'srcextra']];\n"
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no psr-4 autoload entry covers 'srcextra'");

        Config::discover($this->root)->namespaceFor('action');
    }

    public function testNonMatchingPsr4EntriesNeverBecomeTheBestMatch(): void
    {
        // The long, non-matching directory must be skipped outright; if it were
        // allowed to seed the longest-match it would beat the real 'src/'.
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"Long\\\\":"averylongdirectory/","App\\\\":"src/"}}}'
        );

        $this->assertSame('App\Action', Config::discover($this->root)->namespaceFor('action'));
    }

    public function testScanKeepsLookingAfterRejectingAShorterMatch(): void
    {
        // Order matters: a longer match sits behind a shorter one, so the
        // rejection has to skip that entry rather than end the search.
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"Mid\\\\":"src/Action/","App\\\\":"src/",'
            . '"Deepest\\\\":"src/Action/Company/"}}}'
        );
        mkdir($this->root . '/src/Action/Company', 0o775, true);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['action' => 'src/Action/Company']];\n"
        );

        $this->assertSame('Deepest', Config::discover($this->root)->namespaceFor('action'));
    }

    public function testFirstDeclarationWinsWhenTwoPsr4DirectoriesTie(): void
    {
        // Equal-length matches: the earlier declaration keeps the win, so the
        // comparison has to reject an equal candidate, not just a shorter one.
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"First\\\\":"src/Action/","Second\\\\":"src/Action/"}}}'
        );

        $this->assertSame('First', Config::discover($this->root)->namespaceFor('action'));
    }

    public function testPsr4DirectoryIsFoundDespiteSurroundingSlashes(): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"App\\\\":"/src/"}}}'
        );

        $this->assertSame('App', Config::discover($this->root)->namespace());
    }

    public function testNoUsablePsr4EntryThrows(): void
    {
        // composer.json exists but every declared directory is missing, so
        // there is nothing to infer a root namespace from.
        $this->writeComposerJson(['Ghost\\' => 'missing/']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no crest.php and no usable psr-4 autoload entry found');

        Config::discover($this->root);
    }

    public function testPsr4TargetMayBeDeclaredAsAList(): void
    {
        // composer allows "App\\": ["src/", "lib/"]; the first entry wins.
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"App\\\\":["src/","lib/"]}}}'
        );

        $this->assertSame('App', Config::discover($this->root)->namespace());
    }

    public function testPsr4EntryWithAnEmptyTargetIsSkipped(): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"Empty\\\\":"","App\\\\":"src/"}}}'
        );

        $this->assertSame('App', Config::discover($this->root)->namespace());
    }

    public function testUnknownFlavorThrows(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['flavor' => 'nope'];\n"
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown flavor 'nope'");

        Config::discover($this->root);
    }

    public function testUnknownPathKeyThrows(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown path 'views'");

        Config::discover($this->root)->path('views');
    }
}
