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

use function array_filter;
use function basename;
use function count;
use function dirname;
use function explode;
use function file_get_contents;
use function implode;
use function in_array;
use function is_dir;
use function is_file;
use function preg_match;
use function scandir;
use function sprintf;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;
use function uksort;

/**
 * What `crest init` proposes for an existing project. It reads the project,
 * not crest.php: composer.json for the namespace and its folder, the folders
 * for the paths, the files for the front controller, and the compose file for
 * the runtime.
 */
final class Survey
{
    /**
     * The names that docker compose looks for, in its order.
     */
    private const COMPOSE_FILES = ['compose.yaml', 'compose.yml', 'docker-compose.yaml', 'docker-compose.yml'];

    /**
     * The front controller that `crest new` writes, when the file is in the
     * folder of the namespace.
     */
    public static function bootstrap(string $root, string $namespace, string $folder): ?string
    {
        if (false === is_file($root . '/' . $folder . '/' . Layout::FRONT . '.php')) {
            return null;
        }

        return $namespace . '\\' . Layout::FRONT;
    }

    /**
     * The flavor whose default folders the project has most of. A tie gives
     * the earlier flavor of the list. A folder that all flavors share adds
     * one to each flavor, so it changes no result.
     *
     * @param non-empty-list<Flavor> $flavors
     */
    public static function flavor(string $root, string $folder, array $flavors): Flavor
    {
        $found = [];

        foreach ($flavors as $flavor) {
            $found[] = count(
                array_filter(
                    self::paths($root, $folder, $flavor),
                    static fn (string $path): bool => is_dir($root . '/' . $path)
                )
            );
        }

        $best = 0;

        foreach ($found as $index => $count) {
            // Only more folders win, so a tie keeps the earlier flavor.
            if ($count > $found[$best]) {
                $best = $index;
            }
        }

        return $flavors[$best];
    }

    /**
     * The folder of a namespace, by the longest psr-4 prefix that covers it:
     * for `App\Api` under `App\ => src/`, `src/Api`. The folder need not
     * exist.
     */
    public static function folder(string $root, string $namespace): string
    {
        $map = Manifest::psr4($root);

        uksort($map, static fn (string $first, string $second): int => strlen($second) <=> strlen($first));

        foreach ($map as $prefix => $target) {
            $prefix = trim($prefix, '\\');
            $target = trim($target, '/');

            if ($namespace === $prefix) {
                return $target;
            }

            if (true === str_starts_with($namespace, $prefix . '\\')) {
                return $target . '/' . str_replace('\\', '/', substr($namespace, strlen($prefix) + 1));
            }
        }

        throw new Exception(sprintf("'%s' is not in the psr-4 map of composer.json", $namespace));
    }

    /**
     * The namespace of a folder, by the longest psr-4 folder of
     * composer.json that covers it: for `src/Action` under `App\ => src/`,
     * `App\Action`. Null when no psr-4 folder covers it. The namespace is not
     * checked.
     */
    public static function namespaceOf(string $root, string $folder): ?string
    {
        $best      = null;
        $namespace = null;

        foreach (Manifest::psr4($root) as $prefix => $target) {
            $target = trim($target, '/');

            if ($folder !== $target && false === str_starts_with($folder, $target . '/')) {
                continue;
            }

            if (null !== $best && strlen($target) <= strlen($best)) {
                continue;
            }

            $rest      = str_replace('/', '\\', substr($folder, strlen($target) + 1));
            $best      = $target;
            $namespace = trim(trim($prefix, '\\') . '\\' . $rest, '\\');
        }

        return $namespace;
    }

    /**
     * The default paths of a flavor, for a project. A class folder goes into
     * the folder of the namespace: for `app`, `action` is `app/Action`. A
     * folder with no classes (Config::NO_CLASS_PATHS) stays relative to the
     * root.
     *
     * @return array<string, string>
     */
    public static function paths(string $root, string $folder, Flavor $flavor): array
    {
        $paths = [];

        foreach (Config::defaultPaths($flavor) as $key => $path) {
            $paths[$key] = true === in_array($key, Config::NO_CLASS_PATHS, true)
                ? self::noClassFolder($root, $path)
                : $folder . '/' . basename($path);
        }

        return $paths;
    }

    /**
     * @param non-empty-list<Flavor> $flavors The flavors to compare, in order
     */
    public static function settings(string $root, array $flavors): Settings
    {
        [$namespace, $directory] = self::namespace($root);

        $flavor = self::flavor($root, $directory, $flavors);

        return new Settings(
            $flavor,
            $namespace,
            self::bootstrap($root, $namespace, $directory),
            self::paths($root, $directory, $flavor),
            self::runtime($root)
        );
    }

    /**
     * The first psr-4 entry whose folder exists: its namespace and its
     * folder.
     *
     * @return array{string, string}
     */
    private static function namespace(string $root): array
    {
        $missing = [];

        foreach (Manifest::psr4($root) as $prefix => $target) {
            $target = trim($target, '/');

            if (true === is_dir($root . '/' . $target)) {
                return [trim($prefix, '\\'), $target];
            }

            $missing[] = "'" . $target . "'";
        }

        if ([] === $missing) {
            throw new Exception(
                'no usable psr-4 autoload entry found in composer.json; add one, e.g. "App\\\\": "app/", '
                . "run 'composer dump-autoload', then run 'crest init'"
            );
        }

        throw new Exception(
            sprintf(
                'no usable psr-4 autoload entry found in composer.json; these psr-4 directories do not exist: %s',
                implode(', ', $missing)
            )
        );
    }

    /**
     * A folder with no classes: the only folder next to the default, or the
     * default. For `views`, vokuro gets `themes/vokuro`. An existing default
     * is one of the folders, so it stays the proposal. The default must have
     * a parent folder, as `themes/default` does: for a default of one part,
     * the parent is the project root.
     */
    private static function noClassFolder(string $root, string $path): string
    {
        $parent  = dirname($path);
        $folders = [];

        // Suppressed: a parent that does not exist, is a file, or that crest
        // cannot read gives no folders, so the proposal is the default, with
        // no PHP warning among the questions.
        foreach (@scandir($root . '/' . $parent) ?: [] as $name) {
            // A hidden folder (git, an editor, a cache) is never a theme. The
            // check also skips `.` and `..`.
            if (false === str_starts_with($name, '.') && true === is_dir($root . '/' . $parent . '/' . $name)) {
                $folders[] = $parent . '/' . $name;
            }
        }

        return 1 === count($folders) ? $folders[0] : $path;
    }

    /**
     * Docker when the root has a compose file. The service is `app` when the
     * file has it, else the first service.
     */
    private static function runtime(string $root): Runtime
    {
        foreach (self::COMPOSE_FILES as $name) {
            if (false === is_file($root . '/' . $name)) {
                continue;
            }

            $services = self::services($root . '/' . $name);

            if (true === in_array(Runtime::SERVICE, $services, true)) {
                return Runtime::docker();
            }

            return Runtime::docker($services[0] ?? Runtime::SERVICE);
        }

        return Runtime::host();
    }

    /**
     * The service names in the top-level `services:` block. A line scan, not
     * a YAML parser: the first key under the block sets the indent, and each
     * key with that indent is a service. A line that starts at the left edge
     * ends the block. A comment can follow `services:`.
     *
     * @return list<string>
     */
    private static function services(string $file): array
    {
        $services = [];
        $inside   = false;
        $indent   = null;

        foreach (explode("\n", (string) file_get_contents($file)) as $line) {
            if (1 === preg_match('/^services:/', $line)) {
                $inside = true;

                continue;
            }

            if (1 === preg_match('/^[^\s#]/', $line)) {
                $inside = false;

                continue;
            }

            if (true === $inside && 1 === preg_match('/^( +)([\w.-]+):/', $line, $match)) {
                $indent ??= $match[1];

                if ($indent === $match[1]) {
                    $services[] = $match[2];
                }
            }
        }

        return $services;
    }
}
