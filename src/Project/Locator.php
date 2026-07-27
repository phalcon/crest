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

namespace Crest\Project;

use function dirname;
use function is_file;

/**
 * Finds the nearest crest.php by walking up from a starting directory, so
 * crest works from anywhere inside a project.
 */
final class Locator
{
    public const FILENAME = 'crest.php';

    public static function locate(string $from): ?string
    {
        $current = $from;

        while (true) {
            $candidate = $current . '/' . self::FILENAME;

            if (true === is_file($candidate)) {
                return $candidate;
            }

            $parent = dirname($current);

            if ($parent === $current) {
                return null;
            }

            $current = $parent;
        }
    }
}
