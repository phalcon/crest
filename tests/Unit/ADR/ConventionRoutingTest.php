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

namespace Crest\Tests\Unit\ADR;

use Crest\ADR\Convention;
use Crest\ADR\PhalconRouterResolver;
use Phalcon\ADR\Router\Router;
use PHPUnit\Framework\TestCase;

/**
 * Conformance: every mapping crest relies on, produced by the framework rather
 * than by crest.
 *
 * One path names exactly one class and that class names the same path back, so
 * each route is asserted in both directions. If either side moves, crest is
 * told here rather than by silently writing a file where nothing will route.
 */
final class ConventionRoutingTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function referenceRoutes(): iterable
    {
        yield 'root' => ['GET', '/', 'App\Action\Get', 'Get.php'];

        yield 'resource' => [
            'GET',
            '/profiles',
            'App\Action\Profiles\GetProfiles',
            'Profiles/GetProfiles.php',
        ];

        yield 'operation' => [
            'GET',
            '/profiles/create',
            'App\Action\Profiles\Create\GetProfilesCreate',
            'Profiles/Create/GetProfilesCreate.php',
        ];

        yield 'company all' => [
            'GET',
            '/company/all',
            'App\Action\Company\All\GetCompanyAll',
            'Company/All/GetCompanyAll.php',
        ];

        yield 'placeholder' => [
            'GET',
            '/company/{id}',
            'App\Action\Company\GetCompany',
            'Company/GetCompany.php',
        ];

        yield 'dashed' => [
            'POST',
            '/session/forgot-password',
            'App\Action\Session\ForgotPassword\PostSessionForgotPassword',
            'Session/ForgotPassword/PostSessionForgotPassword.php',
        ];
    }

    /**
     * @dataProvider referenceRoutes
     */
    public function testReferenceRouteResolvesThroughTheRealRouter(
        string $method,
        string $path,
        string $fqcn,
        string $relativePath,
    ): void {
        $target = $this->convention()->target($method, $path);

        $this->assertSame($fqcn, $target->fqcn);
        $this->assertSame($relativePath, $target->relativePath);
    }

    /**
     * The inverse has to agree, or a generated file answers a different URL
     * from the one it was generated for.
     *
     * @dataProvider referenceRoutes
     */
    public function testReferenceRouteRoundTripsBackToItsPath(
        string $method,
        string $path,
        string $fqcn,
        string $relativePath,
    ): void {
        $router = new Router();
        $router->setBaseNamespace('App\Action');

        // A placeholder is not part of the class, so the canonical path is the
        // static prefix - '/company' for '/company/{id}'.
        $expected = '/company/{id}' === $path ? '/company' : $path;

        $this->assertSame($expected, $router->pathFor($fqcn));
    }

    private function convention(): Convention
    {
        return new Convention('App\Action', new PhalconRouterResolver());
    }
}
