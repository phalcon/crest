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

use Crest\Console\Exceptions\Exception;
use Crest\Console\Output;
use Crest\Process\Runner;
use Crest\Process\ShellRunner;
use Crest\Project\Config;
use Crest\Project\Manifest;
use Crest\Project\Runtime;
use Throwable;

use function array_slice;
use function count;
use function dirname;
use function in_array;
use function is_file;
use function realpath;
use function sprintf;
use function str_starts_with;
use function stream_isatty;
use function strlen;
use function substr;

use const PHP_BINARY;
use const STDIN;

/**
 * Passes a project command from a global crest to the crest of the project.
 *
 * A global crest loads its own autoloader. The project commands need the
 * autoloader, the Phalcon and the crest version of the project. Thus, in a
 * project, a global crest runs vendor/bin/crest of the project with the same
 * arguments, and returns its exit status.
 *
 * The project is the folder of crest.php, by the root rule of all commands
 * (Config::file()): the file that --config names, or the nearest crest.php
 * above --directory or the working directory.
 *
 * With a docker runtime in crest.php, the crest of the project runs in its
 * compose service: `docker compose exec <service> vendor/bin/crest`. Thus a
 * v5 project works on a host without ext-phalcon.
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

    /**
     * The commands that run on the host also with a docker runtime: `serve`
     * and its alias start PHP's built-in server, which must listen on the
     * host (A8).
     */
    private const ON_HOST = ['serve', 'server'];

    /**
     * The global options that hold host paths.
     */
    private const PATH_OPTIONS = ['--config', '--directory'];

    private readonly Runner $runner;

    private readonly string $self;

    private readonly bool $terminal;

    /**
     * @param string|null $self     The root of the running crest package. Null
     *                              for this package. A test gives another root.
     * @param bool|null   $terminal Whether stdin is a terminal. Null asks the
     *                              stream.
     */
    public function __construct(?Runner $runner = null, ?string $self = null, ?bool $terminal = null)
    {
        $this->runner   = $runner ?? new ShellRunner();
        $this->self     = $self ?? Paths::root();
        $this->terminal = $terminal ?? stream_isatty(STDIN);
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

        $file = $this->file($tokens);

        if (null === $file) {
            return null;
        }

        $root   = dirname($file);
        $binary = $root . '/' . self::BINARY;

        if (false === is_file($binary)) {
            $output->error(Commands::NAME . ': ' . $this->missing($root));

            return 1;
        }

        if (true === $this->isSelf($root)) {
            return null;
        }

        try {
            return $this->pass($file, $root, $binary, $tokens);
        } catch (Throwable $throwable) {
            // As the kernel does: one crest line, not a PHP trace. A broken
            // crest.php or a missing docker comes here.
            $output->error(Commands::NAME . ': ' . $throwable->getMessage());

            return 1;
        }
    }

    /**
     * The crest.php of the project, by the root rule. Null when there is
     * none. Null also when the path in an option does not exist: this crest
     * then runs the call and reports the path.
     *
     * @param list<string> $tokens
     */
    private function file(array $tokens): ?string
    {
        $directory = $this->value($tokens, 'directory');
        $start     = realpath('' === $directory ? '.' : $directory);

        if (false === $start) {
            return null;
        }

        try {
            return Config::file($start, $this->value($tokens, 'config'));
        } catch (Exception) {
            return null;
        }
    }

    /**
     * `docker compose exec` runs the crest of the project in its service.
     * The working folder of the service is the project root (A7). -T when
     * stdin is not a terminal, for CI and pipes.
     *
     * @param list<string> $tokens
     *
     * @return non-empty-list<string>
     */
    private function inContainer(string $service, array $tokens): array
    {
        $command = ['docker', 'compose', 'exec'];

        if (false === $this->terminal) {
            $command[] = '-T';
        }

        return [...$command, $service, self::BINARY, ...$this->withoutPaths($tokens)];
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
     * The project has crest.php but no vendor/bin/crest, so no crest can run
     * a project command there. A project that requires crest has no vendor/
     * yet. Another project must require crest first: this crest does not have
     * the autoloader and the Phalcon of the project.
     */
    private function missing(string $root): string
    {
        if (true === Manifest::requires($root, Commands::PACKAGE)) {
            return sprintf(
                "%s has no %s; run 'crest install' or 'composer install' first",
                $root,
                self::BINARY
            );
        }

        return sprintf(
            "%s does not require %s; run 'composer require --dev %s'",
            $root,
            Commands::PACKAGE,
            Commands::PACKAGE
        );
    }

    /**
     * Runs the crest of the project: in its compose service for a docker
     * runtime, else with the PHP of the host. `serve` always runs on the
     * host (ON_HOST).
     *
     * Only the runtime key is read. The other keys belong to the crest of the
     * project, which can be newer than this crest and accept values that this
     * crest does not know.
     *
     * @param list<string> $tokens
     */
    private function pass(string $file, string $root, string $binary, array $tokens): int
    {
        /** @var array<string, mixed> $declared */
        $declared = require $file;
        $runtime  = Runtime::fromConfig($declared['runtime'] ?? null);

        if (true === $runtime->isDocker() && false === in_array($tokens[0] ?? '', self::ON_HOST, true)) {
            return $this->runner->run($this->inContainer($runtime->service, $tokens), $root);
        }

        return $this->runner->run([PHP_BINARY, $binary, ...$tokens]);
    }

    /**
     * The value of a global path option: `--name=value` or `--name value`.
     * The last value wins, as in the parser. After `--`, tokens are values,
     * not options. Empty when the option is absent.
     *
     * @param list<string> $tokens
     */
    private function value(array $tokens, string $name): string
    {
        $option = '--' . $name;
        $value  = '';

        foreach ($tokens as $index => $token) {
            if ('--' === $token) {
                break;
            }

            if (true === str_starts_with($token, $option . '=')) {
                $value = substr($token, strlen($option) + 1);
            }

            if ($option === $token) {
                $value = $tokens[$index + 1] ?? '';
            }
        }

        return $value;
    }

    /**
     * The tokens without --directory and --config and their values. They are
     * host paths, and the root is already found. A value is the next token
     * when it does not start with `-`, as in the parser. After `--`, tokens
     * are values and stay.
     *
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private function withoutPaths(array $tokens): array
    {
        $kept  = [];
        $count = count($tokens);

        for ($index = 0; $index < $count; $index++) {
            $token = $tokens[$index];

            if ('--' === $token) {
                return [...$kept, ...array_slice($tokens, $index)];
            }

            if (true === in_array($token, self::PATH_OPTIONS, true)) {
                if (false === str_starts_with($tokens[$index + 1] ?? '-', '-')) {
                    $index++;
                }

                continue;
            }

            if (
                true === str_starts_with($token, '--config=')
                || true === str_starts_with($token, '--directory=')
            ) {
                continue;
            }

            $kept[] = $token;
        }

        return $kept;
    }
}
