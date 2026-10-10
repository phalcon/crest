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

/**
 * The result of Definition::extract(): the options that the tokens gave, and
 * the other tokens, in order.
 */
final class Extracted
{
    /**
     * @param array<string, mixed> $options Only the options that the tokens
     *                                      gave. The last value wins.
     * @param list<string>         $rest    The other tokens, in order.
     */
    public function __construct(
        public readonly array $options,
        public readonly array $rest,
    ) {
    }

    /**
     * The value of an option. Null when the tokens did not give it, or gave
     * it without the value that it needs.
     */
    public function option(string $name): mixed
    {
        return $this->options[$name] ?? null;
    }
}
