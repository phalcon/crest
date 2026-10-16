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

use function array_map;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function sprintf;
use function str_ends_with;
use function strtolower;
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
    public const PATTERN = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*\z/';

    /**
     * The words that PHP 8.1 refuses as a class name, in lowercase, that
     * fromTable() can give. PHP compares class names without regard to case.
     * __halt_compiler, include_once and require_once are also reserved, but
     * fromTable() splits at `_`, so it cannot give them.
     */
    public const RESERVED = [
        'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch', 'class',
        'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif',
        'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'eval',
        'exit', 'extends', 'false', 'final', 'finally', 'float', 'fn', 'for', 'foreach', 'function',
        'global', 'goto', 'if', 'implements', 'include', 'instanceof', 'insteadof', 'int',
        'interface', 'isset', 'iterable', 'list', 'match', 'mixed', 'namespace', 'never', 'new',
        'null', 'object', 'or', 'parent', 'print', 'private', 'protected', 'public', 'readonly',
        'require', 'return', 'self', 'static', 'string', 'switch', 'throw', 'trait', 'true', 'try',
        'unset', 'use', 'var', 'void', 'while', 'xor', 'yield',
    ];

    /**
     * The class of the model of a table. The table name is split at each `_`,
     * the first letter of each part becomes uppercase, and the parts are
     * joined: `user_profiles` gives UserProfiles. The other letters do not
     * change, as in suffixed(). There is no singular form.
     *
     * The check runs before crest boots the project, so a name that cannot
     * be a class stops the command first.
     */
    public static function fromTable(string $table): string
    {
        $class = self::studly($table);

        if (0 === preg_match(self::PATTERN, $class)) {
            throw new Exception(
                sprintf(
                    "'%s' gives no usable class name; expected a table name like 'users' or 'user_profiles'",
                    $table
                )
            );
        }

        if (true === in_array(strtolower($class), self::RESERVED, true)) {
            throw new Exception(sprintf("'%s' gives the class %s, but PHP reserves that name", $table, $class));
        }

        return $class;
    }

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

    /**
     * The name split at each `_`, with the first letter of each part in
     * uppercase, and the parts joined: `billing_address` gives
     * BillingAddress. The other letters do not change. There is no check:
     * fromTable() checks a class name, and ModelRelations checks an alias.
     */
    public static function studly(string $name): string
    {
        return implode('', array_map('ucfirst', explode('_', $name)));
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
