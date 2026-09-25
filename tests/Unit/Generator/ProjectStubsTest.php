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
        'actionNamespace'   => 'App\\Action',
        'actionPath'        => 'src/Action',
        'jsonNamespace'     => 'App',
        'namespace'         => 'App',
        'phalconConstraint' => '^5',
        'phalconPackage'    => 'ext-phalcon',
        'phalconVariant'    => 'v5',
        'phpVersion'        => '8.4',
        'project'           => 'my-app',
        'v5'                => '',
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
        $found = glob(Stub::packagedDirectory(Paths::stubs(), self::FLAVOR) . '/project-*.stub');

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
                'type'     => 'project',
                'require'  => ['php' => '>=8.4', 'ext-phalcon' => '^5'],
                'autoload' => ['psr-4' => ['App\\' => 'src/']],
                'config'   => ['sort-packages' => true],
            ],
            json_decode($this->render('project-composer'), true, 512, JSON_THROW_ON_ERROR)
        );
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
