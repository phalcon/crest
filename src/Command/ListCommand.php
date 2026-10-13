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
use Crest\Project\Config;

/**
 * Every command the tool can run, one row each.
 *
 * The kernel runs this command when the user names none
 * (Registry::withDefault()), so `crest` and `crest list` are the same
 * listing. The name makes the listing addressable - and therefore aliasable
 * and documentable - which is what devtools offered through `commands`,
 * `list` and `enumerate`.
 */
final class ListCommand extends Command
{
    public function define(): Definition
    {
        return Definition::for('list', 'List the available commands');
    }

    public function handle(Input $input, Output $output): int
    {
        $output->commandTable(
            Commands::NAME . ' ' . PackageVersion::of(Commands::PACKAGE),
            Commands::registry()->descriptions($this->keep($input))
        );

        return 0;
    }

    /**
     * The test of the listing. Outside a project there is none: all commands
     * show. In a project, a project command shows only when it supports the
     * flavor of the project. This is the rule that stops it in
     * ProjectCommand::config().
     *
     * @return (callable(Command): bool)|null
     */
    private function keep(Input $input): ?callable
    {
        $config = Config::find($input->optionStringOrNull('directory'), $input->optionStringOrNull('config'));

        if (null === $config) {
            return null;
        }

        $flavor = $config->flavor();

        return static fn (Command $command): bool => false === $command instanceof ProjectCommand
            || true === $command->supports($flavor);
    }
}
