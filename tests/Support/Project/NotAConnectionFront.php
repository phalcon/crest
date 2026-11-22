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
use stdClass;

/**
 * An ADR front whose `db` service is not a DataMapper connection (#84).
 */
final class NotAConnectionFront
{
    public function boot(): Container
    {
        $container = new Container();

        $container->set('db', stdClass::class);

        return $container;
    }
}
