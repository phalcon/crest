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
 * Registers something else under the events manager's name. The service is
 * genuinely declared, so the registration check passes and only the type check
 * catches it.
 */
final class WrongManagerFront
{
    public function boot(): Container
    {
        $container = new Container();

        $container->set(Manager::class, HelperFactory::class);

        return $container;
    }
}
