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

/**
 * An ADR front controller that inherits registerProviders(), as `crest new`
 * with no database writes it.
 */
final class InheritedProvidersFront extends AbstractHttpFront
{
}
