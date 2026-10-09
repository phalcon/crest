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

use Crest\Command\DownCommand;
use Crest\Tests\Support\Process\FakeRunner;
use Crest\Tests\Support\RunsACommandDirectly;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

final class DownCommandTest extends TestCase
{
    use RunsACommandDirectly;
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->captureStreams();
        $this->makeScratchDirectory('down', 'src');
        $this->writeCrestPhp();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    public function testDefinitionNamesItselfDown(): void
    {
        $this->assertSame('down', (new DownCommand())->define()->getName());
    }

    public function testFromASubdirectoryComposeRunsInTheRoot(): void
    {
        $runner = new FakeRunner();

        $this->handleDirectly(new DownCommand($runner), ['--directory', $this->root . '/src']);

        $this->assertSame([[['docker', 'compose', 'down'], $this->root]], $runner->calls);
    }

    public function testTheContainersStopAndAreRemoved(): void
    {
        $runner = new FakeRunner();

        $status = $this->handleDirectly(new DownCommand($runner), ['--directory', $this->root]);

        $this->assertSame(0, $status);
        $this->assertSame([[['docker', 'compose', 'down'], $this->root]], $runner->calls);
    }

    public function testVolumesAreRemovedToo(): void
    {
        $runner = new FakeRunner();

        $this->handleDirectly(new DownCommand($runner), ['--volumes', '--directory', $this->root]);

        $this->assertSame([[['docker', 'compose', 'down', '--volumes'], $this->root]], $runner->calls);
    }
}
