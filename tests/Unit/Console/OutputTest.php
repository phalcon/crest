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

use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Tests\Support\CapturesOutput;
use PHPUnit\Framework\TestCase;

use const PHP_EOL;

final class OutputTest extends TestCase
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

    public function testBannerColorsOnlyTheMarkWhenDecorated(): void
    {
        // The text after the mark is the caller's, and stays uncolored.
        $output = new Output($this->stdout, $this->stderr, true);

        $output->banner('demo 1.2.3');

        $this->assertSame(
            "\033[38;5;208m" . Output::MARK . "\033[0m" . ' demo 1.2.3' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testBannerPrintsTheMarkThenTheTextUndecorated(): void
    {
        // Undecorated keeps the glyph and drops the escapes: a piped run should
        // still read as a banner, just without color.
        $output = new Output($this->stdout, $this->stderr, false);

        $output->banner('demo 1.2.3');

        $this->assertSame(Output::MARK . ' demo 1.2.3' . PHP_EOL, $this->readStdout());
    }

    public function testErrorGoesToStderrNotStdout(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->error('it broke');

        $this->assertSame('', $this->readStdout());
        $this->assertSame('it broke' . PHP_EOL, $this->readStderr());
    }

    public function testErrorWrapsInRedWhenDecorated(): void
    {
        $output = new Output($this->stdout, $this->stderr, true);

        $output->error('it broke');

        $this->assertSame("\033[31mit broke\033[0m" . PHP_EOL, $this->readStderr());
    }

    public function testLineWithNoArgumentWritesOnlyANewline(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->line();

        $this->assertSame(PHP_EOL, $this->readStdout());
    }

    public function testLineWritesToStdoutWithNewline(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->line('hello');

        $this->assertSame('hello' . PHP_EOL, $this->readStdout());
    }

    public function testNoColorEnvironmentVariableDisablesDecoration(): void
    {
        putenv('NO_COLOR=1');

        try {
            // Auto-detect: NO_COLOR wins before the tty check is reached.
            $output = new Output($this->stdout, $this->stderr);

            $output->success('done');

            $this->assertSame('done' . PHP_EOL, $this->readStdout());
        } finally {
            putenv('NO_COLOR');
        }
    }

    public function testSuccessIsUndecoratedWhenDecorationIsOff(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->success('done');

        $this->assertSame('done' . PHP_EOL, $this->readStdout());
    }

    public function testSuccessWrapsInGreenWhenDecorated(): void
    {
        $output = new Output($this->stdout, $this->stderr, true);

        $output->success('done');

        $this->assertSame("\033[32mdone\033[0m" . PHP_EOL, $this->readStdout());
    }

    public function testTableGivesAnEntirelyEmptyColumnNoWidth(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        // Column 0 is empty in both the header and the row, so it must occupy
        // no characters at all - only the separator remains before 'x'.
        $output->table(['', '', ''], [['', 'x', 'y']], false);

        $this->assertSame('  x  y' . PHP_EOL, $this->readStdout());
    }

    public function testTableMeasuresMultibyteCellsByCharacterNotByte(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        // 'aeiou' and 'áéíóú' are both five characters; strlen would call the
        // second one ten bytes wide and over-pad the first column.
        $output->table(['H', 'V'], [['áéíóú', 'x'], ['aeiou', 'y']]);

        $expected = 'H      V' . PHP_EOL
            . 'áéíóú  x' . PHP_EOL
            . 'aeiou  y' . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }

    public function testTablePadsColumnsToTheWidestCell(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->table(['NAME', 'VALUE'], [['php', '8.4.1'], ['label', 'dev']]);

        $expected = 'NAME   VALUE' . PHP_EOL
            . 'php    8.4.1' . PHP_EOL
            . 'label  dev' . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }

    public function testTableSuppressesTheHeaderRowButKeepsItsWidths(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->table(['LONGHEADER', ''], [['a', 'b']], false);

        // The header row is not printed, but it still sizes the columns: 'a'
        // is padded out to the width of 'LONGHEADER'. That is what usage()
        // relies on to align two untitled columns.
        $this->assertSame('a           b' . PHP_EOL, $this->readStdout());
    }

    public function testTableTrimsTrailingPaddingFromTheLastColumn(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->table(['A', 'B'], [['x', 'longer'], ['y', 'z']]);

        // 'z' must not be padded out to 'longer' width - the row is rtrimmed.
        $this->assertStringEndsWith('y  z' . PHP_EOL, $this->readStdout());
    }

    public function testUndecoratedIsDetectedForANonTtyStream(): void
    {
        // php://memory is never a tty, so auto-detection must settle on
        // undecorated once NO_COLOR is out of the way.
        putenv('NO_COLOR');

        $output = new Output($this->stdout, $this->stderr);

        $output->success('done');

        $this->assertSame('done' . PHP_EOL, $this->readStdout());
    }

    public function testUsageBracketsRequiredAndOptionalArgumentsDifferently(): void
    {
        $definition = Definition::for('make:action')
            ->argument('method', true)
            ->argument('suffix', false);

        (new Output($this->stdout, $this->stderr, false))->usage('crest', $definition);

        $this->assertStringContainsString('Usage: crest make:action <method> [suffix]', $this->readStdout());
    }

    public function testUsageOmitsTheDescriptionBlockWhenThereIsNone(): void
    {
        $definition = Definition::for('bare');

        (new Output($this->stdout, $this->stderr, false))->usage('crest', $definition);

        // With no description, no arguments and no options the whole block is
        // a single usage line - no leading blank, no section headings.
        $this->assertSame('Usage: crest bare' . PHP_EOL, $this->readStdout());
    }

    public function testUsageOmitsTheOptionsMarkerWhenNoneAreDeclared(): void
    {
        $definition = Definition::for('bare')->argument('only', true);

        (new Output($this->stdout, $this->stderr, false))->usage('crest', $definition);

        $output = $this->readStdout();

        $this->assertStringContainsString('Usage: crest bare <only>', $output);
        $this->assertStringNotContainsString('[options]', $output);
        $this->assertStringNotContainsString('Options:', $output);
    }

    public function testUsageRendersDescriptionArgumentsAndOptions(): void
    {
        $definition = Definition::for('make:action', 'Create an action')
            ->argument('method', true, 'HTTP method')
            ->option('force|f', 'Overwrite');

        (new Output($this->stdout, $this->stderr, false))->usage('crest', $definition);

        $expected = 'Create an action' . PHP_EOL
            . PHP_EOL
            . 'Usage: crest make:action <method> [options]' . PHP_EOL
            . PHP_EOL
            . 'Arguments:' . PHP_EOL
            . '  method  HTTP method' . PHP_EOL
            . PHP_EOL
            . 'Options:' . PHP_EOL
            . '  --force, -f  Overwrite' . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }

    public function testUsageSkipsTheArgumentsSectionWhenThereAreNone(): void
    {
        $definition = Definition::for('about')->option('trace', 'Trace');

        (new Output($this->stdout, $this->stderr, false))->usage('crest', $definition);

        $output = $this->readStdout();

        $this->assertStringNotContainsString('Arguments:', $output);
        $this->assertStringContainsString('Options:', $output);
        $this->assertStringContainsString('  --trace  Trace', $output);
    }

    public function testWriteEmitsNoTrailingNewline(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);

        $output->write('partial');

        $this->assertSame('partial', $this->readStdout());
    }
}
