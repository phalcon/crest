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
use Crest\Generator\ArtifactWriter;
use Crest\Generator\ClassName;
use Crest\Generator\Placement;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Project\Config;

/**
 * Base for every command that reads the project it is run against.
 *
 * `--directory` and `--config` are global options the kernel merges into each
 * definition, so resolving them was repeated identically in ten commands. It
 * lives here rather than on Crest\Console\Command\Command because that class may
 * not reference Crest\Project - Crest\Console stays independent of the rest of
 * the tool, which IsolationTest enforces. And it is not a Config::fromInput()
 * factory, because that would point Crest\Project at Crest\Console\Input and
 * couple project configuration to the console for the sake of two arguments.
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
     * Where a user-named artifact goes.
     *
     * Takes the name rather than the Input it came from: reading the `name`
     * argument here would be an unwritten contract with every subclass, and a
     * generator that called its argument something else would get a confusing
     * complaint about the empty string.
     *
     * The name is validated before any configuration is read, so a typo is
     * reported as a typo rather than as whatever the psr-4 map happens to say
     * about the directory it would have landed in.
     */
    protected function placement(Config $config, string $name, string $key, string $suffix): Placement
    {
        $class = ClassName::suffixed($name, $suffix);

        return new Placement(
            $class,
            $config->path($key) . '/' . $class . '.php',
            $config->namespaceFor($key)
        );
    }

    /**
     * The writer a generator renders through.
     *
     * Assembly was repeated verbatim in every make:* command, which meant five
     * copies of the stub resolution order - packaged root, then project root -
     * and five places to change when it moves. Contributed commands get it by
     * extending this class rather than by knowing how a writer goes together.
     *
     * stub:publish deliberately does not come through here: it copies rather
     * than renders, so it has no stub to construct a writer around and uses the
     * static ArtifactWriter::write() instead.
     */
    protected function writer(Config $config): ArtifactWriter
    {
        return new ArtifactWriter(
            new Stub(Paths::stubs(), $config->root()),
            $config->flavor()->value
        );
    }
}
