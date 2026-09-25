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

use Crest\Console\Command\Command;
use Crest\Console\Input;
use Crest\Console\Kernel;
use Crest\Console\Output;

/**
 * Runs one command without the kernel. The kernel creates commands with
 * `new $class()`, so a test cannot give them a fake collaborator. This trait
 * binds argv the same way as the kernel does, with the global options.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait RunsACommandDirectly
{
    use CapturesOutput;

    /**
     * @param list<string> $tokens
     */
    protected function handleDirectly(Command $command, array $tokens): int
    {
        $definition = $command->define();

        return $command->handle(
            new Input($definition->getName(), $definition->merge(Kernel::globals())->bind($tokens)),
            new Output($this->stdout, $this->stderr, false)
        );
    }
}
