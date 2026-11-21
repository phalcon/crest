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

use Composer\Autoload\ClassLoader;
use Crest\Console\Command\Command;
use Crest\Console\Exceptions\Exception;
use ReflectionClass;

use function array_keys;
use function file_get_contents;
use function is_array;
use function is_file;
use function is_string;
use function is_subclass_of;
use function json_decode;
use function ksort;
use function method_exists;
use function sprintf;
use function str_starts_with;

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
 * withDefault() names the command that runs when the user names none. The
 * kernel gives it all the tokens.
 *
 * Ships empty and names no commands: the owning tool seeds it.
 */
final class Registry
{
    /** @var array<string, string> */
    private array $aliases = [];

    /** @var array<string, class-string<Command>> */
    private array $commands = [];

    private ?string $default = null;

    private bool $discovered = false;

    private ?string $discoveryKey = null;

    /** @var array<string, string> */
    private array $providers = [];

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
     * True when `new $class()` can create the class: it is a Command, it can be
     * instantiated (it is not abstract or an interface, and its constructor is
     * public), and its constructor needs no argument.
     */
    private static function creatable(string $class): bool
    {
        if (false === is_subclass_of($class, Command::class)) {
            return false;
        }

        $reflection  = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        return true === $reflection->isInstantiable()
            && (null === $constructor || 0 === $constructor->getNumberOfRequiredParameters());
    }

    /**
     * The array under a key of $data, or an empty array when $data or the
     * value is not an array.
     *
     * @return array<mixed>
     */
    private static function entry(mixed $data, string $key): array
    {
        $value = true === is_array($data) ? $data[$key] ?? null : null;

        return true === is_array($value) ? $value : [];
    }

    /**
     * The `extra` block of each package in a vendor folder, then the one of
     * the root project. Composer keeps the blocks of the packages in
     * `composer/installed.json` and the one of the root in the root
     * composer.json. `composer/installed.php`, which InstalledVersions reads,
     * has no `extra`; it gives the path of the root.
     *
     * @return list<array<mixed>>
     */
    private static function extras(string $vendor): array
    {
        $extras = [];

        foreach (self::entry(self::json($vendor . '/composer/installed.json'), 'packages') as $package) {
            $extras[] = self::entry($package, 'extra');
        }

        $installed = $vendor . '/composer/installed.php';
        $root      = true === is_file($installed) ? self::entry(require $installed, 'root') : [];
        $path      = $root['install_path'] ?? null;

        if (true === is_string($path)) {
            $extras[] = self::entry(self::json($path . '/composer.json'), 'extra');
        }

        return $extras;
    }

    /**
     * The decoded JSON of a file, or null when the file does not exist, cannot be
     * read, or does not hold valid JSON.
     */
    private static function json(string $file): mixed
    {
        $content = true === is_file($file) ? file_get_contents($file) : null;

        return true === is_string($content) ? json_decode($content, true) : null;
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
     * The command that runs when the user names none. Null when the tool
     * names none: the kernel then prints its own listing.
     */
    public function defaultName(): ?string
    {
        return $this->default;
    }

    /**
     * Every canonical name mapped to its description, sorted by name.
     *
     * The one method here that instantiates anything: a description lives on
     * the command's definition, so answering this means constructing each
     * command. Resolution through get()/has() still instantiates nothing.
     *
     * Lives here rather than in the callers because two callers print this
     * listing: the kernel, when the tool names no default command, and the
     * listing command of the tool. Two copies of the loop had to be kept in
     * agreement by hand.
     *
     * @param (callable(Command): bool)|null $keep Keeps a command when it gives
     *                                             true. Null keeps all of them.
     *
     * @return array<string, string>
     */
    public function descriptions(?callable $keep = null): array
    {
        $commands = $this->all();
        ksort($commands);

        $descriptions = [];
        foreach ($commands as $name => $class) {
            $command = new $class();

            if (null !== $keep && false === $keep($command)) {
                continue;
            }

            $descriptions[$name] = $command->define()->getDescription();
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
            ?? throw new Exception(sprintf("unknown command '%s'%s", $name, $this->provider($name)));
    }

    public function has(string $name): bool
    {
        return isset($this->commands[$this->resolve($name)]);
    }

    /**
     * Records the command that runs when the user names none. The name is not
     * checked here: get() reports an unknown name when the kernel runs it.
     */
    public function withDefault(string $name): static
    {
        $this->default = $name;

        return $this;
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
     * Records which package provides the commands with a name prefix, for
     * example `demo:` => `vendor/demo`. For an unknown name with that prefix,
     * get() names the package in its message.
     *
     * Strings only: the owning tool names the packages, and nothing here
     * loads their classes.
     *
     * @param array<string, string> $providers Name prefix => package name.
     */
    public function withProviders(array $providers): static
    {
        $this->providers = $providers;

        return $this;
    }

    /**
     * Registers whatever one package contributes under the discovery key.
     *
     * Split out of discover() to keep the nesting shallow: walking datasets,
     * packages and commands in one method put its cognitive complexity well
     * past what the analyzers allow. Anything malformed is skipped rather than
     * rejected - a broken contributor must not stop the scan. A name that is
     * in $seeded stays with the seeded command. A class that is not a
     * Command, cannot be instantiated, or has a constructor that needs an
     * argument is skipped.
     *
     * @param mixed                 $extra  The package's `extra.<key>` value,
     *                                      whatever it holds.
     * @param array<string, string> $seeded The seeded names and aliases.
     */
    private function addContributed(mixed $extra, array $seeded): void
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

            if (true === isset($seeded[$name]) || false === self::creatable($class)) {
                continue;
            }

            /** @var class-string<Command> $class */
            $this->add($name, $class);
        }
    }

    /**
     * Folds in commands contributed by installed packages and by the root
     * project through `extra.<key>.commands` in their composer.json. Reads
     * each vendor folder that has a registered composer class loader. A
     * contributed command cannot replace a seeded command or alias. Runs at
     * most once, and only when something actually needs it.
     */
    private function discover(): void
    {
        if (true === $this->discovered || null === $this->discoveryKey) {
            return;
        }

        $this->discovered = true;

        // Composer 1 and early 2.0 releases do not have the method.
        // InstalledVersions does the same check.
        if (false === method_exists(ClassLoader::class, 'getRegisteredLoaders')) {
            return;
        }

        $key    = $this->discoveryKey;
        $seeded = $this->commands + $this->aliases;

        foreach (array_keys(ClassLoader::getRegisteredLoaders()) as $vendor) {
            foreach (self::extras($vendor) as $extra) {
                $this->addContributed($extra[$key] ?? null, $seeded);
            }
        }
    }

    /**
     * The end of the message for an unknown name: `; provided by <package>`
     * for the first prefix that the name starts with. Empty when no prefix
     * matches, or when a registered command has the prefix: then the package
     * is installed, and only the name is wrong.
     */
    private function provider(string $name): string
    {
        foreach ($this->providers as $prefix => $package) {
            if (false === str_starts_with($name, $prefix)) {
                continue;
            }

            foreach (array_keys($this->commands) as $command) {
                if (true === str_starts_with($command, $prefix)) {
                    return '';
                }
            }

            return sprintf('; provided by %s', $package);
        }

        return '';
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
