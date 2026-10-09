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

/**
 * Asks one question and prints the answer.
 */
final class AskingCommand extends Command
{
    public function define(): Definition
    {
        return Definition::for('ask', 'A command that asks one question');
    }

    public function handle(Input $input, Output $output): int
    {
        $output->line('answer: ' . $output->ask('Name', 'default'));

        return 0;
    }
}
