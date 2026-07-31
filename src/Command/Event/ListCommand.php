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

namespace Crest\Command\Event;

use Crest\Command\ProjectCommand;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Project\Bootstrap;
use Phalcon\Contracts\Container\Service\Collection;
use Phalcon\Contracts\Events\Enumerable;
use Phalcon\Events\Manager;

use function get_class;
use function is_object;
use function ksort;

/**
 * The listeners attached to the project's events manager.
 *
 * Event types are mixed granularity by design: a listener may be attached to a
 * whole component (`dispatch`) or to one event (`dispatch:beforeDispatch`), and
 * both are real. Normalising them would hide the difference between listening
 * to everything a component fires and listening to one moment.
 */
final class ListCommand extends ProjectCommand
{
    public function define(): Definition
    {
        return Definition::for('event:list', 'List the listeners attached to the events manager');
    }

    public function handle(Input $input, Output $output): int
    {
        $manager   = $this->manager(Bootstrap::container($this->config($input)));
        $listeners = $manager->getListenerMap();

        if ([] === $listeners) {
            $output->line('no listeners attached');

            return 0;
        }

        // One call answers both halves of the listing, so the types arrive in
        // attach order rather than sorted - which is not the order anyone wants
        // to read listeners in.
        ksort($listeners);

        $rows = [];
        foreach ($listeners as $type => $attached) {
            foreach ($attached as $listener) {
                $rows[] = [$type, $this->describe($listener)];
            }
        }

        $output->table(['EVENT', 'LISTENER'], $rows);

        return 0;
    }

    /**
     * A listener may be an object, a closure or a callable array; the useful
     * answer is which of those, and what it is.
     */
    private function describe(mixed $listener): string
    {
        if (true === is_object($listener)) {
            return get_class($listener);
        }

        return 'callable';
    }

    /**
     * Whether the project registered this service, as opposed to the container
     * being willing to autowire it on demand.
     */
    private function isRegistered(Collection $container, string $name): bool
    {
        return true === $container->hasDefinition($name)
            || true === $container->hasInstance($name);
    }

    /**
     * The events manager the container holds.
     *
     * Asked for by name rather than pulled from a known key, because a project
     * may register it under either and the container answers both the same way.
     */
    private function manager(object $container): Enumerable
    {
        if (false === $container instanceof Collection) {
            throw new Exception(
                get_class($container) . ' is not a Phalcon container'
            );
        }

        // has() is not the question. The container autowires, so it answers
        // true for any instantiable class and get() would hand back a brand new
        // Manager with nothing attached - reported as "no listeners", which
        // reads as "your application has none" rather than "you never
        // registered one". Only a definition or an existing instance means the
        // project actually has an events manager.
        if (false === $this->isRegistered($container, Manager::class)) {
            throw new Exception(
                'the bootstrap registers no ' . Manager::class
                . '; without one there are no listeners to list'
            );
        }

        $manager = $container->get(Manager::class);

        // Enumerable rather than Manager: reporting listeners is the only thing
        // this command asks of it, and that capability is what the contract
        // publishes.
        if (false === $manager instanceof Enumerable) {
            throw new Exception(Manager::class . ' resolved to something else');
        }

        return $manager;
    }
}
