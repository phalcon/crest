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

namespace Crest\Console;

use Crest\Console\Exceptions\Exception;
use Crest\Console\Parsing\Definition;

use function array_combine;
use function array_map;
use function fgets;
use function fwrite;
use function getenv;
use function implode;
use function max;
use function mb_strlen;
use function rtrim;
use function sprintf;
use function str_pad;
use function stream_isatty;
use function strtolower;
use function trim;

use const PHP_EOL;
use const STDERR;
use const STDIN;
use const STDOUT;

/**
 * Everything the console writes goes through here, and the answers to its
 * questions come in here. The input stream and the two output streams are
 * injected so the whole kernel is testable against php://memory with no
 * process spawning.
 */
final class Output
{
    public const COLOR_GREEN  = "\033[32m";

    public const COLOR_ORANGE = "\033[38;5;208m";

    public const COLOR_RED    = "\033[31m";

    public const COLOR_RESET  = "\033[0m";

    /**
     * The glyph a banner opens with. Named for its shape rather than for any
     * one tool.
     */
    public const MARK = '⟩⟩⟩';

    private bool $decorated;

    private bool $interactive;

    /** @var resource */
    private $stderr;

    /** @var resource */
    private $stdin;

    /** @var resource */
    private $stdout;

    /**
     * @param resource  $stdout
     * @param resource  $stderr
     * @param bool|null $decorated   Null auto-detects from NO_COLOR and tty.
     * @param resource  $stdin
     * @param bool|null $interactive Null asks questions only when stdin is a
     *                               terminal.
     */
    public function __construct(
        $stdout = STDOUT,
        $stderr = STDERR,
        ?bool $decorated = null,
        $stdin = STDIN,
        ?bool $interactive = null,
    ) {
        $this->stdout      = $stdout;
        $this->stderr      = $stderr;
        $this->stdin       = $stdin;
        $this->decorated   = $decorated ?? $this->detectDecoration($stdout);
        $this->interactive = $interactive ?? stream_isatty($stdin);
    }

    /**
     * Asks for a value and returns the answer. An empty answer gives the
     * default. When the run is not interactive, nothing is asked and the
     * default is the answer. The question goes to stderr, as the errors do:
     * a redirect of stdout keeps it on the terminal, and out of the file.
     *
     * The check returns null for a good answer, or the error text. An
     * interactive run shows the error and asks again. A run that is not
     * interactive cannot ask again, so the error stops the command.
     *
     * @param (callable(string): ?string)|null $check
     */
    public function ask(string $question, string $default, ?callable $check = null): string
    {
        while (true) {
            $answer = $default;

            if (true === $this->interactive) {
                fwrite($this->stderr, '' === $default ? $question . ': ' : $question . ' [' . $default . ']: ');
                $answer = $this->answer($default);
            }

            $error = null === $check ? null : $check($answer);

            if (null === $error) {
                return $answer;
            }

            if (false === $this->interactive) {
                throw new Exception($error);
            }

            $this->error($error);
        }
    }

    /**
     * The identity line a run opens with: the chevron mark, then whatever the
     * caller puts after it - by convention the tool name and its version.
     *
     * The mark is colored through decorate() rather than carrying its own
     * escapes, so a piped run or one with NO_COLOR set gets the glyph and no
     * control codes.
     */
    public function banner(string $text): void
    {
        $this->line($this->decorate(self::MARK, self::COLOR_ORANGE) . ' ' . $text);
    }

    /**
     * Asks for one of the options. The case of the answer does not matter:
     * the result is the option as the list spells it. Another answer is an
     * error.
     *
     * @param list<string> $options
     */
    public function choice(string $question, array $options, string $default): string
    {
        $list    = implode(', ', $options);
        $byLower = array_combine(array_map(strtolower(...), $options), $options);

        $answer = $this->ask(
            $question . ' (' . $list . ')',
            $default,
            static fn (string $answer): ?string => true === isset($byLower[strtolower($answer)])
                ? null
                : sprintf("'%s' is not one of: %s", $answer, $list)
        );

        return $byLower[strtolower($answer)];
    }

    /**
     * The command listing: banner, blank line, one row per command.
     *
     * Presentation only - the caller supplies the descriptions, so this class
     * stays unaware of how a registry answers. Shared because the kernel prints
     * this listing when invoked with no arguments and an addressable `list`
     * command prints the same thing, and two copies of the layout had to be
     * kept in agreement by hand.
     *
     * @param array<string, string> $descriptions Command name => description.
     */
    public function commandTable(string $banner, array $descriptions): void
    {
        $rows = [];
        foreach ($descriptions as $name => $description) {
            $rows[] = [$name, $description];
        }

        $this->banner($banner);
        $this->line();
        $this->table(['COMMAND', 'DESCRIPTION'], $rows);
    }

    /**
     * No more questions in this run: each one gives its default. The kernel
     * calls this for --no-interaction.
     */
    public function disableInteraction(): void
    {
        $this->interactive = false;
    }

    public function error(string $text): void
    {
        fwrite($this->stderr, $this->decorate($text, self::COLOR_RED) . PHP_EOL);
    }

    public function line(string $text = ''): void
    {
        fwrite($this->stdout, $text . PHP_EOL);
    }

    public function success(string $text): void
    {
        fwrite($this->stdout, $this->decorate($text, self::COLOR_GREEN) . PHP_EOL);
    }

    /**
     * @param list<string>       $headers
     * @param list<list<string>> $rows
     * @param bool               $withHeaders False aligns the columns without
     *                                        printing the header row - used by
     *                                        usage(), where the columns have no
     *                                        titles.
     */
    public function table(array $headers, array $rows, bool $withHeaders = true): void
    {
        $widths = [];
        foreach ([$headers, ...$rows] as $row) {
            foreach ($row as $index => $cell) {
                $widths[$index] = max($widths[$index] ?? 0, mb_strlen($cell));
            }
        }

        $printable = true === $withHeaders ? [$headers, ...$rows] : $rows;

        foreach ($printable as $row) {
            $cells = [];
            foreach ($row as $index => $cell) {
                $cells[] = str_pad($cell, $widths[$index]);
            }

            $this->line(rtrim(implode('  ', $cells)));
        }
    }

    /**
     * Renders a command's usage block from its definition. Presentation lives
     * here rather than on Definition so the schema stays a pure data structure.
     */
    public function usage(string $tool, Definition $definition): void
    {
        $line = 'Usage: ' . $tool . ' ' . $definition->getName();

        foreach ($definition->getArguments() as $argument) {
            $line .= true === $argument->required
                ? ' <' . $argument->name . '>'
                : ' [' . $argument->name . ']';
        }

        if ([] !== $definition->getOptions()) {
            $line .= ' [options]';
        }

        if ('' !== $definition->getDescription()) {
            $this->line($definition->getDescription());
            $this->line();
        }

        $this->line($line);

        if ([] !== $definition->getArguments()) {
            $this->line();
            $this->line('Arguments:');

            $rows = [];
            foreach ($definition->getArguments() as $argument) {
                $rows[] = ['  ' . $argument->name, $argument->description];
            }

            $this->table(['', ''], $rows, false);
        }

        if ([] !== $definition->getOptions()) {
            $this->line();
            $this->line('Options:');

            $rows = [];
            foreach ($definition->getOptions() as $option) {
                $flag = '  --' . $option->name;

                if (null !== $option->short) {
                    $flag .= ', -' . $option->short;
                }

                $rows[] = [$flag, $option->description];
            }

            $this->table(['', ''], $rows, false);
        }
    }

    public function write(string $text): void
    {
        fwrite($this->stdout, $text);
    }

    /**
     * One line from the input stream, without the line end. An empty line
     * gives the default.
     */
    private function answer(string $default): string
    {
        $line = fgets($this->stdin);

        if (false === $line) {
            throw new Exception('no answer; input ended');
        }

        $line = trim($line);

        return '' === $line ? $default : $line;
    }

    private function decorate(string $text, string $color): string
    {
        if (false === $this->decorated) {
            return $text;
        }

        return $color . $text . self::COLOR_RESET;
    }

    /**
     * @param resource $stream
     */
    private function detectDecoration($stream): bool
    {
        if (false !== getenv('NO_COLOR')) {
            return false;
        }

        return stream_isatty($stream);
    }
}
