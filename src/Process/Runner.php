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

/**
 * Runs an external program.
 *
 * This is the seam for the commands that run programs (`up`, `down`,
 * `install`). Tests replace it with a fake and check the exact argv, so they
 * need no docker.
 */
interface Runner
{
    /**
     * Runs the program and returns its exit status. The output of the program
     * goes directly to the terminal, not through crest's Output.
     *
     * @param non-empty-list<string> $command   The program, then its
     *                                          arguments. No shell.
     * @param string|null            $directory Where the program runs; null
     *                                          for the working directory.
     */
    public function run(array $command, ?string $directory = null): int;
}
