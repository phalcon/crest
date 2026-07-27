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

namespace Crest\Tests\Unit\Console;

use Crest\Console\Kernel;
use Crest\Console\Registry;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\Console\FakeCommand;
use PHPUnit\Framework\TestCase;

use const PHP_EOL;

final class KernelTest extends TestCase
{
    use CapturesOutput;

    protected function setUp(): void
    {
        $this->captureStreams();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
    }

    public function testBindingErrorIsAOneLineStderrMessage(): void
    {
        $status = $this->kernel()->handle(['demo', 'fake', 'a', 'b']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("demo: unexpected argument 'b'", $this->readStderr());
        $this->assertStringNotContainsString('#0', $this->readStderr());
    }

    public function testGlobalOptionsAreAvailableToEveryCommand(): void
    {
        $status = $this->kernel()->handle(['demo', 'fake', '--directory', '/srv']);

        $this->assertSame(0, $status);
    }

    public function testHelpForACommandRendersItsUsage(): void
    {
        $status = $this->kernel()->handle(['demo', 'fake', '--help']);

        $output = $this->readStdout();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('A command that exists only for tests', $output);
        $this->assertStringContainsString('Usage: demo fake', $output);
        $this->assertStringContainsString('--directory', $output);
        $this->assertStringNotContainsString('hello', $output);
    }

    public function testKnownCommandRuns(): void
    {
        $status = $this->kernel()->handle(['demo', 'fake', 'phalcon']);

        $this->assertSame(0, $status);
        $this->assertSame('hello phalcon' . PHP_EOL, $this->readStdout());
    }

    public function testNoArgumentsListsCommands(): void
    {
        $status = $this->kernel()->handle(['demo']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('fake', $this->readStdout());
    }

    public function testToolNameIsNeverHardcoded(): void
    {
        // The whole point of the constructor argument: nothing in the console
        // core may say "crest". If this fails, the extraction is broken.
        $this->kernel()->handle(['demo', 'nope']);
        $this->kernel()->handle(['demo', '--version']);

        $this->assertStringNotContainsStringIgnoringCase('crest', $this->readStdout());
        $this->assertStringNotContainsStringIgnoringCase('crest', $this->readStderr());
    }

    public function testUnknownCommandExitsOneWithStderr(): void
    {
        $status = $this->kernel()->handle(['demo', 'nope']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("demo: unknown command 'nope'", $this->readStderr());
    }

    public function testVersionIsHandledBeforeCommandResolution(): void
    {
        $status = $this->kernel()->handle(['demo', '--version']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('demo', $this->readStdout());
    }

    private function kernel(): Kernel
    {
        $registry = (new Registry())->add('fake', FakeCommand::class);

        return new Kernel('demo', $registry, 'phalcon/crest', $this->stdout, $this->stderr, false);
    }
}
