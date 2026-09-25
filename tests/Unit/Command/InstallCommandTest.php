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
use PHPUnit\Framework\TestCase;

final class InstallCommandTest extends TestCase
{
    use RunsACommandDirectly;

    protected function setUp(): void
    {
        $this->captureStreams();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
    }

    public function testComposerInstallRunsInTheAppService(): void
    {
        $runner = new FakeRunner();

        $status = $this->handleDirectly(new InstallCommand($runner), []);

        $this->assertSame(0, $status);
        $this->assertSame(
            [[['docker', 'compose', 'exec', 'app', 'composer', 'install'], null]],
            $runner->calls
        );
    }

    public function testDefinitionNamesItselfInstall(): void
    {
        $this->assertSame('install', (new InstallCommand())->define()->getName());
    }
}
