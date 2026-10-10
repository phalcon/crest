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

use function basename;
use function class_exists;
use function extension_loaded;
use function glob;
use function interface_exists;
use function json_decode;
use function preg_match_all;
use function sort;
use function sprintf;
use function str_contains;
use function token_get_all;

use const JSON_THROW_ON_ERROR;
use const TOKEN_PARSE;

/**
 * The project-* stubs render a whole application for `crest new`, not one
 * artifact class. StubContractsTest skips them, and this test holds their
 * contract.
 */
final class ProjectStubsTest extends TestCase
{
    private const FLAVOR = 'adr';

    /**
     * Every placeholder that the project stubs use. NewCommand supplies
     * exactly these keys.
     */
    private const REPLACEMENTS = [
        'actionNamespace'     => 'App\\Action',
        'actionPath'          => 'src/Action',
        'autoloader'          => 'vendor/autoload.php',
        'bootstrap'           => "    'bootstrap' => App\\AppFront::class,\n",
        'crestConstraint'     => '^1.0',
        'documentRoot'        => 'public',
        'extensionConstraint' => '^5.18',
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
        'router'              => '.htrouter.php',
        'runtime'             => "['type' => 'docker', 'service' => 'app']",
        'seed'                => 'Get',
        'service'             => 'app',
        'source'              => 'src',
        'v5'                  => '',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function phpStubs(): iterable
    {
        foreach (['project-config', 'project-front', 'project-htrouter', 'project-index'] as $name) {
            yield $name => [$name];
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function projectStubs(): iterable
    {
        foreach (self::packagedNames() as $name) {
            yield $name => [$name];
        }
    }

    /**
     * @return list<string>
     */
    private static function packagedNames(): array
    {
        $names = [];
        $found = glob(Stub::packagedDirectory(Paths::stubs(), self::FLAVOR) . '/' . Stub::PROJECT_PREFIX . '*.stub');

        foreach ($found ?: [] as $file) {
            $names[] = basename($file, '.stub');
        }

        // glob() sorts with the collation of the locale, so its order changes
        // from machine to machine. sort() gives byte order everywhere.
        sort($names);

        return $names;
    }

    /**
     * @dataProvider projectStubs
     */
    public function testNoPlaceholderIsLeftUnrendered(string $name): void
    {
        $this->assertFalse(
            str_contains($this->render($name), '{{'),
            sprintf("stub '%s' left a placeholder unrendered", $name)
        );
    }

    /**
     * @dataProvider phpStubs
     */
    public function testPhpStubsRenderToParseablePhp(string $name): void
    {
        try {
            // TOKEN_PARSE makes this a syntax check, not only a tokenizer run.
            $this->assertNotEmpty(token_get_all($this->render($name), TOKEN_PARSE));
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
            json_decode($this->render('project-composer'), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testTheDockerfileGetsTheExtensionConstraintFromAPlaceholder(): void
    {
        $rendered = (new Stub(Paths::stubs()))->render(
            self::FLAVOR,
            Stub::PROJECT_PREFIX . 'dockerfile',
            [...self::REPLACEMENTS, 'extensionConstraint' => '^9.9']
        );

        $this->assertStringContainsString('phalcon/cphalcon:^9.9', $rendered);
    }

    public function testTheFrontControllerImportsResolve(): void
    {
        if (
            false === PackageVersion::isInstalled('phalcon/phalcon')
            && false === extension_loaded('phalcon')
        ) {
            $this->markTestSkipped('resolving the front controller imports needs Phalcon present');
        }

        preg_match_all('/^use\s+([\w\\\\]+)/m', $this->render('project-front'), $matches);

        $this->assertNotEmpty($matches[1]);

        foreach ($matches[1] as $import) {
            $this->assertTrue(
                class_exists($import) || interface_exists($import),
                sprintf('project-front imports %s, which does not exist', $import)
            );
        }
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

        $defaults = [
            'public',
            '.htrouter.php',
            'APP_PORT',
            'AppFront',
            'vendor/autoload.php',
            '"src/"',
            ':-8080',
            '=8080',
            'localhost:8080',
        ];

        foreach (self::packagedNames() as $name) {
            $rendered = (new Stub(Paths::stubs()))->render(self::FLAVOR, $name, [...self::REPLACEMENTS, ...$layout]);

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
                'project-compose',
                'project-composer',
                'project-config',
                'project-dockerfile',
                'project-env',
                'project-front',
                'project-gitignore',
                'project-htrouter',
                'project-index',
                'project-launcher',
                'project-readme',
            ],
            self::packagedNames()
        );
    }

    private function render(string $name): string
    {
        return (new Stub(Paths::stubs()))->render(self::FLAVOR, $name, self::REPLACEMENTS);
    }
}
