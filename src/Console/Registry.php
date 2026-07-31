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
use Crest\Console\Command\Command;
use Crest\Console\Exceptions\Exception;

use function class_exists;
use function is_array;
use function is_string;
use function ksort;
use function sprintf;

/**
 * Lazy name-to-class map. Resolution instantiates nothing; the kernel
 * constructs only the command it is about to run.
 *
 * Package discovery is deferred: withDiscovery() records the composer key but
 * scans nothing. The scan happens on the first name that misses the seeded map,
 * or when all() is called, so running a seeded command never pays for reading
 * every installed package's composer metadata.
 *
 * Aliases resolve through get()/has() but never appear in all(), so `list`
 * shows one row per command.
 *
 * Ships empty and names no commands: the owning tool seeds it.
 */
final class Registry
{
    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, class-string<Command>> */
    private array $commands = [];

    private bool $discovered = false;

    private ?string $discoveryKey = null;

    /**
     * @param array<string, class-string<Command>> $map
     */
    public function __construct(array $map = [])
    {
        foreach ($map as $name => $class) {
            $this->add($name, $class);
        }
    }

    /**
     * @param class-string<Command> $class
     */
    public function add(string $name, string $class, string ...$aliases): static
    {
        $this->commands[$name] = $class;

        foreach ($aliases as $alias) {
            $this->aliases[$alias] = $name;
        }

        return $this;
    }

    /**
     * Every canonical name, including package-contributed ones - `list` has to
     * show them, so this is one of the two places discovery is forced.
     *
     * @return array<string, class-string<Command>>
     */
    public function all(): array
    {
        $this->discover();

        return $this->commands;
    }

    /**
     * Every canonical name mapped to its description, sorted by name.
     *
     * The one method here that instantiates anything: a description lives on
     * the command's definition, so answering this means constructing each
     * command. Resolution through get()/has() still instantiates nothing.
     *
     * Lives here rather than in the callers because the kernel prints this
     * listing when invoked with no arguments and the addressable `list` command
     * prints the same thing - two copies of the loop that had to be kept in
     * agreement by hand.
     *
     * @return array<string, string>
     */
    public function descriptions(): array
    {
        $commands = $this->all();
        ksort($commands);

        $descriptions = [];
        foreach ($commands as $name => $class) {
            $descriptions[$name] = (new $class())->define()->getDescription();
        }

        return $descriptions;
    }

    /**
     * @return class-string<Command>
     */
    public function get(string $name): string
    {
        $resolved = $this->resolve($name);

        return $this->commands[$resolved]
            ?? throw new Exception(sprintf("unknown command '%s'", $name));
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$this->resolve($name)]);
    }

    /**
     * Records the composer `extra` key that packages contribute commands under.
     * Scans nothing: discovery runs on the first lookup that misses the seeded
     * map, or on all().
     *
     * This is how phalcon/migrations contributes `migration:*` without the
     * owning tool knowing it exists.
     */
    public function withDiscovery(string $key): static
    {
        $this->discoveryKey = $key;

        return $this;
    }

    /**
     * Registers whatever one package contributes under the discovery key.
     *
     * Split out of discover() to keep the nesting shallow: walking datasets,
     * packages and commands in one method put its cognitive complexity well
     * past what the analyzers allow. Anything malformed is skipped rather than
     * rejected - a broken contributor must not stop the scan.
     *
     * @param mixed $extra The package's `extra.<key>` value, whatever it holds.
     */
    private function addContributed(mixed $extra): void
    {
        if (false === is_array($extra)) {
            return;
        }

        $commands = $extra['commands'] ?? null;

        if (false === is_array($commands)) {
            return;
        }

        foreach ($commands as $name => $class) {
            if (false === is_string($name) || false === is_string($class)) {
                continue;
            }

            if (false === class_exists($class)) {
                continue;
            }

            /** @var class-string<Command> $class */
            $this->add($name, $class);
        }
    }

    /**
     * Folds in commands contributed by installed packages through
     * `extra.<key>.commands` in their composer.json. Runs at most once, and
     * only when something actually needs it.
     */
    private function discover(): void
    {
        if (true === $this->discovered || null === $this->discoveryKey) {
            return;
        }

        $this->discovered = true;
        $key              = $this->discoveryKey;

        if (false === class_exists(InstalledVersions::class)) {
            return;
        }

        foreach (InstalledVersions::getAllRawData() as $dataset) {
            /** @var array<string, array{extra?: array<string, mixed>}> $versions */
            $versions = $dataset['versions'] ?? [];

            foreach ($versions as $package) {
                // `extra` holds mixed values, so the key is read one offset at
                // a time and the rest of the unwrapping happens in the helper.
                $this->addContributed($package['extra'][$key] ?? null);
            }
        }
    }

    /**
     * Canonical name for a name or alias. A miss triggers discovery once, then
     * re-resolves - a package may contribute the alias as well as the command.
     */
    private function resolve(string $name): string
    {
        $resolved = $this->aliases[$name] ?? $name;

        if (true === isset($this->commands[$resolved])) {
            return $resolved;
        }

        $this->discover();

        return $this->aliases[$name] ?? $name;
    }
}
