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

use function basename;
use function explode;
use function file_get_contents;
use function implode;
use function in_array;
use function is_dir;
use function is_file;
use function preg_match;
use function sprintf;
use function trim;

/**
 * What `crest init` proposes for an existing project. It reads the project,
 * not crest.php: composer.json for the namespace and its folder, the files
 * for the front controller, and the compose file for the runtime.
 */
final class Survey
{
    /**
     * The names that docker compose looks for, in its order.
     */
    private const COMPOSE_FILES = ['compose.yaml', 'compose.yml', 'docker-compose.yaml', 'docker-compose.yml'];

    public static function settings(string $root): Settings
    {
        [$namespace, $directory] = self::namespace($root);

        return new Settings(
            $namespace,
            self::bootstrap($root, $namespace, $directory),
            self::paths($directory),
            self::runtime($root)
        );
    }

    /**
     * The front controller that `crest new` writes, when the file is there.
     */
    private static function bootstrap(string $root, string $namespace, string $directory): ?string
    {
        if (false === is_file($root . '/' . $directory . '/' . Settings::FRONT . '.php')) {
            return null;
        }

        return $namespace . '\\' . Settings::FRONT;
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
            throw new Exception('no usable psr-4 autoload entry found in composer.json');
        }

        throw new Exception(
            sprintf(
                'no usable psr-4 autoload entry found in composer.json; these psr-4 directories do not exist: %s',
                implode(', ', $missing)
            )
        );
    }

    /**
     * The ADR default paths, moved into the folder of the namespace. For
     * `App\ => app/`, `action` is `app/Action`.
     *
     * @return array<string, string>
     */
    private static function paths(string $directory): array
    {
        $paths = [];

        foreach (Config::defaultPaths(Flavor::ADR) as $key => $path) {
            $paths[$key] = $directory . '/' . basename($path);
        }

        return $paths;
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
