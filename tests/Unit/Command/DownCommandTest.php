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
use PHPUnit\Framework\TestCase;

final class DownCommandTest extends TestCase
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

    public function testDefinitionNamesItselfDown(): void
    {
        $this->assertSame('down', (new DownCommand())->define()->getName());
    }

    public function testTheContainersStopAndAreRemoved(): void
    {
        $runner = new FakeRunner();

        $status = $this->handleDirectly(new DownCommand($runner), []);

        $this->assertSame(0, $status);
        $this->assertSame([[['docker', 'compose', 'down'], null]], $runner->calls);
    }

    public function testVolumesAreRemovedToo(): void
    {
        $runner = new FakeRunner();

        $this->handleDirectly(new DownCommand($runner), ['--volumes']);

        $this->assertSame([[['docker', 'compose', 'down', '--volumes'], null]], $runner->calls);
    }
}
