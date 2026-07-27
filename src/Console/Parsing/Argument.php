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
 * One declared positional argument.
 */
final class Argument
{
    public function __construct(
        public readonly string $name,
        public readonly bool $required,
        public readonly mixed $default,
        public readonly string $description,
    ) {
    }
}
