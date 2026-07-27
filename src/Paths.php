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

namespace Crest;

use function dirname;

/**
 * Package-relative paths. The only file in the tree that knows how deep
 * anything sits, and it measures from itself: src/Paths.php -> src -> root.
 *
 * Commands and tests ask here instead of counting dirname() levels, so moving
 * a command never silently repoints it at the wrong directory.
 */
final class Paths
{
    public static function root(): string
    {
        return dirname(__DIR__);
    }

    public static function stubs(): string
    {
        return self::root() . '/resources/stubs';
    }
}
