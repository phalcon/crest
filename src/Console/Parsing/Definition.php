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

use function explode;
use function sprintf;
use function str_contains;

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

    private function guardAgainstCollision(Option $option): void
    {
        if (null !== $this->findOption($option->name)) {
            throw new Exception(sprintf("option '--%s' is already declared", $option->name));
        }

        if (null !== $option->short && null !== $this->findOption($option->short)) {
            throw new Exception(sprintf("option '-%s' is already declared", $option->short));
        }
    }
}
