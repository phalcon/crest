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

namespace Crest\Adr;

use Crest\Console\Exceptions\Exception;
use Phalcon\ADR\Router\Router;

use function class_exists;

/**
 * Delegates to the framework's published contract,
 * Phalcon\ADR\Router\Router::candidatesFor(). Crest deliberately holds no copy
 * of the routing convention: locate() and candidatesFor() share one derivation
 * inside the framework, so what crest is told here is what routing actually
 * does.
 *
 * Available in both variants - the phalcon/phalcon package on v6, the
 * extension on v5 - because crest runs from the target project's vendor
 * directory and shares its autoloader.
 */
final class PhalconRouterCandidates implements CandidateSource
{
    /**
     * @return list<string>
     */
    public function candidatesFor(string $baseNamespace, string $method, string $path): array
    {
        if (false === class_exists(Router::class)) {
            throw new Exception(
                'make:action needs Phalcon to resolve the route; install phalcon/phalcon '
                . 'or enable ext-phalcon in this project'
            );
        }

        $router = new Router();
        $router->setBaseNamespace($baseNamespace);

        return $router->candidatesFor($method, $path);
    }
}
