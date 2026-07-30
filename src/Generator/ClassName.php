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

use Crest\Console\Exceptions\Exception;

use function preg_match;
use function sprintf;
use function str_ends_with;

/**
 * Turns the name a user typed into the class name a generator writes.
 *
 * Suffixing is idempotent: `make:middleware Cors` and
 * `make:middleware CorsMiddleware` both produce CorsMiddleware. Someone who
 * spells out the convention should not be punished with
 * CorsMiddlewareMiddleware for knowing it.
 *
 * The name is otherwise taken verbatim - crest does not case-correct it,
 * because the class it writes should be the class that was asked for.
 *
 * make:action never comes through here: Convention derives that class name from
 * the route, so nothing the user types names it.
 */
final class ClassName
{
    /**
     * One unqualified class name. Namespaced input is rejected rather than
     * split into directories, because the answer to `make:responder Admin/Album`
     * is a decision about layout, not something to guess at.
     */
    private const PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    public static function suffixed(string $name, string $suffix): string
    {
        if (0 === preg_match(self::PATTERN, $name)) {
            throw new Exception(
                sprintf("'%s' is not a usable class name; expected a single name like 'Album'", $name)
            );
        }

        if (true === str_ends_with($name, $suffix)) {
            return $name;
        }

        return $name . $suffix;
    }
}
