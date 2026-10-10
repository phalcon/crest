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

namespace Crest\Project;

/**
 * The layout of a project that `crest new` writes: the names that the
 * project stubs and the commands must agree on. NewCommand gives them to the
 * stubs as placeholders. `serve`, `init` and the default paths read them
 * here. A stub that keeps its placeholders thus agrees with crest.
 */
final class Layout
{
    /**
     * The composer autoloader, relative to the project root. The index file
     * loads it, and `serve` needs it.
     */
    public const AUTOLOADER = 'vendor/autoload.php';

    /**
     * The document root of the web server, relative to the project root.
     */
    public const DOCUMENT_ROOT = 'public';

    /**
     * The variables file of docker compose, relative to the project root.
     */
    public const ENV_FILE = '.env';

    /**
     * The class of the front controller, in the root namespace. `crest init`
     * proposes it when the file is there.
     */
    public const FRONT = 'AppFront';

    /**
     * The port on the host when PORT_VARIABLE has no value. docker compose
     * and `serve` use it.
     */
    public const PORT = 8080;

    /**
     * The variable that holds the port on the host.
     */
    public const PORT_VARIABLE = 'APP_PORT';

    /**
     * The router script of PHP's built-in server, relative to the project
     * root.
     */
    public const ROUTER = '.htrouter.php';

    /**
     * The folder of the root namespace (psr-4), relative to the project
     * root.
     */
    public const SOURCE = 'src';
}
