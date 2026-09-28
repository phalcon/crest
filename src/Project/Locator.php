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
 * Finds a file by walking up from a starting directory, so crest works from
 * anywhere inside a project.
 */
final class Locator
{
    public const FILENAME = 'crest.php';

    private const MANIFEST = 'composer.json';

    /**
     * The nearest crest.php.
     */
    public static function locate(string $from): ?string
    {
        return self::nearest($from, self::FILENAME);
    }

    /**
     * The project root: the nearest directory with a composer.json. The
     * vendor/ directory of the project is next to that file.
     */
    public static function project(string $from): ?string
    {
        $manifest = self::nearest($from, self::MANIFEST);

        return null === $manifest ? null : dirname($manifest);
    }

    private static function nearest(string $from, string $name): ?string
    {
        $current = $from;

        while (true) {
            $candidate = $current . '/' . $name;

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
