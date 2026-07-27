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

use function is_string;

final class FakeCommand extends Command
{
    public function define(): Definition
    {
        return Definition::for('fake', 'A command that exists only for tests')
            ->argument('subject', false, 'What to greet', 'world');
    }

    public function handle(Input $input, Output $output): int
    {
        // argument() returns mixed; PHPStan rejects concatenating, casting and
        // sprintf-ing it, so the string case is narrowed explicitly.
        $subject = $input->argument('subject');

        $output->line('hello ' . (true === is_string($subject) ? $subject : ''));

        return 0;
    }
}
