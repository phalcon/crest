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

/**
 * An MVC front with no `db` service: FactoryDefault does not register one.
 */
final class NoDatabaseFront
{
    public function boot(): DiInterface
    {
        return new FactoryDefault();
    }
}
