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
 * A front that can only serve, not be started - what a project predating
 * boot() looks like.
 */
final class NoBootFront
{
    public function run(): int
    {
        return 0;
    }
}
