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

namespace Crest;

use Crest\Console\Output;
use Crest\Process\Runner;
use Crest\Process\ShellRunner;
use Crest\Project\Locator;

use function array_slice;
use function file_get_contents;
use function in_array;
use function is_file;
use function json_decode;
use function realpath;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;

use const PHP_BINARY;

/**
 * Passes a project command from a global crest to the crest of the project.
 *
 * A global crest loads its own autoloader. The project commands need the
 * autoloader, the Phalcon and the crest version of the project. Thus, in a
 * project, a global crest runs vendor/bin/crest of the project with the same
 * arguments, and returns its exit status.
 *
 * The host commands (Commands::HOST) and --version stay in the crest that the
 * user typed.
 */
final class HandOff
{
    /**
     * Where composer puts the crest of a project that requires it.
     */
    public const BINARY = 'vendor/bin/crest';

    private readonly Runner $runner;

    private readonly string $self;

    /**
     * @param string|null $self The root of the running crest package. Null
     *                          for this package. A test gives another root.
     */
    public function __construct(?Runner $runner = null, ?string $self = null)
    {
        $this->runner = $runner ?? new ShellRunner();
        $this->self   = $self ?? Paths::root();
    }

    /**
     * @param list<string> $argv
     *
     * @return int|null The exit status of the crest of the project. Null when
     *                  this crest runs the call.
     */
    public function run(array $argv, Output $output): ?int
    {
        $tokens = array_slice($argv, 1);

        if (false === $this->isProjectCall($tokens)) {
            return null;
        }

        $start = realpath($this->directory($tokens));
        $root  = false === $start ? null : Locator::project($start);

        if (null === $root) {
            return null;
        }

        $binary = $root . '/' . self::BINARY;

        if (false === is_file($binary)) {
            return $this->missing($root, $output);
        }

        if (true === $this->isSelf($root)) {
            return null;
        }

        return $this->runner->run([PHP_BINARY, $binary, ...$tokens]);
    }

    /**
     * Where the walk up starts: the --directory value, or the working
     * directory. The last value wins, as in the parser. After `--`, tokens
     * are values, not options. An empty value reads as absent.
     *
     * @param list<string> $tokens
     */
    private function directory(array $tokens): string
    {
        $directory = '';

        foreach ($tokens as $index => $token) {
            if ('--' === $token) {
                break;
            }

            if (true === str_starts_with($token, '--directory=')) {
                $directory = substr($token, strlen('--directory='));
            }

            if ('--directory' === $token) {
                $directory = $tokens[$index + 1] ?? '';
            }
        }

        return '' === $directory ? '.' : $directory;
    }

    /**
     * The listing (no command, or an option first) and each command that is
     * not a host command belong to the project. --version gives the version
     * of the crest that the user typed.
     *
     * @param list<string> $tokens
     */
    private function isProjectCall(array $tokens): bool
    {
        $first = $tokens[0] ?? null;

        if (null === $first || true === str_starts_with($first, '-')) {
            return false === in_array($first, ['--version', '-V'], true);
        }

        return false === in_array($first, Commands::HOST, true);
    }

    /**
     * The crest of the project is this package. Without this check, the
     * crest of the project passes the call to itself for ever.
     */
    private function isSelf(string $root): bool
    {
        return realpath($root . '/vendor/' . Commands::PACKAGE) === realpath($this->self);
    }

    /**
     * No vendor/bin/crest. A project that requires crest has no vendor/ yet:
     * no crest can run a project command there, so this is an error. Other
     * projects do not use crest: this crest runs the call, as before.
     */
    private function missing(string $root, Output $output): ?int
    {
        /** @var array{require?: array<string, string>, require-dev?: array<string, string>}|null $composer */
        $composer = json_decode((string) file_get_contents($root . '/composer.json'), true);

        if (
            false === isset($composer['require'][Commands::PACKAGE])
            && false === isset($composer['require-dev'][Commands::PACKAGE])
        ) {
            return null;
        }

        $output->error(
            sprintf(
                "%s: %s has no %s; run 'crest install' or 'composer install' first",
                Commands::NAME,
                $root,
                self::BINARY
            )
        );

        return 1;
    }
}
