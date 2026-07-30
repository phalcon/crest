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

use Crest\Console\Command\Command;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Parsing\Definition;
use Crest\Console\Parsing\Exceptions\Exception as ParsingException;
use Throwable;

use function array_search;
use function array_slice;
use function in_array;
use function ksort;
use function str_starts_with;

use const STDERR;
use const STDOUT;

/**
 * Resolves argv to a command, merges the global options into its definition,
 * binds, runs, and turns console exceptions into clean stderr lines.
 *
 * Owns no identity: the tool's name, its package and its command set are
 * supplied by the caller, which is what allows this class to be moved to
 * phalcon/console without edits.
 */
final class Kernel
{
    private string $name;

    private Output $output;

    private string $package;

    private Registry $registry;

    /**
     * @param string    $name      Tool name used in errors, banner and usage.
     * @param Registry  $registry  Seeded by the owning tool; never defaulted.
     * @param string    $package   Composer package name, for --version.
     * @param resource  $stdout
     * @param resource  $stderr
     * @param bool|null $decorated
     */
    public function __construct(
        string $name,
        Registry $registry,
        string $package,
        $stdout = STDOUT,
        $stderr = STDERR,
        ?bool $decorated = null,
    ) {
        $this->name     = $name;
        $this->registry = $registry;
        $this->package  = $package;
        $this->output   = new Output($stdout, $stderr, $decorated);
    }

    /**
     * The options every command gets for free.
     */
    public static function globals(): Definition
    {
        return Definition::for('')
            ->option('config=s', 'Path to the project configuration file')
            ->option('directory=s', 'Project root override')
            ->option('trace', 'Show the full exception trace')
            ->option('help|h', 'Show this help')
            ->option('quiet|q', 'Suppress non-essential output');
    }

    /**
     * @param list<string> $argv
     */
    public function handle(array $argv): int
    {
        $tokens = array_slice($argv, 1);
        $first  = $tokens[0] ?? null;

        if ('--version' === $first || '-V' === $first) {
            $this->output->line($this->name . ' ' . $this->version());

            return 0;
        }

        if (null === $first || true === str_starts_with($first, '-')) {
            $this->listCommands();

            return 0;
        }

        $trace = in_array('--trace', $this->beforeLiteral($tokens), true);

        try {
            return $this->run($first, array_slice($tokens, 1));
        } catch (Exception | ParsingException $exception) {
            // Both clusters throw their own type; both are user-facing errors,
            // not bugs, so both render as one clean line.
            $this->output->error($this->name . ': ' . $exception->getMessage());

            if (true === $trace) {
                $this->output->error($exception->getTraceAsString());
            }

            return 1;
        } catch (Throwable $throwable) {
            $this->output->error($this->name . ': ' . $throwable->getMessage());

            if (true === $trace) {
                $this->output->error($throwable->getTraceAsString());
            }

            return 1;
        }
    }

    /**
     * Tokens up to the first `--`. Everything after it is literal, so a route
     * path of `--help` must not be mistaken for a request for usage.
     *
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private function beforeLiteral(array $tokens): array
    {
        $position = array_search('--', $tokens, true);

        return false === $position ? $tokens : array_slice($tokens, 0, $position);
    }

    private function listCommands(): void
    {
        $commands = $this->registry->all();
        ksort($commands);

        $rows = [];
        foreach ($commands as $name => $class) {
            $command = new $class();
            $rows[]  = [$name, $command->define()->getDescription()];
        }

        $this->output->banner($this->name . ' ' . $this->version());
        $this->output->line();
        $this->output->table(['COMMAND', 'DESCRIPTION'], $rows);
    }

    /**
     * @param list<string> $tokens
     */
    private function run(string $name, array $tokens): int
    {
        $class = $this->registry->get($name);

        /** @var Command $command */
        $command = new $class();

        $definition = $command->define()->merge(self::globals());
        $flags      = $this->beforeLiteral($tokens);

        if (true === in_array('--help', $flags, true) || true === in_array('-h', $flags, true)) {
            $this->output->usage($this->name, $definition);

            return 0;
        }

        return $command->handle(new Input($name, $definition->bind($tokens)), $this->output);
    }

    private function version(): string
    {
        return PackageVersion::of($this->package);
    }
}
