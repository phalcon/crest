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
 * The place of the base class that crest owns for the MVC controllers
 * (Generation Gap, #46). make:controller and `new` write the base to the same
 * file, with the same namespace. Thus a later make:controller finds the base
 * that `new` wrote.
 */
final class ControllerBase
{
    /**
     * The subfolder of the `controller` path that holds the base, and the
     * last part of the namespace of the base.
     */
    public const FOLDER = 'Base';

    /**
     * The base class. The name does not end in the suffix, so no generated
     * controller can have it, and the import needs no alias.
     */
    public const NAME = 'CrestControllerBase';

    /**
     * The file of the base, in the folder of the controllers.
     */
    public static function file(string $controllerPath): string
    {
        return $controllerPath . '/' . self::FOLDER . '/' . self::NAME . '.php';
    }

    /**
     * The namespace of the base, below the namespace of the controllers.
     */
    public static function namespace(string $controllerNamespace): string
    {
        return $controllerNamespace . '\\' . self::FOLDER;
    }
}
