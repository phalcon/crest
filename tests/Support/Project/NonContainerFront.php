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

/**
 * Returns something that is not an object at all. Untyped on purpose: a front
 * whose boot() declares a return type cannot do this, so the check exists for
 * the one that does not.
 */
final class NonContainerFront
{
    public function boot(): mixed
    {
        return 42;
    }
}
