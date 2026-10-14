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

namespace Crest\Tests\Unit\Generator;

use Crest\Console\PackageVersion;
use Crest\Generator\Stub;
use Crest\Paths;
use ParseError;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function class_exists;
use function extension_loaded;
use function interface_exists;
use function json_decode;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function token_get_all;

use const JSON_THROW_ON_ERROR;
use const TOKEN_PARSE;

/**
 * The project-* stubs render a whole application for `crest new`, not one
 * artifact class. StubContractsTest skips them, and this test holds their
 * contract: the stubs of each flavor that `new` creates, and the shared ones.
 */
final class ProjectStubsTest extends TestCase
{
    /**
     * The flavors that `new` creates. Each one also finds the shared project
     * stubs.
     */
    private const FLAVORS = ['adr', 'mvc'];

    /**
     * Every placeholder that the project stubs use, as one superset.
     * NewCommand and the skeletons supply exactly these keys.
     */
    private const REPLACEMENTS = [
        'actionNamespace'     => 'App\\Action',
        'actionPath'          => 'src/Action',
        'autoloader'          => 'vendor/autoload.php',
        'baseFolder'          => 'Base',
        'bootstrap'           => "    'bootstrap' => App\\AppFront::class,\n",
        'controllerNamespace' => 'App\\Controllers',
        'controllerPath'      => 'src/Controllers',
        'crestConstraint'     => '^1.0',
        'documentRoot'        => 'public',
        'extensionConstraint' => '^5.18',
        'flavor'              => 'adr',
        'front'               => 'AppFront',
        'jsonNamespace'       => 'App',
        'namespace'           => 'App',
        'paths'               => "        'action' => 'src/Action',\n",
        'phalconConstraint'   => '^5',
        'phalconPackage'      => 'ext-phalcon',
        'phalconVariant'      => 'v5',
        'phpVersion'          => '8.4',
        'port'                => '8080',
        'portVariable'        => 'APP_PORT',
        'project'             => 'my-app',
        'providerNamespace'   => 'App\\Providers',
        'providerPath'        => 'src/Providers',
        'router'              => '.htrouter.php',
        'runtime'             => "['type' => 'docker', 'service' => 'app']",
        'seed'                => 'Get',
        'service'             => 'app',
        'source'              => 'src',
        'v5'                  => '',
        'viewsPath'           => 'themes/default',
    ];

    /**
     * The project stubs that import classes.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function importingStubs(): iterable
    {
        yield 'adr/project-front' => ['adr', 'project-front'];
        yield 'mvc/project-front' => ['mvc', 'project-front'];
        yield 'mvc/project-phtml-provider' => ['mvc', 'project-phtml-provider'];
        yield 'mvc/project-provider-dispatcher' => ['mvc', 'project-provider-dispatcher'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function phpStubs(): iterable
    {
        yield 'adr/project-front' => ['adr', 'project-front'];
        yield 'mvc/project-front' => ['mvc', 'project-front'];
        yield 'mvc/project-phtml-index' => ['mvc', 'project-phtml-index'];
        yield 'mvc/project-phtml-layout' => ['mvc', 'project-phtml-layout'];
        yield 'mvc/project-phtml-provider' => ['mvc', 'project-phtml-provider'];
        yield 'mvc/project-provider-dispatcher' => ['mvc', 'project-provider-dispatcher'];
        yield 'shared/project-config' => ['adr', 'project-config'];
        yield 'shared/project-htrouter' => ['adr', 'project-htrouter'];
        yield 'shared/project-index' => ['adr', 'project-index'];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function projectStubs(): iterable
    {
        yield from self::packagedNames();
    }

    /**
     * Every packaged project stub that `new` renders, as the lookup sees it:
     * the flavor folders and the shared folder. The key is `<folder>/<name>`,
     * so a shared stub is in the list one time. packagedFolders() gives byte
     * order on every machine.
     *
     * @return array<string, array{string, string}> The flavor and the name
     */
    private static function packagedNames(): array
    {
        $stubs = [];

        foreach (self::FLAVORS as $flavor) {
            foreach ((new Stub(Paths::stubs()))->packagedFolders($flavor) as $name => $folder) {
                if (true === str_starts_with($name, Stub::PROJECT_PREFIX)) {
                    $stubs[$folder . '/' . $name] ??= [$flavor, $name];
                }
            }
        }

        return $stubs;
    }

    /**
     * An import in the root namespace names a class of the generated
     * project, which does not exist here. GeneratedMvcProjectTest loads
     * those.
     *
     * @dataProvider importingStubs
     */
    public function testEveryImportResolves(string $flavor, string $name): void
    {
        if (
            false === PackageVersion::isInstalled('phalcon/phalcon')
            && false === extension_loaded('phalcon')
        ) {
            $this->markTestSkipped('resolving the imports needs Phalcon present');
        }

        preg_match_all('/^use\s+([\w\\\\]+)/m', $this->render($flavor, $name), $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $import) {
            if (true === str_starts_with($import, self::REPLACEMENTS['namespace'] . '\\')) {
                continue;
            }

            $this->assertTrue(
                class_exists($import) || interface_exists($import),
                sprintf('%s imports %s, which does not exist', $name, $import)
            );
        }
    }

    /**
     * @dataProvider projectStubs
     */
    public function testNoPlaceholderIsLeftUnrendered(string $flavor, string $name): void
    {
        $this->assertFalse(
            str_contains($this->render($flavor, $name), '{{'),
            sprintf("stub '%s' left a placeholder unrendered", $name)
        );
    }

    /**
     * @dataProvider phpStubs
     */
    public function testPhpStubsRenderToParseablePhp(string $flavor, string $name): void
    {
        try {
            // TOKEN_PARSE makes this a syntax check, not only a tokenizer run.
            $this->assertNotEmpty(token_get_all($this->render($flavor, $name), TOKEN_PARSE));
        } catch (ParseError $error) {
            $this->fail(
                sprintf("stub '%s' does not render to valid PHP: %s", $name, $error->getMessage())
            );
        }
    }

    public function testTheComposerStubRendersToValidJson(): void
    {
        $this->assertSame(
            [
                'type'        => 'project',
                'require'     => ['php' => '>=8.4', 'ext-phalcon' => '^5'],
                'require-dev' => ['phalcon/crest' => '^1.0'],
                'autoload'    => ['psr-4' => ['App\\' => 'src/']],
                'config'      => ['sort-packages' => true],
            ],
            json_decode($this->render('adr', 'project-composer'), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testTheComposeServiceRunsAnInit(): void
    {
        // php -S is not PID 1 then, so it stops on the SIGTERM of
        // `docker compose down`.
        $this->assertStringContainsString("    init: true\n", $this->render('adr', 'project-compose'));
    }

    public function testTheDockerfileGetsTheExtensionConstraintFromAPlaceholder(): void
    {
        $rendered = (new Stub(Paths::stubs()))->render(
            'adr',
            Stub::PROJECT_PREFIX . 'dockerfile',
            [...self::REPLACEMENTS, 'extensionConstraint' => '^9.9']
        );

        $this->assertStringContainsString('phalcon/cphalcon:^9.9', $rendered);
    }

    public function testTheLayoutComesOnlyFromPlaceholders(): void
    {
        // NewCommand gives the layout to the stubs. A literal copy in a stub
        // does not follow Layout, so another layout must leave no trace of
        // the default one. The bootstrap value names the front controller
        // too, so it changes with the layout.
        $layout = [
            'autoloader'   => 'deps/autoload.php',
            'bootstrap'    => "    'bootstrap' => App\\SiteFront::class,\n",
            'documentRoot' => 'web',
            'front'        => 'SiteFront',
            'port'         => '9090',
            'portVariable' => 'SITE_PORT',
            'router'       => 'router.php',
            'source'       => 'lib',
        ];

        // The document root as a path, not as a word: `public` is also a PHP
        // keyword, and the MVC front has public methods.
        $defaults = [
            '"public"',
            '/public',
            'public/',
            '.htrouter.php',
            'APP_PORT',
            'AppFront',
            'vendor/autoload.php',
            '"src/"',
            ':-8080',
            '=8080',
            'localhost:8080',
        ];

        foreach (self::packagedNames() as [$flavor, $name]) {
            $rendered = (new Stub(Paths::stubs()))->render($flavor, $name, [...self::REPLACEMENTS, ...$layout]);

            foreach ($defaults as $default) {
                $this->assertStringNotContainsString(
                    $default,
                    $rendered,
                    sprintf("stub '%s' has the literal '%s'", $name, $default)
                );
            }
        }
    }

    public function testTheProjectStubsArePackaged(): void
    {
        $this->assertSame(
            [
                'adr/project-front',
                'adr/project-readme',
                'shared/project-compose',
                'shared/project-composer',
                'shared/project-config',
                'shared/project-dockerfile',
                'shared/project-env',
                'shared/project-gitignore',
                'shared/project-htrouter',
                'shared/project-index',
                'shared/project-launcher',
                'mvc/project-front',
                'mvc/project-phtml-index',
                'mvc/project-phtml-layout',
                'mvc/project-phtml-provider',
                'mvc/project-provider-dispatcher',
                'mvc/project-readme',
            ],
            array_keys(self::packagedNames())
        );
    }

    private function render(string $flavor, string $name): string
    {
        return (new Stub(Paths::stubs()))->render($flavor, $name, self::REPLACEMENTS);
    }
}
