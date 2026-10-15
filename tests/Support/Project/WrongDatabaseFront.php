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

use Phalcon\Di\DiInterface;
use Phalcon\Di\FactoryDefault;
use stdClass;

/**
 * An MVC front whose `db` service is not a database adapter.
 */
final class WrongDatabaseFront
{
    public function boot(): DiInterface
    {
        $container = new FactoryDefault();

        $container->setShared('db', new stdClass());

        return $container;
    }
}
