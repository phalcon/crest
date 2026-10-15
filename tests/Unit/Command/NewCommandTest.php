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

namespace Crest\Tests\Unit\Command;

use Closure;
use Crest\Command\NewCommand;
use Crest\Command\Stub\PublishCommand;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Process\Runner;
use Crest\Tests\Support\GeneratesInAScratchProject;
use Crest\Tests\Support\Process\FakeRunner;
use Crest\Tests\Support\RunsACommandDirectly;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function count;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function fileperms;
use function json_decode;
use function mkdir;
use function preg_match;
use function sort;
use function sprintf;
use function str_replace;
use function strlen;
use function substr;
use function var_export;

use const JSON_THROW_ON_ERROR;
use const PHP_BINARY;
use const PHP_EOL;

final class NewCommandTest extends TestCase
{
    use GeneratesInAScratchProject;
    use RunsACommandDirectly;

    protected function setUp(): void
    {
        $this->startScratchProject('new');
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusablePhpVersions(): iterable
    {
        // Each one passes version_compare(), but no `php:<version>-cli` image
        // has that tag.
        yield 'leading zero in the major' => ['08.4'];
        yield 'leading zero in the minor' => ['8.01'];
        yield 'trailing newline' => ["8.4\n"];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function variants(): iterable
    {
        yield 'v5' => ['v5'];
        yield 'v6' => ['v6'];
    }

    public function testABadNewKeyInASharedCopyNamesTheSharedCopy(): void
    {
        // stub:publish puts project-config into the shared folder. The error
        // names the copy that new used, not a copy in the adr folder.
        $this->publishSteps(['composer install'], Stub::SHARED);

        $status = $this->runCommand(['my-app']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            'fix ' . Stub::overridePath($this->root, Stub::SHARED, 'project-config') . ' and run new again',
            $this->readStderr()
        );
    }

    public function testABadNewKeyStopsBeforeTheFirstCommand(): void
    {
        // Entry 1 names a program that does not exist. If it ran, the error
        // would be 'was not found'. new checks the whole key first. The error
        // names the stub, because a change to the written crest.php does
        // nothing.
        $this->publishSteps([['crest-no-such-program'], 'composer install']);

        $status = $this->runCommand(['my-app']);
        $target = $this->root . '/my-app';

        $this->assertSame(1, $status);
        $this->assertSame('Created ' . $target . '/' . PHP_EOL . PHP_EOL, $this->readStdout());
        $this->assertSame(
            'crest: ' . $target . "/crest.php: 'new' entry 2 in crest.php must be a list of strings, "
            . "e.g. ['crest', 'up']; the files in " . $target . '/ stay; fix '
            . Stub::overridePath($this->root, 'adr', 'project-config') . ' and run new again with --force'
            . PHP_EOL,
            $this->readStderr()
        );
        $this->assertFileExists($target . '/composer.json');
    }

    public function testAConfigCopyWithAFixedFlavorStopsNewAfterTheWrite(): void
    {
        // A shared copy that a team edited: the flavor is fixed, not the
        // placeholder. The written crest.php then names the wrong flavor.
        $override = $this->publishConfig(Stub::SHARED, "'{{ flavor }}'", "'adr'");

        $status = $this->runCommand(['my-app', '--flavor', 'mvc']);
        $target = $this->root . '/my-app';

        $this->assertSame(1, $status);
        $this->assertSame(
            'crest: ' . $target . "/crest.php: the flavor is 'adr', not 'mvc'; keep 'flavor' => '{{ flavor }}' "
            . 'in the stub; the files in ' . $target . '/ stay; fix ' . $override
            . ' and run new again with --force' . PHP_EOL,
            $this->readStderr()
        );
        $this->assertFileExists($target . '/crest.php');
    }

    public function testAConfigCopyWithoutAFlavorKeyIsAnAdrProject(): void
    {
        // crest reads a crest.php with no flavor key as adr. An ADR copy from
        // before the key still works.
        $this->publishConfig('adr', "    'flavor'    => '{{ flavor }}',\n", '');

        $this->assertSame(0, $this->runCommand(['my-app']));
        $this->assertStringNotContainsString("'flavor'", $this->read('crest.php'));
    }

    public function testACorrectSharedConfigCopyGivesTheChosenFlavor(): void
    {
        $this->publishConfig(Stub::SHARED, '', '');

        $this->assertSame(0, $this->runCommand(['my-app', '--flavor', 'mvc']));
        $this->assertStringContainsString("'flavor'    => 'mvc',", $this->read('crest.php'));
    }

    public function testACrestPhpThatDoesNotParseStopsAfterTheWrite(): void
    {
        // new reads crest.php back for its `new` key. A published copy that
        // PHP cannot parse stops new here, not in a later command.
        $override = Stub::overridePath($this->root, 'adr', 'project-config');

        mkdir(dirname($override), 0o775, true);
        file_put_contents($override, "<?php\n\nreturn [\n");

        $status = $this->runCommand(['my-app']);
        $target = $this->root . '/my-app';

        $this->assertSame(1, $status);
        $this->assertSame(
            'crest: ' . $target . "/crest.php: Unclosed '[' on line 3; the files in " . $target . '/ stay; fix '
            . $override . ' and run new again with --force' . PHP_EOL,
            $this->readStderr()
        );
        $this->assertFileExists($target . '/crest.php');
    }

    public function testAMissingProgramStopsTheList(): void
    {
        $this->publishSteps([['crest-no-such-program']]);

        $status = $this->runCommand(['my-app']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "crest: 'crest-no-such-program' was not found; install it or add it to the PATH",
            $this->readStderr()
        );
        $this->assertFileExists($this->root . '/my-app/composer.json');
    }

    public function testANamespacedRootReachesEveryFile(): void
    {
        $status = $this->runCommand(['my-app', '--namespace', 'Acme\\Shop']);

        $this->assertSame(0, $status);
        $this->assertSame(['psr-4' => ['Acme\\Shop\\' => 'src/']], $this->composer()['autoload']);
        $this->assertStringContainsString("'namespace' => 'Acme\\Shop',", $this->read('crest.php'));
        $this->assertStringContainsString(
            "'bootstrap' => Acme\\Shop\\AppFront::class,",
            $this->read('crest.php')
        );
        $this->assertStringContainsString("use Acme\\Shop\\AppFront;\n", $this->read('public/index.php'));
        $this->assertStringContainsString("namespace Acme\\Shop;\n", $this->read('src/AppFront.php'));
        $this->assertStringContainsString(
            "->setBaseNamespace('Acme\\Shop\\Action')",
            $this->read('src/AppFront.php')
        );
        $this->assertStringContainsString(
            "namespace Acme\\Shop\\Action;\n",
            $this->read('src/Action/Get.php')
        );
    }

    public function testAnEmptyDirectoryOptionMeansTheWorkingDirectory(): void
    {
        // `--directory="$DIR"` with an unset variable. As with every other
        // option, empty reads as absent. Not: the project in the filesystem
        // root.
        $status = $this->runInWorkingDirectory(['my-app', '--directory=']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/my-app/composer.json');
        $this->assertStringStartsWith('Created my-app/' . PHP_EOL, $this->readStdout());
    }

    public function testAnEmptyExistingDirectoryIsUsed(): void
    {
        mkdir($this->root . '/my-app');

        $status = $this->runCommand(['my-app']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/my-app/composer.json');
    }

    public function testAnMvcProjectGetsEveryFile(): void
    {
        $this->assertSame(0, $this->runCommand(['my-app', '--flavor', 'mvc']));

        $prefix = $this->root . '/my-app/';
        $files  = [];

        /** @var SplFileInfo $file */
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($prefix, FilesystemIterator::SKIP_DOTS)
            ) as $file
        ) {
            $files[] = substr($file->getPathname(), strlen($prefix));
        }

        sort($files);

        $this->assertSame(
            [
                '.env',
                '.gitignore',
                '.htrouter.php',
                'README.md',
                'composer.json',
                'crest',
                'crest.php',
                'docker-compose.yml',
                'public/index.php',
                'resources/docker/Dockerfile',
                'src/AppFront.php',
                'src/Controllers/Base/CrestControllerBase.php',
                'src/Controllers/IndexController.php',
                'src/Providers/DispatcherProvider.php',
                'src/Providers/ViewProvider.php',
                'themes/default/index.phtml',
                'themes/default/index/index.phtml',
            ],
            $files
        );
    }

    public function testAnOldPublishedConfigStubStopsBeforeAnyWrite(): void
    {
        // A copy from before the runtime key. Without the stop, crest.php
        // has no runtime, and the runtime answer is lost.
        $override = Stub::overridePath($this->root, 'adr', 'project-config');

        mkdir(dirname($override), 0o775, true);
        file_put_contents(
            $override,
            "<?php\n\nreturn [\n    'namespace' => '{{ namespace }}',\n"
            . "    'paths'     => ['action' => '{{ actionPath }}'],\n];\n"
        );

        $status = $this->runCommand(['my-app']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString($override . ' has no value for {{ actionPath }}', $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testANonEmptyDirectoryIsRefused(): void
    {
        mkdir($this->root . '/my-app');
        file_put_contents($this->root . '/my-app/notes.txt', 'mine');

        $status = $this->runCommand(['my-app']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            $this->root . '/my-app exists and is not empty; pass --force to write into it',
            $this->readStderr()
        );
        $this->assertFileDoesNotExist($this->root . '/my-app/composer.json');
    }

    public function testAnOptionSkipsItsQuestion(): void
    {
        // No runtime option is given, so only the runtime questions are asked.
        $this->answers("docker\nweb\n");

        $status = $this->runCommand(
            ['my-app', '--flavor', 'adr', '--namespace', 'Acme', '--php', '8.2', '--phalcon', 'v6'],
            true
        );

        $this->assertSame(0, $status);
        $this->assertStringNotContainsString('Root namespace', $this->readStderr());
        $this->assertStringContainsString(
            "'runtime'   => ['type' => 'docker', 'service' => 'web'],",
            $this->read('crest.php')
        );
        $this->assertStringContainsString("\n  web:\n", $this->read('docker-compose.yml'));
    }

    public function testAnUnknownFlavorIsRejected(): void
    {
        $status = $this->runCommand(['my-app', '--flavor', 'cli']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("unknown flavor 'cli'; expected adr or mvc", $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testAnUnknownPhalconVersionIsRejected(): void
    {
        $status = $this->runCommand(['my-app', '--phalcon', 'v7']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "unknown Phalcon version 'v7'; expected v5 or v6",
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testAnUnknownRuntimeIsRejected(): void
    {
        $status = $this->runCommand(['my-app', '--runtime', 'podman']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("unknown runtime 'podman'; expected host or docker", $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testAnUnknownTemplateEngineIsRejected(): void
    {
        $status = $this->runCommand(['my-app', '--flavor', 'mvc', '--template-engine', 'volt']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "unknown template engine 'volt'; expected phtml",
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testAnUnusableAnswerIsAskedAgain(): void
    {
        $this->answers("\n1bad\nShop\n\n\n\n\n");

        $this->assertSame(0, $this->runCommand(['my-app'], true));
        $this->assertStringContainsString("'1bad' is not a usable namespace", $this->readStderr());
        $this->assertStringContainsString("'namespace' => 'Shop',", $this->read('crest.php'));
    }

    public function testAnUnusableNamespaceIsRejected(): void
    {
        $status = $this->runCommand(['my-app', '--namespace', 'my-app']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "'my-app' is not a usable namespace; expected something like 'App' or 'Acme\\Shop'",
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    /**
     * @dataProvider unusablePhpVersions
     */
    public function testAnUnusablePhpVersionIsRejected(string $version): void
    {
        $status = $this->runCommand(['my-app', '--php', $version]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            sprintf("'%s' is not a PHP version; expected major.minor, e.g. 8.4", $version),
            $this->readStderr()
        );
    }

    public function testAnUnusableServiceIsRejected(): void
    {
        $status = $this->runCommand(['my-app', '--service', 'bad!']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("'bad!' is not a usable service name", $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testAPhpVersionBelowTheFloorIsRejected(): void
    {
        $status = $this->runCommand(['my-app', '--php', '8.0']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            'PHP 8.0 is too old; the generated code needs 8.1 or later',
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testAPhpVersionWithAPatchIsRejected(): void
    {
        $status = $this->runCommand(['my-app', '--php', '8.4.1']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "'8.4.1' is not a PHP version; expected major.minor, e.g. 8.4",
            $this->readStderr()
        );
    }

    public function testAProjectNameMustStartWithALetterOrDigit(): void
    {
        $status = $this->runCommand(['_app']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "'_app' is not a usable project name; expected letters, digits, '-' and '_', "
            . 'starting with a letter or digit',
            $this->readStderr()
        );
    }

    public function testAProjectNameThatIsAPathIsRejected(): void
    {
        $status = $this->runCommand(['../elsewhere']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "'../elsewhere' is not a usable project name",
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist(dirname($this->root) . '/elsewhere');
    }

    public function testAProjectNameWithATrailingNewlineIsRejected(): void
    {
        $status = $this->runCommand(["my-app\n"]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("'my-app\n' is not a usable project name", $this->readStderr());
    }

    public function testAProjectStubFromStubPublishIsUsed(): void
    {
        // stub:publish must write where `new` reads, with the same
        // --directory. That directory is not a project: it has no crest.php
        // and no composer.json.
        $directory = $this->root . '/work';

        mkdir($directory);

        $published = $this->runThroughKernel(
            'stub:publish',
            PublishCommand::class,
            ['project-readme', '--directory', $directory]
        );

        $this->assertSame(0, $published);

        file_put_contents(Stub::overridePath($directory, 'adr', 'project-readme'), "custom {{ project }}\n");

        $status = $this->runThroughKernel('new', NewCommand::class, ['my-app', '--directory', $directory]);

        $this->assertSame(0, $status);
        $this->assertSame("custom my-app\n", (string) file_get_contents($directory . '/my-app/README.md'));
    }

    public function testAPublishedLauncherIsExecutable(): void
    {
        $override = Stub::overridePath($this->root, 'adr', 'project-launcher');

        mkdir(dirname($override), 0o775, true);
        file_put_contents($override, "#!/bin/sh\n\necho {{ service }}\n");

        $status = $this->runCommand(['my-app']);

        $this->assertSame(0, $status);
        $this->assertSame("#!/bin/sh\n\necho app\n", $this->read('crest'));
        $this->assertSame(0o755, fileperms($this->root . '/my-app/crest') & 0o777);
    }

    public function testAPublishedProjectStubInTheParentDirectoryIsUsed(): void
    {
        $override = Stub::overridePath($this->root, 'adr', 'project-readme');

        mkdir(dirname($override), 0o775, true);
        file_put_contents($override, "custom {{ project }}\n");

        $status = $this->runCommand(['my-app']);

        $this->assertSame(0, $status);
        $this->assertSame("custom my-app\n", $this->read('README.md'));
    }

    public function testAPublishedStubWithAnUnknownPlaceholderStopsBeforeAnyWrite(): void
    {
        // project-front renders last. Without the render pass before the first
        // write, the files before it are already on disk when it fails.
        $override = Stub::overridePath($this->root, 'adr', 'project-front');

        mkdir(dirname($override), 0o775, true);
        file_put_contents($override, "{{ old }}\n");

        $status = $this->runCommand(['my-app']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString($override . ' has no value for {{ old }}', $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testAPublishedStubWithLiteralsStillWorks(): void
    {
        // A copy published before the layout placeholders existed. `new`
        // reads overrides from the directory that the project goes into.
        $path = Stub::overridePath($this->root, 'adr', Stub::PROJECT_PREFIX . 'env');

        mkdir(dirname($path), 0o775, true);
        file_put_contents($path, "APP_PORT=8080\n");

        $status = $this->runCommand(['my-app']);

        $this->assertSame(0, $status);
        $this->assertSame("APP_PORT=8080\n", $this->read('.env'));
    }

    public function testAStepRunsForRealAndPassesOnItsStatus(): void
    {
        // The kernel creates the command with its default runner, ShellRunner.
        // The step prints nothing, so the output of the test run stays clean.
        $this->publishSteps([[PHP_BINARY, '-r', 'exit(3);']]);

        $status = $this->runCommand(['my-app']);

        $this->assertSame(3, $status);
        $this->assertStringContainsString('failed with exit status 3', $this->readStderr());
    }

    public function testATargetThatIsAFileIsRefused(): void
    {
        file_put_contents($this->root . '/my-app', 'not a directory');

        $status = $this->runCommand(['my-app']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            $this->root . '/my-app exists and is not a directory',
            $this->readStderr()
        );
    }

    public function testATemplateEngineErrorComesBeforeTheOtherQuestions(): void
    {
        // The flavor and the engine come first: a refused engine asks no
        // more questions.
        $this->answers("\n\n\n\n\n\n");

        $status = $this->runCommand(['my-app', '--template-engine', 'phtml'], true);

        $this->assertSame(1, $status);
        $this->assertStringStartsWith('Flavor (adr, mvc) [adr]: ', $this->readStderr());
        $this->assertStringNotContainsString('Root namespace', $this->readStderr());
    }

    public function testATemplateEngineForAnAdrProjectIsRejected(): void
    {
        $status = $this->runCommand(['my-app', '--template-engine', 'phtml']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "the 'adr' flavor has no views; remove --template-engine",
            $this->readStderr()
        );
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testAV6DockerfileKeepsTheV5ConstraintInItsComments(): void
    {
        // The v5 lines are comments in a v6 project. They must still name the
        // extension that v5 needs, for a switch back to v5.
        $this->runCommand(['my-app', '--phalcon=v6']);

        $this->assertStringContainsString(
            '# pie install --no-interaction phalcon/cphalcon:^5.18',
            $this->read('resources/docker/Dockerfile')
        );
    }

    public function testAWordWithASpaceIsShownInQuotes(): void
    {
        $this->publishSteps([['php', '-r', 'echo 1;']]);

        $this->runWith(new FakeRunner());

        $this->assertStringContainsString("Running: php -r 'echo 1;'" . PHP_EOL, $this->readStdout());
    }

    public function testComposerJsonRequiresTheExtensionByDefault(): void
    {
        $this->runCommand(['my-app']);

        $this->assertSame(
            [
                'type'        => 'project',
                'require'     => ['php' => '>=8.4', 'ext-phalcon' => '^5.18'],
                'require-dev' => ['phalcon/crest' => 'dev-master'],
                'autoload'    => ['psr-4' => ['App\\' => 'src/']],
                'config'      => ['sort-packages' => true],
            ],
            $this->composer()
        );
    }

    public function testComposerJsonRequiresThePackageForV6(): void
    {
        $this->runCommand(['my-app', '--phalcon', 'v6']);

        $this->assertSame(
            ['php' => '>=8.4', 'phalcon/phalcon' => '^6.0@RC'],
            $this->composer()['require']
        );
    }

    public function testDefinitionDescribesBothFlavors(): void
    {
        $this->assertSame('Create an ADR or MVC project', (new NewCommand())->define()->getDescription());
    }

    public function testDefinitionNamesItselfNew(): void
    {
        $this->assertSame('new', (new NewCommand())->define()->getName());
    }

    public function testEachRunningLineComesBeforeItsCommand(): void
    {
        // The output of a step goes directly to the terminal. Thus new must
        // write the Running: line before the step runs, and no line for a
        // step after a failure.
        $this->publishSteps([['composer', 'validate'], ['composer', 'install'], ['crest', 'up']]);

        $runner = new class ($this->readStdout(...)) implements Runner {
            /** @var list<string> What stdout holds at each call. */
            public array $seen = [];

            /**
             * @param Closure(): string $stdout
             */
            public function __construct(private readonly Closure $stdout)
            {
            }

            public function run(array $command, ?string $directory = null, array $environment = []): int
            {
                $this->seen[] = ($this->stdout)();

                return 1 === count($this->seen) ? 0 : 2;
            }
        };

        $this->handleDirectly(new NewCommand($runner), ['my-app', '--directory', $this->root]);

        $this->assertCount(2, $runner->seen);
        $this->assertStringEndsWith('Running: composer validate' . PHP_EOL, $runner->seen[0]);
        $this->assertStringEndsWith('Running: composer install' . PHP_EOL, $runner->seen[1]);
        $this->assertStringEndsWith('Running: composer install' . PHP_EOL, $this->readStdout());
    }

    public function testEveryProjectFileIsWritten(): void
    {
        $status = $this->runCommand(['my-app']);

        $this->assertSame(0, $status);

        foreach (
            [
                'composer.json',
                'crest.php',
                'crest',
                '.env',
                '.gitignore',
                '.htrouter.php',
                'README.md',
                'docker-compose.yml',
                'resources/docker/Dockerfile',
                'public/index.php',
                'src/AppFront.php',
                'src/Action/Get.php',
            ] as $path
        ) {
            $this->assertFileExists($this->root . '/my-app/' . $path);
        }
    }

    public function testForceOverwritesAPreviouslyGeneratedProject(): void
    {
        $this->runCommand(['my-app']);
        file_put_contents($this->root . '/my-app/composer.json', 'stale');

        $status = $this->runCommand(['my-app', '--force']);

        $this->assertSame(0, $status);
        $this->assertSame('project', $this->composer()['type']);
    }

    public function testForceReplacesTheBaseOfAnMvcProject(): void
    {
        // --force overwrites the files with the same names, also a base
        // without the marker. make:controller keeps such a base; new does not.
        $this->runCommand(['my-app', '--flavor', 'mvc']);
        file_put_contents(
            $this->root . '/my-app/src/Controllers/Base/CrestControllerBase.php',
            "<?php\n// mine\n"
        );

        $this->assertSame(0, $this->runCommand(['my-app', '--flavor', 'mvc', '--force']));
        $this->assertStringContainsString(
            'Generated by crest.',
            $this->read('src/Controllers/Base/CrestControllerBase.php')
        );
    }

    public function testForceRunsTheListOfTheNewCrestPhp(): void
    {
        // The second run overwrites crest.php. new must read the new file,
        // not the one that the first run has read.
        $this->runCommand(['my-app']);
        $this->publishSteps([['composer', 'install']]);

        $runner = new FakeRunner();

        $this->runWith($runner, ['--force']);

        $this->assertSame([[['composer', 'install'], $this->root . '/my-app']], $runner->calls);
    }

    public function testForceWritesIntoANonEmptyDirectory(): void
    {
        mkdir($this->root . '/my-app');
        file_put_contents($this->root . '/my-app/notes.txt', 'mine');

        $status = $this->runCommand(['my-app', '--force']);

        $this->assertSame(0, $status);
        $this->assertSame('mine', $this->read('notes.txt'));
        $this->assertFileExists($this->root . '/my-app/composer.json');
    }

    public function testHelpDoesNotCallTheDirectoryAProjectRoot(): void
    {
        // `new` has no project yet. The option replaces the working directory,
        // and the project goes into it.
        $status = $this->runInWorkingDirectory(['--help']);

        $this->assertSame(0, $status);
        $this->assertMatchesRegularExpression(
            '/--directory +Directory to use instead of the working directory\R/',
            $this->readStdout()
        );
    }

    public function testNameArgumentIsRequired(): void
    {
        $status = $this->runCommand([]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("missing required argument 'name'", $this->readStderr());
    }

    /**
     * @dataProvider variants
     */
    public function testNoPlaceholderIsLeftInAnMvcProject(string $variant): void
    {
        $this->runCommand(['my-app', '--flavor', 'mvc', '--phalcon', $variant]);

        $this->assertNoPlaceholderIn($this->root . '/my-app');
    }

    /**
     * @dataProvider variants
     */
    public function testNoPlaceholderIsLeftInAnyWrittenFile(string $variant): void
    {
        // ProjectStubsTest renders the stubs with its own values. This test
        // uses the values that NewCommand supplies, so a key that NewCommand
        // does not supply fails here.
        $this->runCommand(['my-app', '--phalcon', $variant]);

        $this->assertNoPlaceholderIn($this->root . '/my-app');
    }

    public function testOneCommandIsCountedInTheSingular(): void
    {
        $this->publishSteps([['composer', 'install']]);

        $this->runWith(new FakeRunner());

        $this->assertStringEndsWith(
            "Ran 1 command from the 'new' key of crest.php" . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testSurroundingBackslashesAreDropped(): void
    {
        $this->runCommand(['my-app', '--namespace', '\\Acme\\']);

        $this->assertSame(['psr-4' => ['Acme\\' => 'src/']], $this->composer()['autoload']);
    }

    public function testTheClosingOutputShowsBothWaysToRunIt(): void
    {
        $this->runCommand(['my-app']);

        $target = $this->root . '/my-app';

        $this->assertSame(
            'Created ' . $target . '/' . PHP_EOL
            . PHP_EOL
            . 'Nothing runs it yet. With docker:' . PHP_EOL
            . PHP_EOL
            . '    cd ' . $target . PHP_EOL
            . '    ./crest up' . PHP_EOL
            . '    ./crest install' . PHP_EOL
            . PHP_EOL
            . 'Or with PHP and composer on the host:' . PHP_EOL
            . PHP_EOL
            . '    cd ' . $target . PHP_EOL
            . '    composer install' . PHP_EOL
            . '    crest serve' . PHP_EOL
            . PHP_EOL
            . 'Then GET / answers from src/Action/Get.php' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testTheDirectoryOptionNamesWhereTheProjectGoes(): void
    {
        // Not the working directory, and with a trailing slash: the project
        // must land in nested/, and the report must not show a double slash.
        $status = $this->runInWorkingDirectory(['my-app', '--directory', $this->root . '/nested/']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/nested/my-app/composer.json');
        $this->assertStringStartsWith(
            'Created ' . $this->root . '/nested/my-app/' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testTheDockerfileAcceptsIdsThatTheImageAlreadyUses(): void
    {
        // GID 20 is the main group on macOS, and the Debian image already has
        // it. Without -o, groupadd fails and the build stops.
        $this->runCommand(['my-app']);

        $dockerfile = $this->read('resources/docker/Dockerfile');

        $this->assertStringContainsString('groupadd -o -g "${GID}" "${GROUP}"', $dockerfile);
        $this->assertStringContainsString(
            'useradd -l -m -o -u "${UID}" -g "${GID}" -s /bin/bash "${USER}"',
            $dockerfile
        );
    }

    public function testTheDockerfileCommentsTheExtensionOutForV6(): void
    {
        $this->runCommand(['my-app', '--phalcon', 'v6']);

        $dockerfile = $this->read('resources/docker/Dockerfile');

        $this->assertStringContainsString("# Phalcon v6.\n", $dockerfile);
        $this->assertStringContainsString("\n# pie install --no-interaction phalcon/cphalcon:^5.18\n", $dockerfile);
        $this->assertStringNotContainsString("\npie install", $dockerfile);
    }

    public function testTheDockerfileInstallsTheExtensionForV5(): void
    {
        $this->runCommand(['my-app']);

        $dockerfile = $this->read('resources/docker/Dockerfile');

        $this->assertStringContainsString("# Phalcon v5.\n", $dockerfile);
        $this->assertStringContainsString("\npie install --no-interaction phalcon/cphalcon:^5.18\n", $dockerfile);
        $this->assertStringContainsString(
            'CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", ".htrouter.php"]',
            $dockerfile
        );
    }

    public function testTheDockerfileInstallsTheExtensionVersionThatComposerRequires(): void
    {
        // NewCommand writes the constraint into composer.json and, through the
        // extensionConstraint placeholder, into the Dockerfile. They must
        // agree.
        $this->runCommand(['my-app']);

        preg_match(
            '#^pie install --no-interaction phalcon/cphalcon:(\S+)$#m',
            $this->read('resources/docker/Dockerfile'),
            $matches
        );

        $this->assertSame(
            ['php' => '>=8.4', 'ext-phalcon' => $matches[1] ?? ''],
            $this->composer()['require']
        );
    }

    public function testTheDockerFilesCarryTheProjectName(): void
    {
        $this->runCommand(['my-app']);

        $this->assertStringContainsString("PROJECT_PREFIX=my-app\n", $this->read('.env'));
        $this->assertStringContainsString(
            'container_name: ${PROJECT_PREFIX:-my-app}-app',
            $this->read('docker-compose.yml')
        );
        $this->assertStringStartsWith("# my-app\n", $this->read('README.md'));
    }

    public function testTheEndOfInputWritesNothing(): void
    {
        // Ctrl+D at the first question.
        $this->assertSame(1, $this->runCommand(['my-app'], true));
        $this->assertStringEndsWith('crest: no answer; input ended' . PHP_EOL, $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/my-app');
    }

    public function testTheFirstFailureStopsTheList(): void
    {
        $this->publishSteps([['composer', 'validate'], ['composer', 'install'], ['crest', 'up']]);

        $runner = new FakeRunner(0, 2, 0);
        $status = $this->runWith($runner);

        $this->assertSame(2, $status);
        $this->assertCount(2, $runner->calls);
        $this->assertSame(
            "crest: 'composer install' failed with exit status 2; the files in "
            . $this->root . '/my-app/ stay' . PHP_EOL,
            $this->readStderr()
        );
        $this->assertStringNotContainsString('Ran ', $this->readStdout());
        $this->assertFileExists($this->root . '/my-app/composer.json');
    }

    public function testTheFlavorIsCaseInsensitive(): void
    {
        $this->runCommand(['my-app', '--flavor', 'MVC']);

        $this->assertStringContainsString("'flavor'    => 'mvc',", $this->read('crest.php'));
    }

    public function testTheFlavorIsTheFirstQuestion(): void
    {
        // Flavor, namespace, PHP version, Phalcon version, runtime, service.
        // MVC has one engine, so there is no engine question.
        $this->answers("mvc\n\n\n\n\n\n");

        $this->assertSame(0, $this->runCommand(['my-app'], true));
        $this->assertStringStartsWith('Flavor (adr, mvc) [adr]: Root namespace [App]: ', $this->readStderr());
        $this->assertStringNotContainsString('Template engine', $this->readStderr());
        $this->assertStringContainsString("'flavor'    => 'mvc',", $this->read('crest.php'));
    }

    public function testTheFrontControllerIsRendered(): void
    {
        // Asserted whole: this is generated code nobody reviews.
        $this->runCommand(['my-app']);

        $this->assertSame(
            "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App;\n"
            . "\n"
            . "use Phalcon\\ADR\\Application;\n"
            . "use Phalcon\\ADR\\Front\\AbstractHttpFront;\n"
            . "use Phalcon\\Container\\Container;\n"
            . "use Phalcon\\Contracts\\ADR\\Application as ApplicationInterface;\n"
            . "\n"
            . "/**\n"
            . " * The front controller. public/index.php runs it for every request, and\n"
            . " * crest boots it for container:list and event:list.\n"
            . " *\n"
            . " * Add services by overriding registerProviders(); `crest make:provider`\n"
            . " * prints the override to paste here.\n"
            . " */\n"
            . "final class AppFront extends AbstractHttpFront\n"
            . "{\n"
            . "    protected function getApplication(Container \$container): ApplicationInterface\n"
            . "    {\n"
            . "        return (new Application(\$container))\n"
            . "            ->setBaseNamespace('App\\Action')\n"
            . "            ->setActionDirectory(\$this->projectRoot . '/src/Action');\n"
            . "    }\n"
            . "}\n",
            $this->read('src/AppFront.php')
        );
    }

    public function testTheGeneratedConfigStatesEveryKey(): void
    {
        $this->runCommand(['my-app']);

        // The docblock comes before declare(): PSR-12 puts the file docblock
        // first in the header.
        $this->assertSame(
            "<?php\n"
            . "\n"
            . "/**\n"
            . " * Crest configuration. Run `crest config:show` to see every value and where\n"
            . " * it came from.\n"
            . " */\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "return [\n"
            . "    'flavor'    => 'adr',\n"
            . "    'namespace' => 'App',\n"
            . "    'bootstrap' => App\\AppFront::class,\n"
            . "    'paths'     => [\n"
            . "        'action'     => 'src/Action',\n"
            . "        'command'    => 'src/Command',\n"
            . "        'middleware' => 'src/Middleware',\n"
            . "        'provider'   => 'src/Provider',\n"
            . "        'responder'  => 'src/Responder',\n"
            . "    ],\n"
            . "    'runtime'   => ['type' => 'docker', 'service' => 'app'],\n"
            . "];\n",
            $this->read('crest.php')
        );
    }

    public function testTheGitignoreKeepsVendorOut(): void
    {
        $this->runCommand(['my-app']);

        $this->assertSame("/vendor/\n", $this->read('.gitignore'));
    }

    public function testTheLauncherIsExecutable(): void
    {
        $this->runCommand(['my-app']);

        $this->assertSame(0o755, fileperms($this->root . '/my-app/crest') & 0o777);
    }

    public function testTheMvcConfigStatesEveryKey(): void
    {
        $this->runCommand(['my-app', '--flavor', 'mvc']);

        $this->assertSame(
            "<?php\n"
            . "\n"
            . "/**\n"
            . " * Crest configuration. Run `crest config:show` to see every value and where\n"
            . " * it came from.\n"
            . " */\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "return [\n"
            . "    'flavor'    => 'mvc',\n"
            . "    'namespace' => 'App',\n"
            . "    'bootstrap' => App\\AppFront::class,\n"
            . "    'paths'     => [\n"
            . "        'command'    => 'src/Command',\n"
            . "        'controller' => 'src/Controllers',\n"
            . "        'model'      => 'src/Models',\n"
            . "        'provider'   => 'src/Providers',\n"
            . "        'views'      => 'themes/default',\n"
            . "    ],\n"
            . "    'runtime'   => ['type' => 'docker', 'service' => 'app'],\n"
            . "];\n",
            $this->read('crest.php')
        );
    }

    public function testTheMvcFrontControllerIsRendered(): void
    {
        // Asserted whole: this is generated code nobody reviews.
        $this->runCommand(['my-app', '--flavor', 'mvc']);

        $this->assertSame(
            "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App;\n"
            . "\n"
            . "use App\\Providers\\DispatcherProvider;\n"
            . "use App\\Providers\\ViewProvider;\n"
            . "use Phalcon\\Di\\DiInterface;\n"
            . "use Phalcon\\Di\\FactoryDefault;\n"
            . "use Phalcon\\Di\\ServiceProviderInterface;\n"
            . "use Phalcon\\Http\\ResponseInterface;\n"
            . "use Phalcon\\Mvc\\Application;\n"
            . "\n"
            . "/**\n"
            . " * The front controller. public/index.php runs it for every request,\n"
            . " * and crest.php names it, so that crest can boot it.\n"
            . " *\n"
            . " * Add a service with a provider in providers(). `crest make:provider` writes\n"
            . " * the provider, and prints the line to add here.\n"
            . " */\n"
            . "final class AppFront\n"
            . "{\n"
            . "    public function __construct(private readonly string \$projectRoot)\n"
            . "    {\n"
            . "    }\n"
            . "\n"
            . "    /**\n"
            . "     * The container with the services of the application.\n"
            . "     */\n"
            . "    public function boot(): DiInterface\n"
            . "    {\n"
            . "        \$container = new FactoryDefault();\n"
            . "\n"
            . "        foreach (\$this->providers() as \$provider) {\n"
            . "            \$container->register(\$provider);\n"
            . "        }\n"
            . "\n"
            . "        return \$container;\n"
            . "    }\n"
            . "\n"
            . "    /**\n"
            . "     * Handles the request, and sends the response. The return value is the\n"
            . "     * exit status of public/index.php.\n"
            . "     */\n"
            . "    public function run(): int\n"
            . "    {\n"
            . "        \$uri      = (string) (\$_SERVER['REQUEST_URI'] ?? '/');\n"
            . "        \$response = (new Application(\$this->boot()))->handle(\$uri);\n"
            . "\n"
            . "        if (\$response instanceof ResponseInterface) {\n"
            . "            \$response->send();\n"
            . "        }\n"
            . "\n"
            . "        return 0;\n"
            . "    }\n"
            . "\n"
            . "    /**\n"
            . "     * @return list<ServiceProviderInterface>\n"
            . "     */\n"
            . "    private function providers(): array\n"
            . "    {\n"
            . "        return [\n"
            . "            new DispatcherProvider(),\n"
            . "            new ViewProvider(\$this->projectRoot . '/themes/default'),\n"
            . "        ];\n"
            . "    }\n"
            . "}\n",
            $this->read('src/AppFront.php')
        );
    }

    public function testTheMvcReportNamesTheIndexController(): void
    {
        $this->runCommand(['my-app', '--flavor', 'mvc']);

        $this->assertStringEndsWith(
            PHP_EOL . 'Then GET / answers from src/Controllers/IndexController.php' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testTheMvcSeedComesFromTheControllerStubs(): void
    {
        // The packaged controller stubs, as make:controller renders them.
        $this->runCommand(['my-app', '--flavor', 'mvc', '--namespace', 'Shop']);

        $stub = new Stub(Paths::stubs());

        $this->assertSame(
            $stub->render(
                'mvc',
                'controller-base',
                ['class' => 'CrestControllerBase', 'namespace' => 'Shop\\Controllers\\Base']
            ),
            $this->read('src/Controllers/Base/CrestControllerBase.php')
        );
        $this->assertSame(
            $stub->render(
                'mvc',
                'controller',
                [
                    'base'          => 'CrestControllerBase',
                    'baseNamespace' => 'Shop\\Controllers\\Base',
                    'class'         => 'IndexController',
                    'namespace'     => 'Shop\\Controllers',
                ]
            ),
            $this->read('src/Controllers/IndexController.php')
        );
    }

    public function testTheMvcViewsCarryTheProjectName(): void
    {
        $this->runCommand(['my-app', '--flavor', 'mvc']);

        $this->assertStringContainsString('<title>my-app</title>', $this->read('themes/default/index.phtml'));
        $this->assertStringContainsString('<h1>my-app</h1>', $this->read('themes/default/index/index.phtml'));
    }

    public function testTheNewKeyRunsEachCommandInTheProject(): void
    {
        $this->publishSteps([['composer', 'install'], ['composer', 'test']]);

        $runner = new FakeRunner();
        $status = $this->runWith($runner);
        $target = $this->root . '/my-app';

        $this->assertSame(0, $status);
        $this->assertSame(
            [
                [['composer', 'install'], $target],
                [['composer', 'test'], $target],
            ],
            $runner->calls
        );
        $this->assertSame([[], []], $runner->environments);
        $this->assertSame(
            'Created ' . $target . '/' . PHP_EOL
            . PHP_EOL
            . 'Running: composer install' . PHP_EOL
            . 'Running: composer test' . PHP_EOL
            . "Ran 2 commands from the 'new' key of crest.php" . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testThePhalconVersionIsCaseInsensitive(): void
    {
        $this->runCommand(['my-app', '--phalcon', 'V6']);

        $this->assertSame(
            ['php' => '>=8.4', 'phalcon/phalcon' => '^6.0@RC'],
            $this->composer()['require']
        );
    }

    public function testThePhpFloorItselfIsAccepted(): void
    {
        $status = $this->runCommand(['my-app', '--php', '8.1']);

        $this->assertSame(0, $status);
    }

    public function testThePhpVersionReachesComposerAndTheDockerfile(): void
    {
        $this->runCommand(['my-app', '--php', '8.3']);

        $this->assertSame(['php' => '>=8.3', 'ext-phalcon' => '^5.18'], $this->composer()['require']);
        $this->assertStringContainsString(
            "ARG PHP_VERSION=8.3\n",
            $this->read('resources/docker/Dockerfile')
        );
    }

    public function testTheQuestionsFillTheValuesThatNoOptionGives(): void
    {
        // Flavor, namespace, PHP version, Phalcon version, runtime.
        $this->answers("adr\nShop\n8.3\nv6\nhost\n");

        $this->assertSame(0, $this->runCommand(['my-app'], true));
        $this->assertStringContainsString(
            'Flavor (adr, mvc) [adr]: Root namespace [App]: PHP version [8.4]: '
            . 'Phalcon version (v5, v6) [v5]: Runtime (host, docker) [docker]: ',
            $this->readStderr()
        );
        $this->assertSame(['php' => '>=8.3', 'phalcon/phalcon' => '^6.0@RC'], $this->composer()['require']);
        $this->assertStringContainsString("'namespace' => 'Shop',", $this->read('crest.php'));
        $this->assertStringContainsString("'runtime'   => ['type' => 'host'],", $this->read('crest.php'));
    }

    public function testTheRouterScriptIsRendered(): void
    {
        $this->runCommand(['my-app']);

        $this->assertSame(
            "<?php\n"
            . "\n"
            . "/**\n"
            . " * Router for PHP's built-in server. crest serve and the container\n"
            . " * (resources/docker/Dockerfile) use it.\n"
            . " * A request for a real file under public/ gets that file. All other requests\n"
            . " * go to the front controller.\n"
            . " */\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "\$uri = urldecode((string) parse_url((string) \$_SERVER['REQUEST_URI'], PHP_URL_PATH));\n"
            . "\n"
            . "if ('/' !== \$uri && true === file_exists(__DIR__ . '/public' . \$uri)) {\n"
            . "    return false;\n"
            . "}\n"
            . "\n"
            . "require_once __DIR__ . '/public/index.php';\n",
            $this->read('.htrouter.php')
        );
    }

    public function testTheRuntimeOptionsSkipTheirQuestions(): void
    {
        // Each value has an option, so a run in a terminal asks nothing.
        $status = $this->runCommand(
            [
                'my-app',
                '--flavor',
                'adr',
                '--namespace',
                'Acme',
                '--php',
                '8.2',
                '--phalcon',
                'v6',
                '--runtime',
                'docker',
                '--service',
                'web',
            ],
            true
        );

        $this->assertSame(0, $status);
        $this->assertSame('', $this->readStderr());
        $this->assertStringContainsString(
            "'runtime'   => ['type' => 'docker', 'service' => 'web'],",
            $this->read('crest.php')
        );
        $this->assertStringContainsString("\n  web:\n", $this->read('docker-compose.yml'));
    }

    public function testTheSeedActionIsTheRootAction(): void
    {
        // The packaged action stub, as make:action would render GET /.
        $this->runCommand(['my-app']);

        $expected = (new Stub(Paths::stubs()))->render(
            'adr',
            'action',
            [
                'attributes' => '',
                'class'      => 'Get',
                'namespace'  => 'App\\Action',
                'params'     => '',
            ]
        );

        $this->assertSame($expected, $this->read('src/Action/Get.php'));
        $this->assertStringContainsString('final class Get implements Action', $expected);
    }

    public function testTheWebEntryPointIsRendered(): void
    {
        $this->runCommand(['my-app']);

        $this->assertSame(
            "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "use App\\AppFront;\n"
            . "\n"
            . "\$root = dirname(__DIR__);\n"
            . "\n"
            . "require_once \$root . '/vendor/autoload.php';\n"
            . "\n"
            . "exit((new AppFront(\$root))->run());\n",
            $this->read('public/index.php')
        );
    }

    public function testTheWordCrestRunsThisCrest(): void
    {
        // The crest that runs new, with the same PHP: the user can have no
        // crest on the PATH.
        $this->publishSteps([['crest', 'up', '--build']]);

        $runner = new FakeRunner();

        $this->runWith($runner);

        $this->assertSame(
            [[[PHP_BINARY, Paths::root() . '/bin/crest', 'up', '--build'], $this->root . '/my-app']],
            $runner->calls
        );
        $this->assertStringContainsString('Running: crest up --build' . PHP_EOL, $this->readStdout());
    }

    public function testWithoutANewKeyNothingRuns(): void
    {
        // The packaged stub has no key: the D01 behavior.
        $runner = new FakeRunner();
        $status = $this->runWith($runner);

        $this->assertSame(0, $status);
        $this->assertSame([], $runner->calls);
        $this->assertStringContainsString('Nothing runs it yet. With docker:', $this->readStdout());
    }

    public function testWithoutDirectoryTheProjectLandsInTheWorkingDirectory(): void
    {
        // startScratchProject() moved the working directory into the scratch
        // root, as a user would cd into the directory they want.
        $status = $this->runInWorkingDirectory(['my-app']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/my-app/composer.json');
        $this->assertStringStartsWith('Created my-app/' . PHP_EOL, $this->readStdout());
        $this->assertStringContainsString('    cd my-app' . PHP_EOL, $this->readStdout());
    }

    private function assertNoPlaceholderIn(string $directory): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        $scanned = 0;

        /** @var SplFileInfo $file */
        foreach ($files as $file) {
            $scanned++;

            $this->assertStringNotContainsString(
                '{{',
                (string) file_get_contents($file->getPathname()),
                sprintf('%s still has a placeholder', $file->getPathname())
            );
        }

        $this->assertGreaterThan(0, $scanned);
    }

    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($this->read('composer.json'), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * Publishes the packaged project-config stub into a folder of the
     * overrides, with one text replaced. Gives the path of the copy.
     */
    private function publishConfig(string $folder, string $search, string $replace): string
    {
        $override = Stub::overridePath($this->root, $folder, 'project-config');

        mkdir(dirname($override), 0o775, true);
        file_put_contents(
            $override,
            str_replace(
                $search,
                $replace,
                (string) file_get_contents(Stub::packagedPath(Paths::stubs(), Stub::SHARED, 'project-config'))
            )
        );

        return $override;
    }

    /**
     * Publishes the packaged project-config stub with a `new` key, where new
     * reads its overrides: the adr folder by default, or the shared folder,
     * where stub:publish puts it.
     */
    private function publishSteps(mixed $steps, string $folder = 'adr'): void
    {
        $override = Stub::overridePath($this->root, $folder, 'project-config');

        mkdir(dirname($override), 0o775, true);
        file_put_contents(
            $override,
            str_replace(
                "\n];\n",
                "\n    'new'       => " . var_export($steps, true) . ",\n];\n",
                (string) file_get_contents(Stub::packagedPath(Paths::stubs(), Stub::SHARED, 'project-config'))
            )
        );
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->root . '/my-app/' . $path);
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments, bool $interactive = false): int
    {
        return $this->runProjectCommand('new', NewCommand::class, $arguments, $interactive);
    }

    /**
     * Runs `new` with no --directory added, the way a user types it.
     *
     * @param list<string> $arguments
     */
    private function runInWorkingDirectory(array $arguments): int
    {
        return $this->runThroughKernel('new', NewCommand::class, $arguments);
    }

    /**
     * Runs `new my-app` without the kernel, with a fake runner.
     *
     * @param list<string> $options
     */
    private function runWith(FakeRunner $runner, array $options = []): int
    {
        return $this->handleDirectly(
            new NewCommand($runner),
            ['my-app', '--directory', $this->root, ...$options]
        );
    }
}
