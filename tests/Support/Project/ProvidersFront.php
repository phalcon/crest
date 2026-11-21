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

use Phalcon\ADR\Front\AbstractHttpFront;
use Phalcon\Container\Container;

/**
 * An ADR front controller with its own registerProviders(), as
 * `crest new --database` writes it.
 */
final class ProvidersFront extends AbstractHttpFront
{
    protected function registerProviders(Container $container): void
    {
        parent::registerProviders($container);
    }
}
