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

namespace Crest\Console\Parsing;

use Crest\Console\Parsing\Exceptions\Exception;

use function array_key_exists;
use function array_keys;
use function array_shift;
use function count;
use function explode;
use function sprintf;
use function str_contains;
use function str_split;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * A command's schema. Declared once, then used for three things: binding argv
 * (Task 4), rendering usage, and rejecting nonsense before a command runs.
 */
final class Definition
{
    /** @var list<Argument> */
    private array $arguments = [];

    /** @var list<Option> */
    private array $options = [];

    private function __construct(
        private readonly string $name,
        private readonly string $description,
    ) {
    }

    public static function for(string $name, string $description = ''): self
    {
        return new self($name, $description);
    }

    public function argument(
        string $name,
        bool $required = false,
        string $description = '',
        mixed $default = null,
    ): static {
        if (true === $required) {
            foreach ($this->arguments as $existing) {
                if (false === $existing->required) {
                    throw new Exception(
                        sprintf(
                            "required argument '%s' cannot follow optional argument '%s'",
                            $name,
                            $existing->name
                        )
                    );
                }
            }
        }

        $this->arguments[] = new Argument($name, $required, $default, $description);

        return $this;
    }

    /**
     * Binds raw tokens against this schema. The schema is consulted *during*
     * tokenization: without it, `--force GET` would consume `GET` as the value
     * of a flag and lose the positional entirely.
     *
     * @param list<string> $tokens argv minus the script name and command name
     */
    public function bind(array $tokens): Bound
    {
        $options     = [];
        $positionals = [];
        $literal     = false;

        while ([] !== $tokens) {
            $token = array_shift($tokens);

            if (true === $literal) {
                $positionals[] = $token;

                continue;
            }

            if ('--' === $token) {
                $literal = true;

                continue;
            }

            if (true === str_starts_with($token, '--')) {
                $this->bindLongOption(substr($token, 2), $tokens, $options);

                continue;
            }

            if (true === str_starts_with($token, '-') && strlen($token) > 1) {
                $this->bindShortOptions(substr($token, 1), $tokens, $options);

                continue;
            }

            $positionals[] = $token;
        }

        return new Bound(
            $this->resolveArguments($positionals),
            $this->resolveOptions($options),
            array_keys($options)
        );
    }

    public function findOption(string $name): ?Option
    {
        foreach ($this->options as $option) {
            if ($option->name === $name || $option->short === $name) {
                return $option;
            }
        }

        return null;
    }

    /**
     * @return list<Argument>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return list<Option>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Folds another definition's options into this one, keeping this
     * definition's name. Used by the kernel to merge global options into each
     * command. Collisions are a programming error, not user input.
     */
    public function merge(self $other): static
    {
        foreach ($other->getOptions() as $option) {
            $this->guardAgainstCollision($option);

            $this->options[] = $option;
        }

        return $this;
    }

    /**
     * @param string $spec `name`, `name|s`, `name=s`, `name=s?` or `name=l`
     */
    public function option(string $spec, string $description = '', mixed $default = null): static
    {
        $mode = OptionMode::None;

        if (true === str_contains($spec, '=')) {
            [$spec, $suffix] = explode('=', $spec, 2);

            // OptionMode::None is backed by '', so tryFrom('') would silently
            // turn the malformed spec 'name=' into a flag.
            if ('' === $suffix) {
                throw new Exception(sprintf("option spec '%s=' is missing a mode", $spec));
            }

            $mode = OptionMode::tryFrom($suffix)
                ?? throw new Exception(sprintf("unknown option mode '=%s'", $suffix));
        }

        $short = null;
        if (true === str_contains($spec, '|')) {
            [$spec, $short] = explode('|', $spec, 2);
        }

        $option = new Option($spec, $short, $mode, $default, $description);

        $this->guardAgainstCollision($option);

        $this->options[] = $option;

        return $this;
    }

    /**
     * @param list<string>         $tokens
     * @param array<string, mixed> $options
     */
    private function bindLongOption(string $token, array &$tokens, array &$options): void
    {
        $value = null;
        if (true === str_contains($token, '=')) {
            [$token, $value] = explode('=', $token, 2);
        }

        $option = $this->findOption($token);
        if (null === $option) {
            throw new Exception(sprintf("unknown option '--%s'", $token));
        }

        $options[$option->name] = $this->valueFor($option, $value, $tokens, true);
    }

    /**
     * @param list<string>         $tokens
     * @param array<string, mixed> $options
     */
    private function bindShortOptions(string $cluster, array &$tokens, array &$options): void
    {
        $letters = str_split($cluster);
        $last    = count($letters) - 1;

        foreach ($letters as $index => $letter) {
            $option = $this->findOption($letter);
            if (null === $option) {
                throw new Exception(sprintf("unknown option '-%s'", $letter));
            }

            // Only the final letter in a cluster may consume the next token:
            // in `-fq value`, `value` belongs to `q`, never to `f`.
            $options[$option->name] = $this->valueFor(
                $option,
                null,
                $tokens,
                $index === $last
            );
        }
    }

    private function guardAgainstCollision(Option $option): void
    {
        if (null !== $this->findOption($option->name)) {
            throw new Exception(sprintf("option '--%s' is already declared", $option->name));
        }

        if (null !== $option->short && null !== $this->findOption($option->short)) {
            throw new Exception(sprintf("option '-%s' is already declared", $option->short));
        }
    }

    /**
     * @param list<string> $positionals
     *
     * @return array<string, mixed>
     */
    private function resolveArguments(array $positionals): array
    {
        $resolved = [];

        foreach ($this->arguments as $index => $argument) {
            if (true === array_key_exists($index, $positionals)) {
                $resolved[$argument->name] = $positionals[$index];

                continue;
            }

            if (true === $argument->required) {
                throw new Exception(sprintf("missing required argument '%s'", $argument->name));
            }

            $resolved[$argument->name] = $argument->default;
        }

        $extra = count($positionals) - count($this->arguments);
        if ($extra > 0) {
            throw new Exception(
                sprintf("unexpected argument '%s'", $positionals[count($this->arguments)])
            );
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $supplied
     *
     * @return array<string, mixed>
     */
    private function resolveOptions(array $supplied): array
    {
        $resolved = [];

        foreach ($this->options as $option) {
            $resolved[$option->name] = $supplied[$option->name]
                ?? ($option->mode === OptionMode::None ? false : $option->default);
        }

        return $resolved;
    }

    /**
     * @param list<string> $tokens     Remaining tokens, shifted in place when
     *                                 this option takes the next one as its
     *                                 value.
     * @param bool         $mayConsume False for a non-final letter in a short
     *                                 cluster, which can never own the next
     *                                 token.
     */
    private function valueFor(
        Option $option,
        ?string $attached,
        array &$tokens,
        bool $mayConsume,
    ): mixed {
        if ($option->mode === OptionMode::None) {
            if (null !== $attached) {
                throw new Exception(sprintf("option '--%s' takes no value", $option->name));
            }

            return true;
        }

        $value = $attached;

        if (null === $value && true === $mayConsume && [] !== $tokens) {
            $next = $tokens[0];

            if ('--' !== $next && false === str_starts_with($next, '-')) {
                $value = array_shift($tokens);
            }
        }

        if (null === $value) {
            if ($option->mode === OptionMode::Optional) {
                return $option->default;
            }

            throw new Exception(sprintf("option '--%s' requires a value", $option->name));
        }

        if ($option->mode === OptionMode::List) {
            return explode(',', $value);
        }

        return $value;
    }
}
