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

use function file_get_contents;
use function is_array;
use function is_file;
use function json_decode;

/**
 * Reads the composer.json of a project. crest.php does not copy these facts:
 * composer.json owns them, and a copy gets out of date (D07).
 */
final class Manifest
{
    private const FILENAME = 'composer.json';

    /**
     * The psr-4 map of the autoload section. Each prefix has one folder: for a
     * list, the first folder. An entry with an empty folder is skipped. Empty
     * when there is no composer.json or no psr-4 section.
     *
     * @return array<string, string>
     */
    public static function psr4(string $root): array
    {
        /** @var array{autoload?: array{psr-4?: array<string, string|list<string>>}} $decoded */
        $decoded = self::read($root);

        $map = [];
        foreach ($decoded['autoload']['psr-4'] ?? [] as $prefix => $target) {
            $target = is_array($target) ? ($target[0] ?? '') : $target;

            if ('' === $target) {
                continue;
            }

            $map[$prefix] = $target;
        }

        return $map;
    }

    /**
     * Whether require or require-dev names the package.
     */
    public static function requires(string $root, string $package): bool
    {
        /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $decoded */
        $decoded = self::read($root);

        return true === isset($decoded['require'][$package])
            || true === isset($decoded['require-dev'][$package]);
    }

    /**
     * The decoded file. Empty when the file is missing or is not a JSON
     * object.
     *
     * @return array<mixed>
     */
    private static function read(string $root): array
    {
        $file = $root . '/' . self::FILENAME;

        if (false === is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return true === is_array($decoded) ? $decoded : [];
    }
}
