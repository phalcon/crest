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

namespace Crest\Generator;

/**
 * Where a named artifact goes and what it is called.
 *
 * The counterpart to Crest\ADR\Target, which answers the same question for
 * Actions - except an Action's name is derived from its route, so Target also
 * carries the method and path. Everything else is named by the user, so this
 * carries nothing but the placement.
 *
 * Holds no reference to Config: the command resolves the three values and hands
 * them over, which keeps project configuration out of Crest\Generator.
 */
final class Placement
{
    public function __construct(
        public readonly string $class,
        public readonly string $file,
        public readonly string $namespace,
    ) {
    }
}
