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
 * One declared option.
 *
 * `final class` with readonly properties, not `final readonly class` - the
 * latter is PHP 8.2 and the floor is 8.1.
 */
final class Option
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $short,
        public readonly OptionMode $mode,
        public readonly mixed $default,
        public readonly string $description,
    ) {
    }
}
