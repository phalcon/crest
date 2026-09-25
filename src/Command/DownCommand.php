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

namespace Crest\Command;

use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;

/**
 * Stops the project containers and removes them.
 */
final class DownCommand extends ComposeCommand
{
    public function define(): Definition
    {
        return Definition::for('down', 'Stop and remove the project containers')
            ->option('volumes', 'Remove the named volumes too');
    }

    public function handle(Input $input, Output $output): int
    {
        $arguments = ['down'];

        if (true === $input->option('volumes')) {
            $arguments[] = '--volumes';
        }

        return $this->compose($input, $arguments);
    }
}
