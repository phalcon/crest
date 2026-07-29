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

namespace Crest\Tests\Support\Project;

use Phalcon\Container\Container;
use Phalcon\Events\Manager;
use Phalcon\Support\HelperFactory;

/**
 * Listeners attached out of alphabetical order, one an object and one a closure.
 */
final class EventsFront
{
    public function boot(): Container
    {
        $events = new Manager();

        $events->attach('zebra:fired', new HelperFactory());
        $events->attach('alpha', new HelperFactory());
        $events->attach('boot', static fn () => null);

        $container = new Container();
        $container->setInstance(Manager::class, $events, 'shared');

        return $container;
    }
}
