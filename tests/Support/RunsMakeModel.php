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

use Crest\Command\Make\ModelCommand;

use const PHP_EOL;

/**
 * Runs make:model in a scratch project, for the tests of make:model in the
 * unit suite and in the database suites.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait RunsMakeModel
{
    use GeneratesInAScratchProject;

    /**
     * The command stops with the error on stderr, and writes no file.
     *
     * @param list<string> $arguments
     */
    protected function assertStops(array $arguments, string $error): void
    {
        $status = $this->runCommand($arguments);

        $this->assertSame(1, $status);
        $this->assertSame('crest: ' . $error . PHP_EOL, $this->readStderr());
        $this->assertDirectoryDoesNotExist($this->root . '/src/Models');
    }

    /**
     * Empty streams for the output of the next run.
     */
    protected function freshStreams(): void
    {
        $this->closeStreams();
        $this->captureStreams();
    }

    /**
     * An MVC crest.php that names the front controller, and the models key
     * when the test gives one (#53).
     *
     * @param class-string $class
     */
    protected function front(string $class, string $models = ''): void
    {
        $this->writeCrestPhp(
            "['flavor' => 'mvc', 'bootstrap' => \\" . $class . '::class'
            . ('' === $models ? '' : ", 'models' => " . $models)
            . ']'
        );
    }

    /**
     * @param list<string> $arguments
     */
    protected function runCommand(array $arguments): int
    {
        return $this->runProjectCommand('make:model', ModelCommand::class, $arguments);
    }
}
