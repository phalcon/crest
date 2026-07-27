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

namespace Crest\Console;

use Composer\InstalledVersions;

use function class_exists;

/**
 * An installed package's version, or UNKNOWN when composer's runtime API is
 * unavailable or the package is absent. Callers supply the package name; this
 * class carries no identity of its own.
 */
final class PackageVersion
{
    public const UNKNOWN = 'dev';

    public static function isInstalled(string $package): bool
    {
        return true === class_exists(InstalledVersions::class)
            && true === InstalledVersions::isInstalled($package);
    }

    public static function of(string $package): string
    {
        if (false === self::isInstalled($package)) {
            return self::UNKNOWN;
        }

        return InstalledVersions::getPrettyVersion($package) ?? self::UNKNOWN;
    }
}
