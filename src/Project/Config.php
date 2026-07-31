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

use function array_keys;
use function dirname;
use function explode;
use function file_get_contents;
use function getcwd;
use function implode;
use function in_array;
use function is_array;
use function is_dir;
use function is_file;
use function is_string;
use function json_decode;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Everything a generator needs to know about the project it is writing into,
 * including the one mapping nothing else may re-derive: which namespace a
 * configured directory belongs to.
 *
 * crest.php is optional: with none present the namespace and paths are
 * inferred from composer.json's psr-4 map, so generators work immediately
 * after composer create-project with no setup.
 */
final class Config
{
    /**
     * @param array<string, string> $paths
     * @param array<string, string> $namespaces
     * @param array<string, string> $psr4
     * @param list<string>          $declared Top-level keys the config file
     *                                        actually stated, so a reader can
     *                                        tell those from the defaults.
     */
    private function __construct(
        private readonly Flavor $flavor,
        private readonly string $namespace,
        private readonly string $root,
        private readonly array $paths,
        private readonly array $namespaces,
        private readonly array $psr4,
        private readonly ?string $source = null,
        private readonly array $declared = [],
        private readonly ?string $bootstrap = null,
    ) {
    }

    /**
     * @param string|null $directory  Project root; defaults to the cwd.
     * @param string|null $configFile Explicit crest.php, bypassing the walk-up.
     */
    public static function discover(?string $directory = null, ?string $configFile = null): self
    {
        $directory = rtrim($directory ?? (string) getcwd(), '/');

        $file = $configFile ?? Locator::locate($directory);

        if (null !== $file && true === is_file($file)) {
            /** @var array<string, mixed> $declared */
            $declared = require $file;

            return self::fromArray($declared, dirname($file), $file);
        }

        return self::infer($directory);
    }

    /**
     * Where each generated artifact lands when crest.php does not say.
     *
     * Keyed by flavor rather than shared, because the artifacts themselves are
     * flavor-specific: a provider registers against `Phalcon\Container` under
     * ADR and against DI under MVC, so one flat set would offer every project
     * directories for artifacts it can never generate.
     *
     * Only ADR is populated. The others get their keys when their generators
     * land - defaults no command reads would show up in `config:show` as
     * locations that mean nothing.
     *
     * @return array<string, string>
     */
    private static function defaultPaths(Flavor $flavor): array
    {
        return match ($flavor) {
            Flavor::ADR => [
                'action' => 'src/Action',
                // Not an ADR artifact: a crest command is the same class in any
                // flavor. It sits here because ADR is the only populated set,
                // and moves to a shared one when cli, mvc and micro arrive.
                'command'    => 'src/Command',
                'middleware' => 'src/Middleware',
                'provider'   => 'src/Provider',
                'responder'  => 'src/Responder',
            ],
            Flavor::CLI, Flavor::MVC => [],
        };
    }

    /**
     * @param array<string, mixed> $declared
     */
    private static function fromArray(array $declared, string $root, string $source): self
    {
        $stated = [];

        // `namespaces` is deliberately absent: nothing reads its origin yet, and
        // tracking a key no caller asks about is a claim with no way to be wrong.
        foreach (['flavor', 'namespace', 'paths'] as $key) {
            if (true === isset($declared[$key])) {
                $stated[] = $key;
            }
        }

        $flavor = Flavor::ADR;
        if (true === isset($declared['flavor']) && true === is_string($declared['flavor'])) {
            $flavor = Flavor::tryFrom($declared['flavor'])
                ?? throw new Exception(sprintf("unknown flavor '%s'", $declared['flavor']));
        }

        $namespace = 'App';
        if (true === isset($declared['namespace']) && true === is_string($declared['namespace'])) {
            $namespace = trim($declared['namespace'], '\\');
        }

        $paths = self::defaultPaths($flavor);
        if (true === isset($declared['paths']) && true === is_array($declared['paths'])) {
            /** @var array<string, string> $supplied */
            $supplied = $declared['paths'];
            $paths    = [...$paths, ...$supplied];

            // Per key, not just the block: declaring `views` leaves `action` on
            // its default, and calling that one declared would be a lie.
            foreach (array_keys($supplied) as $name) {
                $stated[] = 'paths.' . $name;
            }
        }

        $namespaces = [];
        if (true === isset($declared['namespaces']) && true === is_array($declared['namespaces'])) {
            /** @var array<string, string> $namespaces */
            $namespaces = $declared['namespaces'];
        }

        // crest.php never restates the autoload map; namespaceFor() still needs
        // it whenever `namespaces` does not answer the question outright.
        $bootstrap = null;
        if (true === isset($declared['bootstrap']) && true === is_string($declared['bootstrap'])) {
            $bootstrap = $declared['bootstrap'];
        }

        return new self(
            $flavor,
            $namespace,
            $root,
            $paths,
            $namespaces,
            self::psr4Map($root),
            $source,
            $stated,
            $bootstrap
        );
    }

    /**
     * No crest.php: take the first psr-4 entry whose directory actually
     * exists and build defaults around it. The whole map is retained, not just
     * the winning prefix, because namespaceFor() needs it.
     */
    private static function infer(string $directory): self
    {
        if (false === is_file($directory . '/composer.json')) {
            throw new Exception('no crest.php and no composer.json found');
        }

        $psr4 = self::psr4Map($directory);

        foreach ($psr4 as $prefix => $target) {
            if (false === is_dir($directory . '/' . trim($target, '/'))) {
                continue;
            }

            return new self(
                Flavor::ADR,
                trim($prefix, '\\'),
                $directory,
                self::defaultPaths(Flavor::ADR),
                [],
                $psr4
            );
        }

        throw new Exception('no crest.php and no usable psr-4 autoload entry found');
    }

    /**
     * composer.json's psr-4 map, flattened so each prefix has exactly one
     * directory. Returns an empty map when composer.json is missing or has no
     * psr-4 section.
     *
     * @return array<string, string>
     */
    private static function psr4Map(string $root): array
    {
        $composer = $root . '/composer.json';

        if (false === is_file($composer)) {
            return [];
        }

        /** @var array{autoload?: array{psr-4?: array<string, string|list<string>>}} $decoded */
        $decoded = json_decode((string) file_get_contents($composer), true) ?: [];

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
     * How the project boots, as declared - either a front controller class or
     * a path to a file returning a container. Null when nothing was declared.
     *
     * Returned verbatim rather than resolved, because the two forms resolve
     * differently and only the caller knows which it is looking at.
     *
     * Services and listeners cannot be read off the filesystem the way routes
     * can: they exist only once the application has registered them.
     */
    public function bootstrap(): ?string
    {
        return $this->bootstrap;
    }

    public function flavor(): Flavor
    {
        return $this->flavor;
    }

    /**
     * Whether the config file stated this top-level key, as opposed to it
     * taking a default. `flavor`, `namespace`, `paths`, `namespaces`.
     */
    public function isDeclared(string $key): bool
    {
        return in_array($key, $this->declared, true);
    }

    public function namespace(): string
    {
        return $this->namespace;
    }

    /**
     * The namespace a named location maps to.
     *
     * An explicit `namespaces` entry in crest.php wins. Otherwise the answer
     * comes from composer.json's psr-4 map - the authoritative statement of
     * which prefix covers which directory - by finding the longest declared
     * directory that prefixes this path and appending the remainder.
     *
     * PSR-4 maps directory segments to namespace segments verbatim, so the
     * remainder is used as-is with no case transformation.
     *
     * Throws when no psr-4 entry covers the path: that configuration cannot
     * autoload whatever is written there, and a clear error is worth more than
     * a plausible guess.
     */
    public function namespaceFor(string $key): string
    {
        if (true === isset($this->namespaces[$key])) {
            return trim($this->namespaces[$key], '\\');
        }

        // substr, not str_replace: a global replace would also strip a repeat
        // of the root further down the path.
        $relative = trim(substr($this->path($key), strlen($this->root)), '/');
        $best     = null;
        $prefix   = '';

        foreach ($this->psr4 as $candidate => $directory) {
            $directory = trim($directory, '/');

            if (
                $relative !== $directory
                && false === str_starts_with($relative, $directory . '/')
            ) {
                continue;
            }

            if (null !== $best && strlen($directory) <= strlen($best)) {
                continue;
            }

            $best   = $directory;
            $prefix = trim($candidate, '\\');
        }

        if (null === $best) {
            throw new Exception(
                sprintf("no psr-4 autoload entry covers '%s'", $relative)
            );
        }

        $remainder = trim(substr($relative, strlen($best)), '/');

        if ('' === $remainder) {
            return $prefix;
        }

        return $prefix . '\\' . implode('\\', explode('/', $remainder));
    }

    /**
     * Absolute path for a named location.
     */
    public function path(string $key): string
    {
        if (false === isset($this->paths[$key])) {
            throw new Exception(sprintf("unknown path '%s'", $key));
        }

        return $this->root . '/' . trim($this->paths[$key], '/');
    }

    /**
     * Every named location, resolved to an absolute path.
     *
     * @return array<string, string>
     */
    public function paths(): array
    {
        $resolved = [];

        foreach (array_keys($this->paths) as $key) {
            $resolved[$key] = $this->path($key);
        }

        return $resolved;
    }

    public function root(): string
    {
        return $this->root;
    }

    /**
     * The config file this was read from, or null when everything was inferred
     * from composer.json.
     */
    public function source(): ?string
    {
        return $this->source;
    }
}
