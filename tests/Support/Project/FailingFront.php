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
 * Fails to boot, the way a missing database or a bad env file would.
 */
final class FailingFront
{
    public function boot(): Container
    {
        throw new RuntimeException('no database');
    }
}
