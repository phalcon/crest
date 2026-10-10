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

use Crest\Command\Project;
use Crest\Console\Command\Command;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;

/**
 * A command as a package writes it: only the plugin API.
 */
final class ContextCommand extends Command
{
    public function define(): Definition
    {
        return Definition::for('context', 'Prints what a package sees of the project');
    }

    public function handle(Input $input, Output $output): int
    {
        $project = Project::context($input);

        $output->line($project->root());
        $output->line($project->path('action'));
        $output->line($project->namespaceFor('action'));

        return 0;
    }
}
