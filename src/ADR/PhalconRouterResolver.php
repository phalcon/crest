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

namespace Crest\ADR;

use Crest\Console\Exceptions\Exception;
use Phalcon\ADR\Router\Router;

use function class_exists;

/**
 * Delegates to the framework's published contract,
 * Phalcon\ADR\Router\Router::classFor(). Crest deliberately holds no copy of
 * the routing convention.
 *
 * classFor() rather than candidatesFor() because the latter walks the action
 * directory to find where static segments end, and a generator is called
 * precisely when those directories do not exist yet. classFor() derives from
 * the convention alone, and pathFor() inverts it exactly.
 *
 * Available in both variants - the phalcon/phalcon package on v6, the
 * extension on v5 - because crest runs from the target project's vendor
 * directory and shares its autoloader.
 */
final class PhalconRouterResolver implements ActionResolver
{
    public function classFor(string $baseNamespace, string $method, string $path): string
    {
        return $this->router($baseNamespace)->classFor($method, $path);
    }

    public function methodFor(string $baseNamespace, string $class): ?string
    {
        return $this->router($baseNamespace)->methodFor($class);
    }

    public function pathFor(string $baseNamespace, string $class): ?string
    {
        return $this->router($baseNamespace)->pathFor($class);
    }

    private function router(string $baseNamespace): Router
    {
        if (false === class_exists(Router::class)) {
            throw new Exception(
                'this command needs Phalcon to resolve routes; install phalcon/phalcon '
                . 'or enable ext-phalcon in this project'
            );
        }

        $router = new Router();
        $router->setBaseNamespace($baseNamespace);

        return $router;
    }
}
