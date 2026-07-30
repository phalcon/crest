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

use Crest\Console\Command\Command;
use Crest\Console\Input;
use Crest\Generator\ClassName;
use Crest\Generator\Placement;
use Crest\Project\Config;

/**
 * Base for every command that reads the project it is run against.
 *
 * `--directory` and `--config` are global options the kernel merges into each
 * definition, so resolving them was repeated identically in ten commands. It
 * lives here rather than on Crest\Console\Command\Command because that class may
 * not reference Crest\Project - it has to stay tool-agnostic for the eventual
 * extraction to phalcon/console. And it is not a Config::fromInput() factory,
 * because that would point Crest\Project at Crest\Console\Input and couple
 * project configuration to the console for the sake of two arguments.
 */
abstract class ProjectCommand extends Command
{
    protected function config(Input $input): Config
    {
        return Config::discover(
            $input->optionStringOrNull('directory'),
            $input->optionStringOrNull('config')
        );
    }

    /**
     * Where a user-named artifact goes, for the `name` argument every generator
     * declares.
     *
     * The name is validated before any configuration is read, so a typo is
     * reported as a typo rather than as whatever the psr-4 map happens to say
     * about the directory it would have landed in.
     */
    protected function placement(Config $config, Input $input, string $key, string $suffix): Placement
    {
        $class = ClassName::suffixed($input->argumentString('name'), $suffix);

        return new Placement(
            $class,
            $config->path($key) . '/' . $class . '.php',
            $config->namespaceFor($key)
        );
    }
}
