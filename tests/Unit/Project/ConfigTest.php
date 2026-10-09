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

use function chdir;
use function file_put_contents;
use function getcwd;
use function mkdir;

final class ConfigTest extends TestCase
{
    use ScratchDirectory;

    private string $previousCwd = '';

    protected function setUp(): void
    {
        $this->makeScratchDirectory('project', 'src/Action');
        $this->previousCwd = (string) getcwd();
    }

    protected function tearDown(): void
    {
        chdir($this->previousCwd);
        $this->removeScratchDirectory();
    }

    public function testADirectoryThatDoesNotExistIsReported(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($this->root . '/missing is not a directory');

        Config::rootFor($this->root . '/missing');
    }

    public function testAdrGetsADefaultPathForEveryGeneratedArtifact(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->writeCrestPhp();

        $this->assertSame(
            [
                'action'     => $this->root . '/src/Action',
                'command'    => $this->root . '/src/Command',
                'middleware' => $this->root . '/src/Middleware',
                'provider'   => $this->root . '/src/Provider',
                'responder'  => $this->root . '/src/Responder',
            ],
            Config::discover($this->root)->paths()
        );
    }

    public function testAMissingExplicitConfigFileIsReported(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage($this->root . '/missing.php was not found');

        Config::discover($this->root, $this->root . '/missing.php');
    }

    public function testAnEmptyConfigFileMeansTheWalkUp(): void
    {
        $this->writeCrestPhp("['namespace' => 'Walked']");

        $this->assertSame('Walked', Config::discover($this->root . '/src/Action', '')->namespace());
    }

    public function testAnEmptyDirectoryMeansTheWorkingDirectory(): void
    {
        // `--directory="$DIR"` with an unset variable reads as absent.
        $this->writeCrestPhp();
        chdir($this->root . '/src');

        $this->assertSame($this->root, Config::discover('')->root());
    }

    public function testARelativeDirectoryIsWalkedFromWhereItPoints(): void
    {
        // From project a/, `../b` names b/, which has no crest.php. The walk
        // must not come back to a/.
        mkdir($this->root . '/a');
        mkdir($this->root . '/b');
        file_put_contents($this->root . '/a/crest.php', "<?php\n\nreturn [];\n");
        chdir($this->root . '/a');

        $this->assertNull(Config::file('../b'));
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

        $this->assertSame(Flavor::MVC, $config->flavor());
        $this->assertSame('Shop', $config->namespace());
        $this->assertSame($this->root . '/app/Handlers', $config->path('action'));
        $this->assertSame('Shop\Handlers', $config->namespaceFor('action'));
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

    public function testDeclaredNamespaceForIsStrippedOfSurroundingBackslashes(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['namespaces' => ['action' => '\\\\Shop\\\\Handlers\\\\']];\n"
        );

        $this->assertSame('Shop\Handlers', Config::discover($this->root)->namespaceFor('action'));
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

    public function testDefaultsApplyWhenCrestPhpStatesNothing(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->writeCrestPhp();

        $config = Config::discover($this->root);

        $this->assertSame(Flavor::ADR, $config->flavor());
        $this->assertSame('App', $config->namespace());
        $this->assertSame($this->root . '/src/Action', $config->path('action'));
        $this->assertSame($this->root, $config->root());
        $this->assertNull($config->bootstrap());
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

    public function testFileIsNullWithoutCrestPhp(): void
    {
        // This repository has no crest.php above tests/_output.
        $this->assertNull(Config::file($this->root));
    }

    public function testFileIsTheNearestCrestPhpAbove(): void
    {
        $this->writeCrestPhp();

        $this->assertSame($this->root . '/crest.php', Config::file($this->root . '/src/Action'));
    }

    public function testFirstDeclarationWinsWhenTwoPsr4DirectoriesTie(): void
    {
        // Equal-length matches: the earlier declaration keeps the win, so the
        // comparison has to reject an equal candidate, not just a shorter one.
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"First\\\\":"src/Action/","Second\\\\":"src/Action/"}}}'
        );
        $this->writeCrestPhp();

        $this->assertSame('First', Config::discover($this->root)->namespaceFor('action'));
    }

    public function testFlavorsWithoutGeneratorsGetNoDefaultPaths(): void
    {
        // ADR is the only flavor with generators. Offering the others its
        // directories would put locations in config:show for artifacts the
        // project has no command to write.
        $this->writeComposerJson(['App\\' => 'src/']);

        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn ['flavor' => 'cli'];\n");
        $config = Config::discover($this->root);
        $this->assertSame([], $config->paths());

        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn ['flavor' => 'mvc'];\n");
        $config = Config::discover($this->root);
        $this->assertSame([], $config->paths());
    }

    public function testNamespaceForDerivesFromThePsr4Pairing(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->writeCrestPhp();

        $this->assertSame('App\Action', Config::discover($this->root)->namespaceFor('action'));
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

    public function testNonMatchingPsr4EntriesNeverBecomeTheBestMatch(): void
    {
        // The long, non-matching directory must be skipped outright; if it were
        // allowed to seed the longest-match it would beat the real 'src/'.
        file_put_contents(
            $this->root . '/composer.json',
            '{"autoload":{"psr-4":{"Long\\\\":"averylongdirectory/","App\\\\":"src/"}}}'
        );
        $this->writeCrestPhp();

        $this->assertSame('App\Action', Config::discover($this->root)->namespaceFor('action'));
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

    public function testRootIsTheFolderOfTheNearestCrestPhp(): void
    {
        $this->writeCrestPhp();

        $this->assertSame($this->root, Config::rootFor($this->root . '/src/Action'));
    }

    public function testRootWithoutCrestPhpStopsWithTheInitHint(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no crest.php found; run 'crest init'");

        Config::rootFor($this->root);
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

    public function testTheConfigFileNamesTheRoot(): void
    {
        mkdir($this->root . '/elsewhere');
        file_put_contents($this->root . '/elsewhere/crest.php', "<?php\n\nreturn [];\n");

        $this->assertSame(
            $this->root . '/elsewhere',
            Config::rootFor($this->root, $this->root . '/elsewhere/crest.php')
        );
    }

    public function testTheRuntimeComesFromCrestPhp(): void
    {
        $this->writeCrestPhp("['runtime' => ['type' => 'docker', 'service' => 'web']]");

        $runtime = Config::discover($this->root)->runtime();

        $this->assertTrue($runtime->isDocker());
        $this->assertSame('web', $runtime->service);
    }

    public function testTheSourceIsTheCrestPhp(): void
    {
        $this->writeCrestPhp();

        $this->assertSame($this->root . '/crest.php', Config::discover($this->root)->source());
    }

    public function testTrailingSlashOnTheDirectoryIsIgnored(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->writeCrestPhp();

        $config = Config::discover($this->root . '/');

        $this->assertSame($this->root, $config->root());
        $this->assertSame($this->root . '/src/Action', $config->path('action'));
        $this->assertSame($this->root . '/crest.php', $config->source());
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
        $this->writeCrestPhp();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown path 'views'");

        Config::discover($this->root)->path('views');
    }

    public function testWithoutARuntimeKeyTheProjectRunsOnTheHost(): void
    {
        $this->writeCrestPhp();

        $this->assertFalse(Config::discover($this->root)->runtime()->isDocker());
    }

    public function testWithoutCrestPhpItStopsWithTheInitHint(): void
    {
        // composer.json alone does not make a crest project.
        $this->writeComposerJson(['App\\' => 'src/']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no crest.php found; run 'crest init'");

        Config::discover($this->root);
    }
}
