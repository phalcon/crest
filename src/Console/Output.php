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
    public const COLOR_GREEN = "\033[32m";
    public const COLOR_RED   = "\033[31m";
    public const COLOR_RESET = "\033[0m";

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
