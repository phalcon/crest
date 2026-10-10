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
use function implode;
use function in_array;
use function is_array;
use function is_file;
use function is_string;
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
 * crest.php marks a crest project, and it is required (D07). The project root
 * is the folder of the crest.php that the root rule finds (see file()).
 */
final class Config implements ProjectContext
{
    /**
     * The error when the root rule finds no crest.php.
     */
    public const MISSING = "no crest.php found; run 'crest init'";

    /**
     * @param array<string, string> $paths
     * @param array<string, string> $namespaces
     * @param array<string, string> $psr4
     * @param list<string>          $declared   Top-level keys the config file
     *                                          actually stated, so a reader can
     *                                          tell those from the defaults.
     */
    private function __construct(
        private readonly Flavor $flavor,
        private readonly string $namespace,
        private readonly string $root,
        private readonly array $paths,
        private readonly array $namespaces,
        private readonly array $psr4,
        private readonly string $source,
        private readonly Runtime $runtime,
        private readonly array $declared = [],
        private readonly ?string $bootstrap = null,
    ) {
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
    public static function defaultPaths(Flavor $flavor): array
    {
        return match ($flavor) {
            Flavor::ADR => [
                'action'     => Layout::SOURCE . '/Action',
                // Not an ADR artifact: a crest command is the same class in any
                // flavor. It sits here because ADR is the only populated set,
                // and moves to a shared one when cli, mvc and micro arrive.
                'command'    => Layout::SOURCE . '/Command',
                'middleware' => Layout::SOURCE . '/Middleware',
                'provider'   => Layout::SOURCE . '/Provider',
                'responder'  => Layout::SOURCE . '/Responder',
            ],
            Flavor::CLI, Flavor::MVC => [],
        };
    }

    /**
     * @param string|null $directory  Where the walk up starts. Null or empty
     *                                for the working directory.
     * @param string|null $configFile An explicit crest.php, instead of the
     *                                walk up. Null or empty for none.
     */
    public static function discover(?string $directory = null, ?string $configFile = null): self
    {
        $file = self::required($directory, $configFile);

        /** @var array<string, mixed> $declared */
        $declared = require $file;

        return self::fromArray($declared, dirname($file), $file);
    }

    /**
     * The root rule: the explicit config file, else the nearest crest.php on
     * the walk up from the directory, or from the working directory. Null
     * when there is none. An empty value reads as absent. The walk starts
     * at Locator::start(), so a relative or missing directory cannot lead
     * to another project.
     */
    public static function file(?string $directory = null, ?string $configFile = null): ?string
    {
        if (null !== $configFile && '' !== $configFile) {
            if (false === is_file($configFile)) {
                throw new Exception(sprintf('%s was not found', $configFile));
            }

            return $configFile;
        }

        return Locator::locate(Locator::start($directory));
    }

    /**
     * The project root: the folder of the crest.php that file() finds.
     */
    public static function rootFor(?string $directory = null, ?string $configFile = null): string
    {
        return dirname(self::required($directory, $configFile));
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
            // crest.php never restates the autoload map; namespaceFor()
            // still needs it whenever `namespaces` does not answer the
            // question outright.
            Manifest::psr4($root),
            $source,
            Runtime::fromConfig($declared['runtime'] ?? null),
            $stated,
            $bootstrap
        );
    }

    /**
     * The crest.php that file() finds. No crest.php stops the command with the
     * crest init hint.
     */
    private static function required(?string $directory, ?string $configFile): string
    {
        return self::file($directory, $configFile) ?? throw new Exception(self::MISSING);
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
     * Whether the config file stated this key, as opposed to it taking a
     * default. `flavor`, `namespace`, `paths`, or `paths.<name>` for one path.
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
     * Where the project commands run (the `runtime` key).
     */
    public function runtime(): Runtime
    {
        return $this->runtime;
    }

    /**
     * The crest.php this was read from.
     */
    public function source(): string
    {
        return $this->source;
    }
}
