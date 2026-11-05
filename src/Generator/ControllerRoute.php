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
use function implode;
use function preg_replace;
use function preg_split;
use function sprintf;
use function str_ends_with;
use function strlen;
use function strtolower;
use function substr;

/**
 * The URL rule of an MVC controller. make:controller and make:scaffold use it,
 * because no URL reaches a controller that the dispatcher cannot find.
 */
final class ControllerRoute
{
    /**
     * The end of the name of each controller class.
     */
    public const SUFFIX = 'Controller';

    /**
     * The URL segment of the controller with the default routes, after two
     * checks of the name. Both stop the command before it writes a file. The
     * messages name what the user typed.
     *
     * The part of the class name before the suffix must not end in the
     * suffix, in any case: `Usercontroller` gives UsercontrollerController.
     *
     * The segment has `-` before each ASCII uppercase letter except the
     * first, and is in lowercase: UserProfilesController gives
     * `user-profiles`. The dispatcher must make the class name again from it
     * (camelize()). `users` gives usersController, but the URL /users looks
     * for UsersController.
     */
    public static function of(string $name, string $class): string
    {
        $stem = substr($class, 0, -strlen(self::SUFFIX));

        if (true === str_ends_with(strtolower($stem), strtolower(self::SUFFIX))) {
            throw new Exception(
                sprintf(
                    "'%s' gives %s, with '%s' twice; type the name without '%s'",
                    $name,
                    $class,
                    self::SUFFIX,
                    self::SUFFIX
                )
            );
        }

        $route = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $stem) ?? $stem);

        if ('' === $route || $stem !== self::camelize($route)) {
            throw new Exception(
                sprintf(
                    "'%s' gives %s, which the dispatcher cannot find from a URL; use a name like 'Users'",
                    $name,
                    $class
                )
            );
        }

        return $route;
    }

    /**
     * The rule of the dispatcher (AbstractDispatcher::toCamelCase() in Phalcon
     * v5 and v6): split at `-` and `_`, make the first letter of each part
     * uppercase, and join the parts.
     */
    private static function camelize(string $route): string
    {
        return implode('', array_map('ucfirst', preg_split('/[_-]+/', $route) ?: []));
    }
}
