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

/**
 * Runs one command through the real kernel, with the captured streams.
 *
 * The kernel creates the command with `new $class()`. A test that must give
 * the command a fake collaborator uses RunsACommandDirectly instead.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait RunsThroughTheKernel
{
    use CapturesOutput;

    /**
     * @param class-string<Command> $class
     * @param list<string>          $tokens The arguments and options, as a user types them
     */
    protected function runThroughKernel(string $name, string $class, array $tokens): int
    {
        $kernel = new Kernel(
            Commands::NAME,
            (new Registry())->add($name, $class),
            Commands::PACKAGE,
            $this->stdout,
            $this->stderr,
            false
        );

        return $kernel->handle(['crest', $name, ...$tokens]);
    }
}
