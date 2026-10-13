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

use Crest\Console\Exceptions\Exception;
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

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function aliasBoundaries(): iterable
    {
        yield 'a file in the folder' => ['/srv/src/A.php', '/home/u/app/src/A.php'];
        yield 'the folder at the end' => ['root /srv', 'root /home/u/app'];
        yield 'the folder in quotes' => ["'/srv'", "'/home/u/app'"];
        yield 'two times' => ['/srv and /srv/a', '/home/u/app and /home/u/app/a'];
        yield 'a folder with the same start' => ['/srv-old/a', '/srv-old/a'];
        yield 'a longer name' => ['/srvx', '/srvx'];
        yield 'a name with a dot' => ['/srv.bak', '/srv.bak'];
        yield 'a deeper folder' => ['/x/srv/a', '/x/srv/a'];
        yield 'a non-ASCII folder before it' => ['/x/é/srv', '/x/é/srv'];
        yield 'a URL' => ['file:///srv/a', 'file:///srv/a'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function noAliasFolders(): iterable
    {
        yield 'empty' => [''];
        yield 'the root folder' => ['/'];
    }

    public function testAliasChangesEachWrite(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);
        $output->alias('/srv', '/home/u/app');

        $output->line('a /srv/a');
        $output->success('b /srv/b');
        $output->write('c /srv/c');
        $output->error('d /srv/d');

        $this->assertSame(
            'a /home/u/app/a' . PHP_EOL . 'b /home/u/app/b' . PHP_EOL . 'c /home/u/app/c',
            $this->readStdout()
        );
        $this->assertSame('d /home/u/app/d' . PHP_EOL, $this->readStderr());
    }

    /**
     * @dataProvider aliasBoundaries
     */
    public function testAliasChangesOnlyTheWholeFolder(string $text, string $expected): void
    {
        $output = new Output($this->stdout, $this->stderr, false);
        $output->alias('/srv', '/home/u/app');

        $output->line($text);

        $this->assertSame($expected . PHP_EOL, $this->readStdout());
    }

    public function testAliasChangesThePrompt(): void
    {
        $this->answers("\n");
        $output = $this->interactive();
        $output->alias('/srv', '/home/u/app');

        $output->ask('Folder', '/srv/a');

        $this->assertSame('Folder [/home/u/app/a]: ', $this->readStderr());
    }

    public function testAliasKeepsTheTableColumnsInLine(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);
        $output->alias('/srv', '/home/u/app');

        $output->table(
            ['ITEM', 'VALUE', 'ORIGIN'],
            [['root', '/srv', 'crest.php'], ['flavor', 'adr', 'declared']]
        );

        $this->assertSame(
            'ITEM    VALUE        ORIGIN' . PHP_EOL
            . 'root    /home/u/app  crest.php' . PHP_EOL
            . 'flavor  adr          declared' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAliasLeavesTextThatIsNotUtf8(): void
    {
        $output = new Output($this->stdout, $this->stderr, false);
        $output->alias('/srv', '/home/u/app');

        $output->write("\xff /srv");

        $this->assertSame("\xff /srv", $this->readStdout());
    }

    public function testAliasQuotesTheFolder(): void
    {
        // The folder is text, not a pattern.
        $output = new Output($this->stdout, $this->stderr, false);
        $output->alias('/a+b', '/h');

        $output->line('/aab /a+b');

        $this->assertSame('/aab /h' . PHP_EOL, $this->readStdout());
    }

    public function testAliasReplacesOneTime(): void
    {
        // The host root contains the container root.
        $output = new Output($this->stdout, $this->stderr, false);
        $output->alias('/srv', '/srv/www/app');

        $output->line('/srv/a');
        $output->table(['A', 'B'], [['x', '/srv/b']]);

        $this->assertSame(
            '/srv/www/app/a' . PHP_EOL . 'A  B' . PHP_EOL . 'x  /srv/www/app/b' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAskAgainAfterAnAnswerThatTheCheckRefuses(): void
    {
        $this->answers("bad\ngood\n");

        $answer = $this->interactive()->ask(
            'Name',
            'x',
            static fn (string $answer): ?string => 'bad' === $answer ? 'not that one' : null
        );

        $this->assertSame('good', $answer);
        $this->assertSame('', $this->readStdout());
        $this->assertSame('Name [x]: not that one' . PHP_EOL . 'Name [x]: ', $this->readStderr());
    }

    public function testAskReturnsTheAnswerWithoutTheLineEnd(): void
    {
        $this->answers("Shop\n");

        $this->assertSame('Shop', $this->interactive()->ask('Namespace', 'App'));
    }

    public function testAskReturnsTheDefaultForAnEmptyAnswer(): void
    {
        $this->answers("\n");

        $this->assertSame('App', $this->interactive()->ask('Namespace', 'App'));
    }

    public function testAskShowsNoBracketsForAnEmptyDefault(): void
    {
        $this->answers("\n");

        $this->assertSame('', $this->interactive()->ask('Front', ''));
        $this->assertSame('Front: ', $this->readStderr());
    }

    public function testAskStopsWhenTheInputEnds(): void
    {
        // Ctrl+D. Nothing is typed, so there is no answer. The error starts
        // on its own line.
        try {
            $this->interactive()->ask('Namespace', 'App');
            $this->fail('ask() did not stop');
        } catch (Exception $exception) {
            $this->assertSame('no answer; input ended', $exception->getMessage());
        }

        $this->assertSame('Namespace [App]: ' . PHP_EOL, $this->readStderr());
    }

    public function testAskWithoutInteractionReturnsTheDefaultAndWritesNothing(): void
    {
        $this->answers("Shop\n");

        $output = new Output($this->stdout, $this->stderr, false, $this->stdin, false);

        $this->assertSame('App', $output->ask('Namespace', 'App'));
        $this->assertSame('', $this->readStdout());
        $this->assertSame('', $this->readStderr());
    }

    public function testAskWithoutInteractionStopsWhenTheDefaultFailsTheCheck(): void
    {
        // Without interaction, crest cannot ask again.
        $output = new Output($this->stdout, $this->stderr, false, $this->stdin, false);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('bad default');

        $output->ask('Name', 'x', static fn (string $answer): string => 'bad default');
    }

    public function testAskWritesTheQuestionToStderr(): void
    {
        // A redirect of stdout keeps the prompt on the terminal, and out of
        // the file.
        $this->answers("Shop\n");

        $this->interactive()->ask('Namespace', 'App');

        $this->assertSame('', $this->readStdout());
        $this->assertSame('Namespace [App]: ', $this->readStderr());
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

    public function testChoiceAsksAgainForAnotherAnswer(): void
    {
        $this->answers("podman\ndocker\n");

        $this->assertSame('docker', $this->interactive()->choice('Runtime', ['host', 'docker'], 'host'));
        $this->assertSame(
            "Runtime (host, docker) [host]: 'podman' is not one of: host, docker" . PHP_EOL
            . 'Runtime (host, docker) [host]: ',
            $this->readStderr()
        );
    }

    public function testChoiceIgnoresTheCaseAndGivesTheSpellingOfTheList(): void
    {
        $this->answers("DOCKER\n");

        $this->assertSame('Docker', $this->interactive()->choice('Runtime', ['host', 'Docker'], 'host'));
    }

    public function testChoiceReturnsTheDefaultForAnEmptyAnswer(): void
    {
        $this->answers("\n");

        $this->assertSame('host', $this->interactive()->choice('Runtime', ['host', 'docker'], 'host'));
    }

    public function testDisableInteractionStopsTheQuestions(): void
    {
        $this->answers("Shop\n");

        $output = $this->interactive();
        $output->disableInteraction();

        $this->assertSame('App', $output->ask('Namespace', 'App'));
        $this->assertSame('', $this->readStdout());
        $this->assertSame('', $this->readStderr());
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

    public function testInteractionFollowsTheTerminalByDefault(): void
    {
        // php://memory is never a terminal: a pipe or CI asks no questions,
        // and nothing waits for input.
        $this->answers("Shop\n");

        $output = new Output($this->stdout, $this->stderr, false, $this->stdin);

        $this->assertSame('App', $output->ask('Namespace', 'App'));
        $this->assertSame('', $this->readStdout());
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

    /**
     * @dataProvider noAliasFolders
     */
    public function testNoAliasForAnEmptyOrTheRootFolder(string $from): void
    {
        $output = new Output($this->stdout, $this->stderr, false);
        $output->alias($from, '/h');

        $output->line('/srv /');

        $this->assertSame('/srv /' . PHP_EOL, $this->readStdout());
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

    private function interactive(): Output
    {
        return new Output($this->stdout, $this->stderr, false, $this->stdin, true);
    }
}
