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

use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Project\Config;
use Crest\Project\Flavor;
use Crest\Project\Runtime;
use Crest\Project\Settings;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;

final class SettingsTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('settings', 'src/Action');
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function camelCases(): iterable
    {
        yield 'false' => [false, 'false'];
        yield 'true' => [true, 'true'];
    }

    public function testABootstrapIsOneLine(): void
    {
        $settings = new Settings(Flavor::ADR, 'App', 'App\\AppFront', [], Runtime::host());

        $this->assertSame("    'bootstrap' => App\\AppFront::class,\n", $settings->replacements()['bootstrap']);
    }

    /**
     * @dataProvider camelCases
     */
    public function testACamelCaseAnswerGivesTheModelsKey(bool $camelCase, string $written): void
    {
        $settings = new Settings(Flavor::MVC, 'App', null, [], Runtime::host(), $camelCase);

        $this->assertSame(
            "    'models'    => [\n"
            . "        // Property names in camelCase. A change renames the properties of\n"
            . "        // each model, so choose before the first make:model.\n"
            . "        'camelCase' => " . $written . ",\n"
            . "        // The class name of a table, when the default name does not fit,\n"
            . "        // e.g. 'user_logins' => 'Login'.\n"
            . "        'classes'   => [],\n"
            . "    ],\n",
            $settings->replacements()['models']
        );
    }

    public function testNoBootstrapIsNoLine(): void
    {
        $this->assertSame(
            '',
            (new Settings(Flavor::ADR, 'App', null, [], Runtime::host()))->replacements()['bootstrap']
        );
    }

    public function testNoCamelCaseAnswerIsNoModelsKey(): void
    {
        // ADR and CLI have no models.
        $this->assertSame(
            '',
            (new Settings(Flavor::ADR, 'App', null, [], Runtime::host()))->replacements()['models']
        );
    }

    public function testNoPathsIsNoLines(): void
    {
        $this->assertSame(
            '',
            (new Settings(Flavor::ADR, 'App', null, [], Runtime::host()))->replacements()['paths']
        );
    }

    public function testTheFlavorIsPassedOn(): void
    {
        $this->assertSame(
            'mvc',
            (new Settings(Flavor::MVC, 'App', null, [], Runtime::host()))->replacements()['flavor']
        );
    }

    public function testTheNamespaceAndTheRuntimeArePassedOn(): void
    {
        $replacements = (new Settings(Flavor::ADR, 'Shop', null, [], Runtime::docker('web')))->replacements();

        $this->assertSame('Shop', $replacements['namespace']);
        $this->assertSame("['type' => 'docker', 'service' => 'web']", $replacements['runtime']);
    }

    public function testThePathsLineUp(): void
    {
        $settings = new Settings(
            Flavor::ADR,
            'App',
            null,
            ['action' => 'src/Action', 'middleware' => 'src/Middleware'],
            Runtime::host()
        );

        $this->assertSame(
            "        'action'     => 'src/Action',\n"
            . "        'middleware' => 'src/Middleware',\n",
            $settings->replacements()['paths']
        );
    }

    public function testTheRenderedConfigGivesTheFlavorBack(): void
    {
        $this->writeComposerJson(['App\\' => 'src/']);

        $settings = new Settings(Flavor::MVC, 'App', null, [], Runtime::host());

        file_put_contents(
            $this->root . '/crest.php',
            (new Stub(Paths::stubs()))->render('mvc', 'project-config', $settings->replacements())
        );

        $this->assertSame(Flavor::MVC, Config::discover($this->root)->flavor());
    }

    public function testTheRenderedConfigReadsBack(): void
    {
        // The written crest.php gives back each value.
        $this->writeComposerJson(['Shop\\' => 'src/']);

        $settings = new Settings(
            Flavor::ADR,
            'Shop',
            'Shop\\AppFront',
            Config::defaultPaths(Flavor::ADR),
            Runtime::docker('web')
        );

        file_put_contents(
            $this->root . '/crest.php',
            (new Stub(Paths::stubs()))->render('adr', 'project-config', $settings->replacements())
        );

        $config = Config::discover($this->root);

        $this->assertSame('Shop', $config->namespace());
        $this->assertSame('Shop\\AppFront', $config->bootstrap());
        $this->assertSame($this->root . '/src/Middleware', $config->path('middleware'));
        $this->assertTrue($config->isDeclared('paths.responder'));
        $this->assertTrue($config->runtime()->isDocker());
        $this->assertSame('web', $config->runtime()->service);
    }

    public function testTheRenderedConfigReadsTheModelsKeyBack(): void
    {
        // make:model reads the key that new and init write.
        $this->writeComposerJson(['App\\' => 'src/']);

        $settings = new Settings(
            Flavor::MVC,
            'App',
            'App\\AppFront',
            Config::defaultPaths(Flavor::MVC),
            Runtime::docker(),
            true
        );

        file_put_contents(
            $this->root . '/crest.php',
            (new Stub(Paths::stubs()))->render('mvc', 'project-config', $settings->replacements())
        );

        $this->assertSame(['camelCase' => true, 'classes' => []], Config::discover($this->root)->models());
    }
}
