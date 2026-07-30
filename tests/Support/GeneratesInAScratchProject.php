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

namespace Crest\Tests\Support;

use Crest\Commands;
use Crest\Console\Command\Command;
use Crest\Console\Kernel;
use Crest\Console\Registry;

use function chdir;
use function getcwd;

/**
 * A throwaway project a generator can be pointed at, plus the kernel to run one
 * command against it.
 *
 * Every generator test needed the same four things - scratch directory, a psr-4
 * composer.json, captured streams, and the working directory moved inside the
 * scratch project - and each was carrying its own copy.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait GeneratesInAScratchProject
{
    use CapturesOutput;
    use ScratchDirectory;

    private string $previousCwd = '';

    protected function endScratchProject(): void
    {
        chdir($this->previousCwd);

        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    /**
     * @param class-string<Command> $class
     * @param list<string>          $arguments
     */
    protected function runProjectCommand(string $name, string $class, array $arguments): int
    {
        $registry = (new Registry())->add($name, $class);
        $kernel   = new Kernel(
            Commands::NAME,
            $registry,
            Commands::PACKAGE,
            $this->stdout,
            $this->stderr,
            false
        );

        return $kernel->handle(['crest', $name, ...$arguments, '--directory', $this->root]);
    }

    protected function startScratchProject(string $prefix, string ...$subdirectories): void
    {
        $this->makeScratchDirectory($prefix, ...$subdirectories);
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->captureStreams();

        // Config::discover() falls back to the working directory when
        // --directory does not reach it, and that fallback is reachable under
        // mutation testing. Running from the scratch directory keeps even a
        // mutant's writes contained instead of landing in the real src/ tree.
        $this->previousCwd = (string) getcwd();
        chdir($this->root);
    }
}
