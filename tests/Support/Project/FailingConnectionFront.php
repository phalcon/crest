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
use RuntimeException;

/**
 * An ADR front whose `db` service throws when the container makes it (#84).
 */
final class FailingConnectionFront
{
    public function boot(): Container
    {
        $container = new Container();

        $container->set(
            'db',
            static function (): never {
                throw new RuntimeException('could not connect');
            }
        );

        return $container;
    }
}
