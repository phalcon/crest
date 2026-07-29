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

/**
 * An events manager is registered but nothing is attached to it.
 */
final class ManagerOnlyFront
{
    public function boot(): Container
    {
        $container = new Container();

        $container->set(Manager::class, Manager::class);

        return $container;
    }
}
