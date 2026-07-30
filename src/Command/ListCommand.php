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

use Crest\Commands;
use Crest\Console\Command\Command;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\PackageVersion;
use Crest\Console\Parsing\Definition;

use function ksort;

/**
 * Every command the tool can run, one row each.
 *
 * The kernel prints the same listing when it is invoked with no arguments, but
 * that path cannot be reached by name: it belongs to Crest\Console, which may
 * not know that a command called "list" exists. This command is how the
 * listing becomes addressable - and therefore aliasable and documentable -
 * which is what devtools offered through `commands`, `list` and `enumerate`.
 */
final class ListCommand extends Command
{
    public function define(): Definition
    {
        return Definition::for('list', 'List the available commands');
    }

    public function handle(Input $input, Output $output): int
    {
        $commands = Commands::registry()->all();
        ksort($commands);

        $rows = [];
        foreach ($commands as $name => $class) {
            $rows[] = [$name, (new $class())->define()->getDescription()];
        }

        $output->banner(Commands::NAME . ' ' . PackageVersion::of(Commands::PACKAGE));
        $output->line();
        $output->table(['COMMAND', 'DESCRIPTION'], $rows);

        return 0;
    }
}
