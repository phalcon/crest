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
use Crest\Paths;
use Phalcon\ADR\Router\Router;

use function class_exists;
use function sprintf;

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
    /**
     * The error when the running crest has no Phalcon. %s is the folder of the
     * running crest.
     */
    private const NO_PHALCON = <<<'TEXT'
        %s has no Phalcon: install phalcon/phalcon or enable ext-phalcon, or set 'runtime' to docker in crest.php
        TEXT;

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
            // The running crest has no Phalcon. A project crest on a host
            // without ext-phalcon is the usual case: the docker runtime runs
            // it in the container, where Phalcon is.
            throw new Exception(sprintf(self::NO_PHALCON, Paths::root()));
        }

        $router = new Router();
        $router->setBaseNamespace($baseNamespace);

        return $router;
    }
}
