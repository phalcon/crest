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

use Crest\Console\Parsing\Bound;

/**
 * One command invocation: which command was asked for, and the values bound
 * against its definition.
 */
final class Input
{
    public function __construct(
        public readonly string $command,
        private readonly Bound $bound,
    ) {
    }

    public function argument(string $name): mixed
    {
        return $this->bound->argument($name);
    }

    public function hasOption(string $name): bool
    {
        return $this->bound->hasOption($name);
    }

    public function option(string $name): mixed
    {
        return $this->bound->option($name);
    }
}
