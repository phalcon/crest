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

namespace Crest\Command\Container;

use Crest\Command\ProjectCommand;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Project\Bootstrap;
use Phalcon\Container\Container;

use function get_class;
use function sort;

/**
 * The services registered in the project's container.
 *
 * Names come from the container; everything else is looked up per name, which
 * is all the container exposes and all this needs.
 */
final class ListCommand extends ProjectCommand
{
    public function define(): Definition
    {
        return Definition::for('container:list', 'List the services registered in the container');
    }

    public function handle(Input $input, Output $output): int
    {
        $container = $this->container(Bootstrap::container($this->config($input)));

        $names = $container->getServiceNames();
        sort($names);

        if ([] === $names) {
            $output->line('no services registered');

            return 0;
        }

        $rows = [];
        foreach ($names as $name) {
            $rows[] = [
                $name,
                $this->concrete($container, $name),
                true === $container->hasInstance($name) ? 'yes' : 'no',
            ];
        }

        $output->table(['SERVICE', 'CLASS', 'RESOLVED'], $rows);

        return 0;
    }

    /**
     * What the service builds, when the definition names a class.
     */
    private function concrete(Container $container, string $name): string
    {
        $definition = $container->getDefinition($name);

        // A service built by a factory has no class to report, which is
        // information rather than a gap.
        return true === $definition->hasClass() ? $definition->getClass() : 'factory';
    }

    /**
     * @throws Exception when the bootstrap returned something that is not a container
     */
    private function container(object $container): Container
    {
        if (false === $container instanceof Container) {
            throw new Exception(get_class($container) . ' is not a Phalcon container');
        }

        return $container;
    }
}
