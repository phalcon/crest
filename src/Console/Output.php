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

use Crest\Console\Parsing\Definition;

use function fwrite;
use function getenv;
use function implode;
use function max;
use function mb_strlen;
use function rtrim;
use function str_pad;
use function stream_isatty;

use const PHP_EOL;
use const STDERR;
use const STDOUT;

/**
 * Everything the console writes goes through here. The two streams are
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

    /** @var resource */
    private $stderr;

    /** @var resource */
    private $stdout;

    /**
     * @param resource  $stdout
     * @param resource  $stderr
     * @param bool|null $decorated Null auto-detects from NO_COLOR and tty.
     */
    public function __construct($stdout = STDOUT, $stderr = STDERR, ?bool $decorated = null)
    {
        $this->stdout    = $stdout;
        $this->stderr    = $stderr;
        $this->decorated = $decorated ?? $this->detectDecoration($stdout);
    }

    /**
     * The identity line a run opens with: the chevron mark, then whatever the
     * caller puts after it - by convention the tool name and its version.
     *
     * The mark is coloured through decorate() rather than carrying its own
     * escapes, so a piped run or one with NO_COLOR set gets the glyph and no
     * control codes.
     */
    public function banner(string $text): void
    {
        $this->line($this->decorate(self::MARK, self::COLOR_ORANGE) . ' ' . $text);
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
