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

use function is_string;

/**
 * One command invocation: which command was asked for, and the values bound
 * against its definition.
 *
 * argument() and option() return mixed because a definition may declare flags,
 * lists and values alike. The typed accessors alongside them narrow the common
 * string case once, here, so commands do not each repeat an is_string() guard
 * to satisfy the static analyzer.
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

    /**
     * A positional argument as a string, empty when it was not supplied or is
     * not a string.
     */
    public function argumentString(string $name): string
    {
        $value = $this->bound->argument($name);

        return true === is_string($value) ? $value : '';
    }

    public function hasOption(string $name): bool
    {
        return $this->bound->hasOption($name);
    }

    public function option(string $name): mixed
    {
        return $this->bound->option($name);
    }

    /**
     * An option as a string, empty when it was not supplied or is not a string.
     * Use for options whose definition declares a string default, so absence
     * cannot occur in practice.
     */
    public function optionString(string $name): string
    {
        return $this->optionStringOrNull($name) ?? '';
    }

    /**
     * An option as a string, null when it was not supplied or is not a string.
     *
     * Distinct from optionString() because for some options "absent" is a real
     * answer a caller must act on - a project root override that falls back to
     * the working directory cannot treat '' and "unset" alike.
     */
    public function optionStringOrNull(string $name): ?string
    {
        $value = $this->bound->option($name);

        return true === is_string($value) ? $value : null;
    }
}
