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

use Crest\Console\Exceptions\Exception;

use function getenv;
use function is_string;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function str_starts_with;

/**
 * Where composer puts the packages and the binaries of a project, by the
 * rules of composer: the environment variable, then the config section of
 * composer.json, then the default. A relative folder is relative to the
 * project root.
 *
 * The environment counts only for the process that runs composer. For the
 * docker runtime, composer runs in the container, where the environment of
 * the host is not set.
 */
final class Vendor
{
    private const BIN = 'bin-dir';

    private const PLACEHOLDER = '{$vendor-dir}';

    private const VENDOR = 'vendor-dir';

    /**
     * The bin folder: COMPOSER_BIN_DIR, else config.bin-dir, else
     * <vendor-dir>/bin.
     */
    public static function binDir(string $root, bool $environment): string
    {
        [$value, $source] = self::value($root, self::BIN, 'COMPOSER_BIN_DIR', $environment);

        return self::checked(
            str_replace(
                self::PLACEHOLDER,
                self::vendorDir($root, $environment),
                $value ?? self::PLACEHOLDER . '/bin'
            ),
            self::BIN,
            $source
        );
    }

    public static function isAbsolute(string $folder): bool
    {
        return true === str_starts_with($folder, '/');
    }

    /**
     * A folder on the host: as it is when it is absolute, else in the root.
     */
    public static function path(string $root, string $folder): string
    {
        return true === self::isAbsolute($folder) ? $folder : $root . '/' . $folder;
    }

    /**
     * The vendor folder: COMPOSER_VENDOR_DIR, else config.vendor-dir, else
     * vendor.
     */
    public static function vendorDir(string $root, bool $environment): string
    {
        [$value, $source] = self::value($root, self::VENDOR, 'COMPOSER_VENDOR_DIR', $environment);

        return self::checked($value ?? 'vendor', self::VENDOR, $source);
    }

    /**
     * The value without a `/` at the end. Composer expands a `~`, a `$` and
     * a `{$` placeholder. crest does not, because the docker runtime cannot
     * do it in the container. Thus these are errors.
     */
    private static function checked(string $value, string $key, string $source): string
    {
        if (
            true === str_starts_with($value, '~')
            || true === str_starts_with($value, '$')
            || true === str_contains($value, '{$')
        ) {
            throw new Exception(
                sprintf("unsupported %s '%s' in %s; use a relative or an absolute path", $key, $value, $source)
            );
        }

        return rtrim($value, '/');
    }

    /**
     * The value of the variable, else of composer.json, and its source. A
     * null value when neither has one. An empty value counts as no value.
     *
     * @return array{?string, string}
     */
    private static function value(string $root, string $key, string $variable, bool $environment): array
    {
        if (true === $environment) {
            $value = getenv($variable);

            if (true === is_string($value) && '' !== $value) {
                return [$value, $variable];
            }
        }

        $value = Manifest::config($root, $key);

        return ['' === $value ? null : $value, 'composer.json'];
    }
}
