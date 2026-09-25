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

use function explode;
use function getenv;
use function is_dir;
use function is_executable;
use function is_file;
use function proc_close;
use function proc_open;
use function sprintf;
use function str_contains;

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
    public function run(array $command, ?string $directory = null): int
    {
        if (false === $this->exists($command[0])) {
            throw new Exception(
                sprintf("'%s' was not found; install it or add it to the PATH", $command[0])
            );
        }

        if (null !== $directory && false === is_dir($directory)) {
            throw new Exception(sprintf('%s is not a directory', $directory));
        }

        return proc_close($this->start($command, $directory));
    }

    /**
     * Whether the program can run. A path must name an executable file. A
     * bare name must be an executable file in a directory on the PATH.
     */
    private function exists(string $program): bool
    {
        if (true === str_contains($program, '/')) {
            return true === is_file($program) && true === is_executable($program);
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            $candidate = $directory . '/' . $program;

            if (true === is_file($candidate) && true === is_executable($candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Starts the program with crest's own stdin, stdout and stderr.
     *
     * proc_open() returns false only when it cannot create the process. run()
     * checks the program and the directory first, so the guard is a last
     * defense that the suite cannot reach.
     *
     * @param non-empty-list<string> $command
     *
     * @return resource
     */
    private function start(array $command, ?string $directory)
    {
        $process = proc_open($command, [], $pipes, $directory);

        if (false === $process) {
            throw new Exception(sprintf('could not start %s', $command[0]));
        }

        return $process;
    }
}
