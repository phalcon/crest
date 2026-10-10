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
use Crest\Console\Kernel;
use Crest\Console\Output;
use Crest\Console\Parsing\Extracted;
use Crest\Process\Compose;
use Crest\Process\Runner;
use Crest\Process\ShellRunner;
use Crest\Project\Config;
use Crest\Project\Manifest;
use Crest\Project\Runtime;
use Throwable;

use function array_slice;
use function basename;
use function dirname;
use function getenv;
use function in_array;
use function is_file;
use function is_string;
use function realpath;
use function sprintf;
use function stream_isatty;

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
 * user typed. The arguments are read with the rules of the parser:
 * Kernel::command() and Definition::extract().
 */
final class HandOff
{
    /**
     * Where composer puts the crest of a project that requires it.
     */
    public const BINARY = 'vendor/bin/crest';

    /**
     * The version of the hand-off protocol: Commands::HOST, Commands::ON_HOST
     * and the options of Kernel::paths(). The global crest sends it to the
     * crest of the project in VARIABLE. Change it when one of them changes.
     */
    public const PROTOCOL = 1;

    /**
     * The environment variable that holds the protocol of the global crest.
     */
    public const VARIABLE = 'CREST_HANDOFF';

    private readonly Runner $runner;

    private readonly bool $terminal;

    private readonly string $vendor;

    /**
     * @param string    $vendor   The vendor folder of the autoloader that the
     *                            running crest loaded.
     * @param bool|null $terminal Whether stdin is a terminal. Null asks the
     *                            stream.
     */
    public function __construct(string $vendor, ?Runner $runner = null, ?bool $terminal = null)
    {
        $this->runner   = $runner ?? new ShellRunner();
        $this->terminal = $terminal ?? stream_isatty(STDIN);
        $this->vendor   = $vendor;
    }

    /**
     * @param list<string> $argv
     *
     * @return int|null The exit status of the crest of the project. Null when
     *                  this crest runs the call.
     */
    public function run(array $argv, Output $output): ?int
    {
        $mismatch = $this->mismatch();

        if (null !== $mismatch) {
            $output->error(Commands::NAME . ': ' . $mismatch);

            return 1;
        }

        $tokens = array_slice($argv, 1);

        if (false === $this->isProjectCall($tokens)) {
            return null;
        }

        $paths = Kernel::paths()->extract($tokens);
        $file  = $this->file($paths);

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
            return $this->pass($file, $root, $binary, $tokens, $paths);
        } catch (Throwable $throwable) {
            // As the kernel does: one crest line, not a PHP trace. A broken
            // crest.php or a missing docker comes here.
            $output->error(Commands::NAME . ': ' . $throwable->getMessage());

            return 1;
        }
    }

    /**
     * What the crest of the project gets in its environment: the protocol of
     * this crest.
     *
     * @return array<string, string>
     */
    private function environment(): array
    {
        return [self::VARIABLE => (string) self::PROTOCOL];
    }

    /**
     * The crest.php of the project, by the root rule. Null when there is
     * none. Null also when the path in an option does not exist: this crest
     * then runs the call and reports the path.
     */
    private function file(Extracted $paths): ?string
    {
        $directory = $this->path($paths, 'directory');
        $start     = realpath('' === $directory ? '.' : $directory);

        if (false === $start) {
            return null;
        }

        try {
            return Config::file($start, $this->path($paths, 'config'));
        } catch (Exception) {
            return null;
        }
    }

    /**
     * The tokens for the crest in the container: without the options of
     * Kernel::paths(), which hold host paths, and with only the file name of
     * --config. The container works in the root, the folder of the config
     * file, so the name finds the file there. The command name stays first.
     *
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    private function forContainer(array $tokens, Extracted $paths): array
    {
        $config  = $paths->option('config');
        $named   = true === is_string($config) ? ['--config=' . basename($config)] : [];
        $command = Kernel::command($tokens);

        if (null === $command) {
            return [...$named, ...$paths->rest];
        }

        return [$command, ...$named, ...array_slice($paths->rest, 1)];
    }

    /**
     * `docker compose exec` runs the crest of the project in its service.
     * The working folder of the service is the project root (A7).
     *
     * @param list<string> $tokens
     *
     * @return non-empty-list<string>
     */
    private function inContainer(string $service, array $tokens, Extracted $paths): array
    {
        return [
            'docker',
            'compose',
            ...Compose::exec(
                $service,
                [self::BINARY, ...$this->forContainer($tokens, $paths)],
                $this->terminal,
                $this->environment()
            ),
        ];
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
        $command = Kernel::command($tokens);

        if (null === $command) {
            return false === in_array($tokens[0] ?? null, Kernel::VERSION, true);
        }

        return false === in_array($command, Commands::HOST, true);
    }

    /**
     * The crest of the project is this crest: it runs with the autoloader in
     * <root>/vendor. Without this check, the crest of the project passes the
     * call to itself for ever. The package folder cannot tell: with a
     * symlinked path repository, a global crest and the crest of the project
     * have the same package folder.
     */
    private function isSelf(string $root): bool
    {
        return realpath($root . '/vendor') === realpath($this->vendor);
    }

    /**
     * The error when the global crest that passed this call has another
     * protocol. Null when it has this protocol, or when no global crest
     * passed the call: the user ran this crest directly.
     */
    private function mismatch(): ?string
    {
        $received = getenv(self::VARIABLE);

        if (false === $received || (string) self::PROTOCOL === $received) {
            return null;
        }

        $newer = (int) $received > self::PROTOCOL;

        return sprintf(
            'the global crest is %s than the crest of this project (hand-off %s, this crest %d); run %s',
            true === $newer ? 'newer' : 'older',
            $received,
            self::PROTOCOL,
            true === $newer
                ? sprintf("'composer update %s' in the project", Commands::PACKAGE)
                : sprintf("'composer global update %s'", Commands::PACKAGE)
        );
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
     * host (Commands::ON_HOST).
     *
     * Only the runtime key is read (Runtime::fromFile()).
     *
     * @param list<string> $tokens
     */
    private function pass(string $file, string $root, string $binary, array $tokens, Extracted $paths): int
    {
        $runtime = Runtime::fromFile($file);

        if (true === $runtime->isDocker() && false === in_array($tokens[0] ?? '', Commands::ON_HOST, true)) {
            return $this->runner->run($this->inContainer($runtime->service, $tokens, $paths), $root);
        }

        return $this->runner->run([PHP_BINARY, $binary, ...$tokens], null, $this->environment());
    }

    /**
     * The value of a path option. Empty when the option is absent or has no
     * value: then the working directory, or no config file.
     */
    private function path(Extracted $paths, string $name): string
    {
        $value = $paths->option($name);

        return true === is_string($value) ? $value : '';
    }
}
