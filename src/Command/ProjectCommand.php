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
 * Base for the commands of crest that read the project they run against.
 *
 * `--directory` and `--config` are global options the kernel merges into each
 * definition; Project::config() resolves them. It lives here rather than on
 * Crest\Console\Command\Command because that class may not reference
 * Crest\Project - Crest\Console stays independent of the rest of the tool,
 * which IsolationTest enforces.
 *
 * Not for packages: a package that adds commands extends
 * Crest\Console\Command\Command and calls Project::context().
 *
 * @internal
 */
abstract class ProjectCommand extends Command
{
    protected function config(Input $input): Config
    {
        return Project::config($input);
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
     * The stubs of the project: its overrides first, then the packaged
     * copies.
     */
    protected function stub(Config $config): Stub
    {
        return new Stub(Paths::stubs(), $config->root());
    }

    /**
     * The writer a generator renders through.
     *
     * Assembly was repeated verbatim in every make:* command, which meant five
     * copies of the stub resolution order - packaged root, then project root -
     * and five places to change when it moves.
     *
     * stub:publish deliberately does not come through here: it copies rather
     * than renders, so it has no stub to construct a writer around and uses the
     * static ArtifactWriter::write() instead.
     */
    protected function writer(Config $config): ArtifactWriter
    {
        return new ArtifactWriter($this->stub($config), $config->flavor()->value);
    }
}
