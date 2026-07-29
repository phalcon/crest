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
use Phalcon\Support\HelperFactory;

/**
 * Two services declared out of alphabetical order, one already resolved.
 */
final class ServicesFront
{
    public function boot(): Container
    {
        $container = new Container();

        $container->set('zebra', HelperFactory::class);
        $container->set('alpha', HelperFactory::class);
        $container->get('alpha');

        return $container;
    }
}
