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

use function in_array;

/**
 * The result of binding argv against a Definition: defaults applied, values
 * cast, everything validated. Consumers never see raw tokens.
 */
final class Bound
{
    /**
     * @param array<string, mixed> $arguments
     * @param array<string, mixed> $options  Every declared option, defaults applied.
     * @param list<string>         $supplied Only the options the caller actually passed.
     */
    public function __construct(
        private readonly array $arguments,
        private readonly array $options,
        private readonly array $supplied,
    ) {
    }

    public function argument(string $name): mixed
    {
        return $this->arguments[$name] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Whether the caller actually passed this option, as opposed to it being
     * declared and defaulted. Answering from $options would make this always
     * true for declared options, which is useless.
     */
    public function hasOption(string $name): bool
    {
        return in_array($name, $this->supplied, true);
    }

    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }
}
