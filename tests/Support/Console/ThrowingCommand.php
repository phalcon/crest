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

namespace Crest\Tests\Support\Console;

use Crest\Console\Command\Command;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use RuntimeException;

/**
 * Throws a type the kernel does not specifically catch, so the Throwable arm
 * of handle() is reachable from a test.
 */
final class ThrowingCommand extends Command
{
    public function define(): Definition
    {
        return Definition::for('boom', 'A command that always fails');
    }

    public function handle(Input $input, Output $output): int
    {
        throw new RuntimeException('the wheels came off');
    }
}
