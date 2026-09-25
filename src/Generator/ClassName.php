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

use function explode;
use function preg_match;
use function sprintf;
use function str_ends_with;
use function trim;

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
     *
     * The high-byte range is PHP's own rule for an identifier, so a class named
     * in a non-Latin script is accepted rather than refused for being unusual.
     * Deliberately byte-oriented and not /u: that is exactly how PHP itself
     * decides what may name a class.
     */
    private const PATTERN = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/';

    /**
     * A namespace: one name, or more names that backslashes join. Each name
     * follows the identifier rule above. Leading and trailing backslashes are
     * removed, because `\App` and `App\` both mean App.
     */
    public static function namespace(string $namespace): string
    {
        $trimmed = trim($namespace, '\\');

        foreach (explode('\\', $trimmed) as $segment) {
            if (0 === preg_match(self::PATTERN, $segment)) {
                throw new Exception(
                    sprintf(
                        "'%s' is not a usable namespace; expected something like 'App' or 'Acme\\Shop'",
                        $namespace
                    )
                );
            }
        }

        return $trimmed;
    }

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
