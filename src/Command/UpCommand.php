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
 * Starts the project containers in the background.
 */
final class UpCommand extends ComposeCommand
{
    public function define(): Definition
    {
        return Definition::for('up', 'Start the project containers')
            ->option('build', 'Rebuild the images first');
    }

    public function handle(Input $input, Output $output): int
    {
        $arguments = ['up', '-d'];

        if (true === $input->option('build')) {
            $arguments[] = '--build';
        }

        return $this->compose($input, $arguments);
    }
}
