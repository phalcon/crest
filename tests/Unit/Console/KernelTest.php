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
use Crest\Tests\Support\Console\ThrowingCommand;
use PHPUnit\Framework\TestCase;

use function strpos;

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

    public function testDoubleDashShieldsALaterHelpFlagFromTheKernel(): void
    {
        // `--` makes everything after it literal, so a positional of '--help'
        // must reach the command instead of printing usage.
        $status = $this->kernel()->handle(['demo', 'fake', '--', '--help']);

        $this->assertSame(0, $status);
        $this->assertSame('hello --help' . PHP_EOL, $this->readStdout());
    }

    public function testDoubleDashShieldsALaterTraceFlagFromTheKernel(): void
    {
        // Same rule for --trace: after `--` it is a value, so the failure below
        // must still render as a single line with no stack frames.
        $status = $this->kernel()->handle(['demo', 'fake', 'a', 'b', '--', '--trace']);

        $this->assertSame(1, $status);
        $this->assertStringNotContainsString('#0', $this->readStderr());
    }

    public function testListCommandsIsSortedByName(): void
    {
        $registry = (new Registry())
            ->add('zebra', FakeCommand::class)
            ->add('alpha', ThrowingCommand::class);

        $kernel = new Kernel('demo', $registry, 'phalcon/crest', $this->stdout, $this->stderr, false);

        $kernel->handle(['demo']);

        $output = $this->readStdout();

        $this->assertLessThan(strpos($output, 'zebra'), strpos($output, 'alpha'));
    }

    public function testListingShowsEachCommandDescription(): void
    {
        $this->kernel()->handle(['demo']);

        $this->assertStringContainsString('A command that exists only for tests', $this->readStdout());
    }

    public function testShortHelpFlagAlsoRendersUsage(): void
    {
        $status = $this->kernel()->handle(['demo', 'fake', '-h']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Usage: demo fake', $this->readStdout());
    }

    public function testShortVersionFlagMatchesTheLongOne(): void
    {
        $status = $this->kernel()->handle(['demo', '-V']);

        $this->assertSame(0, $status);
        $this->assertStringStartsWith('demo ', $this->readStdout());
    }

    public function testTraceAddsStackFramesToABindingError(): void
    {
        $status = $this->kernel()->handle(['demo', 'fake', 'a', 'b', '--trace']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('#0', $this->readStderr());
    }

    public function testUnexpectedThrowableIsAlsoOneCleanLine(): void
    {
        $status = $this->throwingKernel()->handle(['demo', 'boom']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('demo: the wheels came off', $this->readStderr());
        $this->assertStringNotContainsString('#0', $this->readStderr());
    }

    public function testUnexpectedThrowableHonoursTrace(): void
    {
        $status = $this->throwingKernel()->handle(['demo', 'boom', '--trace']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('#0', $this->readStderr());
    }

    public function testVersionLineCarriesAResolvedVersion(): void
    {
        $this->kernel()->handle(['demo', '--version']);

        // 'demo ' plus something - an empty version would mean the package
        // lookup silently failed.
        $this->assertMatchesRegularExpression('/^demo \S+/', $this->readStdout());
    }

    private function kernel(): Kernel
    {
        $registry = (new Registry())->add('fake', FakeCommand::class);

        return new Kernel('demo', $registry, 'phalcon/crest', $this->stdout, $this->stderr, false);
    }

    private function throwingKernel(): Kernel
    {
        $registry = (new Registry())->add('boom', ThrowingCommand::class);

        return new Kernel('demo', $registry, 'phalcon/crest', $this->stdout, $this->stderr, false);
    }
}
