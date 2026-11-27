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

namespace Crest\Process;

use Crest\Console\Exceptions\Exception;

use function basename;
use function explode;
use function getenv;
use function is_dir;
use function is_executable;
use function is_file;
use function proc_close;
use function proc_open;
use function sprintf;
use function str_ends_with;
use function strtolower;

use const PATH_SEPARATOR;

/**
 * Runs a program as a child process that shares crest's terminal.
 *
 * The child gets crest's own stdin, stdout and stderr: an empty descriptor
 * list does that. Thus `docker compose exec` sees a terminal, and composer
 * shows its progress. crest captures nothing.
 *
 * Two checks come first, because proc_open() does neither. A missing program
 * gives no clear error. A missing working directory is ignored, and the child
 * runs in crest's own directory.
 *
 * The command is an argv list, not a shell string. Nothing in it is
 * interpreted.
 */
final class ShellRunner implements Runner
{
    public function run(array $command, ?string $directory = null, array $environment = []): int
    {
        if (false === $this->exists($command[0])) {
            throw new Exception(
                sprintf("'%s' was not found; install it or add it to the PATH", $command[0])
            );
        }

        if (null !== $directory && false === is_dir($directory)) {
            throw new Exception(sprintf('%s is not a directory', $directory));
        }

        return proc_close($this->start($command, $directory, $environment));
    }

    /**
     * Whether the program can run. A path must name an executable file. A
     * bare name must be an executable file in a directory on the PATH.
     *
     * Windows: basename() also reads `\` as a separator, so PHP_BINARY is a
     * path. PATHEXT gives the endings to try, so `docker` finds `docker.exe`.
     * A file with one of these endings is a program, as on Windows itself:
     * is_executable() there is true only for a binary, not for
     * `composer.bat`. Other systems do not set PATHEXT.
     *
     * An empty PATH entry is skipped. It does not name the filesystem root.
     */
    private function exists(string $program): bool
    {
        $endings = ['', ...explode(';', (string) getenv('PATHEXT'))];

        if (basename($program) !== $program) {
            return $this->runnable($program, $endings);
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            if ('' === $directory) {
                continue;
            }

            foreach ($endings as $ending) {
                if (true === $this->runnable($directory . '/' . $program . $ending, $endings)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the file is a program: it ends with an ending of PATHEXT, in
     * any case, or it is executable.
     *
     * @param list<string> $endings
     */
    private function runnable(string $file, array $endings): bool
    {
        if (false === is_file($file)) {
            return false;
        }

        foreach ($endings as $ending) {
            if ('' !== $ending && true === str_ends_with(strtolower($file), strtolower($ending))) {
                return true;
            }
        }

        return is_executable($file);
    }

    /**
     * Starts the program with crest's own stdin, stdout and stderr.
     *
     * Null as the environment of proc_open() gives the child the environment
     * of crest. A list replaces it, so the variables of crest come first.
     *
     * proc_open() returns false only when it cannot create the process. run()
     * checks the program and the directory first, so the guard is a last
     * defense that the suite cannot reach.
     *
     * @param non-empty-list<string> $command
     * @param array<string, string>  $environment
     *
     * @return resource
     */
    private function start(array $command, ?string $directory, array $environment)
    {
        $variables = [] === $environment ? null : [...getenv(), ...$environment];
        $process   = proc_open($command, [], $pipes, $directory, $variables);

        if (false === $process) {
            throw new Exception(sprintf('could not start %s', $command[0])); // @codeCoverageIgnore
        }

        return $process;
    }
}
