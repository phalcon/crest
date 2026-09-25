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

use Crest\Command\NewCommand;
use Crest\Commands;
use Crest\Console\Kernel;
use Crest\Console\Registry;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Tests\Support\GeneratesInAScratchProject;
use PHPUnit\Framework\TestCase;

use function dirname;
use function file_get_contents;
use function file_put_contents;
use function json_decode;
use function mkdir;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

final class NewCommandTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('new');
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
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

    public function testAPublishedProjectStubInTheParentDirectoryIsUsed(): void
    {
        $override = Stub::overridePath($this->root, 'adr', 'project-readme');

        mkdir(dirname($override), 0o775, true);
        file_put_contents($override, "custom {{ project }}\n");

        $status = $this->runCommand(['my-app']);

        $this->assertSame(0, $status);
        $this->assertSame("custom my-app\n", $this->read('README.md'));
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

    public function testComposerJsonRequiresTheExtensionByDefault(): void
    {
        $this->runCommand(['my-app']);

        $this->assertSame(
            [
                'type'     => 'project',
                'require'  => ['php' => '>=8.4', 'ext-phalcon' => '^5.18'],
                'autoload' => ['psr-4' => ['App\\' => 'src/']],
                'config'   => ['sort-packages' => true],
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

    public function testDefinitionNamesItselfNew(): void
    {
        $this->assertSame('new', (new NewCommand())->define()->getName());
    }

    public function testEveryProjectFileIsWritten(): void
    {
        $status = $this->runCommand(['my-app']);

        $this->assertSame(0, $status);

        foreach (
            [
                'composer.json',
                'crest.php',
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

    public function testForceWritesIntoANonEmptyDirectory(): void
    {
        mkdir($this->root . '/my-app');
        file_put_contents($this->root . '/my-app/notes.txt', 'mine');

        $status = $this->runCommand(['my-app', '--force']);

        $this->assertSame(0, $status);
        $this->assertSame('mine', $this->read('notes.txt'));
        $this->assertFileExists($this->root . '/my-app/composer.json');
    }

    public function testNameArgumentIsRequired(): void
    {
        $status = $this->runCommand([]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("missing required argument 'name'", $this->readStderr());
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
            . '    crest up' . PHP_EOL
            . '    crest install' . PHP_EOL
            . PHP_EOL
            . 'Or with PHP and composer on the host:' . PHP_EOL
            . PHP_EOL
            . '    cd ' . $target . PHP_EOL
            . '    composer install' . PHP_EOL
            . '    php -S localhost:8080 -t public .htrouter.php' . PHP_EOL
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

    public function testTheGeneratedConfigNamesTheFrontController(): void
    {
        $this->runCommand(['my-app']);

        // The docblock comes before declare(): PSR-12 puts the file docblock
        // first in the header.
        $this->assertSame(
            "<?php\n"
            . "\n"
            . "/**\n"
            . " * Crest configuration. Paths that are not listed here take the ADR defaults;\n"
            . " * run `crest config:show` to see every value and where it came from.\n"
            . " */\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "return [\n"
            . "    'flavor'    => 'adr',\n"
            . "    'namespace' => 'App',\n"
            . "    'bootstrap' => App\\AppFront::class,\n"
            . "];\n",
            $this->read('crest.php')
        );
    }

    public function testTheGitignoreKeepsVendorOut(): void
    {
        $this->runCommand(['my-app']);

        $this->assertSame("/vendor/\n", $this->read('.gitignore'));
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

    public function testTheRouterScriptIsRendered(): void
    {
        $this->runCommand(['my-app']);

        $this->assertSame(
            "<?php\n"
            . "\n"
            . "/**\n"
            . " * Router for PHP's built-in server: php -S localhost:8080 -t public .htrouter.php\n"
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

    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($this->read('composer.json'), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->root . '/my-app/' . $path);
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): int
    {
        return $this->runProjectCommand('new', NewCommand::class, $arguments);
    }

    /**
     * Runs `new` with no --directory added, the way a user types it.
     *
     * @param list<string> $arguments
     */
    private function runInWorkingDirectory(array $arguments): int
    {
        $kernel = new Kernel(
            Commands::NAME,
            (new Registry())->add('new', NewCommand::class),
            Commands::PACKAGE,
            $this->stdout,
            $this->stderr,
            false
        );

        return $kernel->handle(['crest', 'new', ...$arguments]);
    }
}
