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

    public function testABootstrapIsOneLine(): void
    {
        $settings = new Settings('App', 'App\\AppFront', [], Runtime::host());

        $this->assertSame("    'bootstrap' => App\\AppFront::class,\n", $settings->replacements()['bootstrap']);
    }

    public function testNoBootstrapIsNoLine(): void
    {
        $this->assertSame('', (new Settings('App', null, [], Runtime::host()))->replacements()['bootstrap']);
    }

    public function testNoPathsIsNoLines(): void
    {
        $this->assertSame('', (new Settings('App', null, [], Runtime::host()))->replacements()['paths']);
    }

    public function testTheNamespaceAndTheRuntimeArePassedOn(): void
    {
        $replacements = (new Settings('Shop', null, [], Runtime::docker('web')))->replacements();

        $this->assertSame('Shop', $replacements['namespace']);
        $this->assertSame("['type' => 'docker', 'service' => 'web']", $replacements['runtime']);
    }

    public function testThePathsLineUp(): void
    {
        $settings = new Settings(
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

    public function testTheRenderedConfigReadsBack(): void
    {
        // The written crest.php gives back each value.
        $this->writeComposerJson(['Shop\\' => 'src/']);

        $settings = new Settings(
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
}
