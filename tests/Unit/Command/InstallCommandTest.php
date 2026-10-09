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

use Crest\Command\InstallCommand;
use Crest\Tests\Support\Process\FakeRunner;
use Crest\Tests\Support\RunsACommandDirectly;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

final class InstallCommandTest extends TestCase
{
    use RunsACommandDirectly;
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->captureStreams();
        $this->makeScratchDirectory('install');
        $this->writeCrestPhp();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    public function testComposerInstallRunsInTheAppService(): void
    {
        $runner = new FakeRunner();

        $status = $this->handleDirectly(new InstallCommand($runner), ['--directory', $this->root]);

        $this->assertSame(0, $status);
        $this->assertSame(
            [[['docker', 'compose', 'exec', 'app', 'composer', 'install'], $this->root]],
            $runner->calls
        );
    }

    public function testDefinitionNamesItselfInstall(): void
    {
        $this->assertSame('install', (new InstallCommand())->define()->getName());
    }

    public function testTheServiceComesFromCrestPhp(): void
    {
        $this->writeCrestPhp("['runtime' => ['type' => 'docker', 'service' => 'web']]");

        $runner = new FakeRunner();

        $this->handleDirectly(new InstallCommand($runner), ['--directory', $this->root]);

        $this->assertSame(
            [[['docker', 'compose', 'exec', 'web', 'composer', 'install'], $this->root]],
            $runner->calls
        );
    }
}
