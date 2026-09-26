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
 * Boots a container that can find services but cannot list them. No Phalcon
 * container is like this, so the test sets the object: Bootstrap creates the
 * front, and a test cannot give it the object in another way.
 */
final class NonEnumerableFront
{
    public static ?object $container = null;

    public function boot(): ?object
    {
        return self::$container;
    }
}
