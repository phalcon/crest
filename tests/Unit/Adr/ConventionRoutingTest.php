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

namespace Crest\Tests\Unit\Adr;

use Crest\Adr\Convention;
use Crest\Adr\PhalconRouterCandidates;
use PHPUnit\Framework\TestCase;

/**
 * Conformance: every mapping crest relies on, produced by the framework rather
 * than by crest.
 */
final class ConventionRoutingTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function referenceRoutes(): iterable
    {
        yield 'root'        => ['GET', '/', 'App\Action\Get', 'Get.php'];
        yield 'resource'    => ['GET', '/profiles', 'App\Action\Profiles\GetProfiles', 'Profiles/GetProfiles.php'];
        yield 'operation'   => [
            'GET',
            '/profiles/create',
            'App\Action\Profiles\GetProfilesCreate',
            'Profiles/GetProfilesCreate.php',
        ];
        yield 'company all' => ['GET', '/company/all', 'App\Action\Company\GetCompanyAll', 'Company/GetCompanyAll.php'];
        yield 'placeholder' => ['GET', '/company/{id}', 'App\Action\Company\GetCompany', 'Company/GetCompany.php'];
        yield 'dashed'      => [
            'POST',
            '/session/forgot-password',
            'App\Action\Session\PostSessionForgotPassword',
            'Session/PostSessionForgotPassword.php',
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

    public function testCandidatesComeBackInRouterPrecedenceOrder(): void
    {
        $candidates = $this->convention()->candidates('GET', '/profiles/create');

        $this->assertSame('App\Action\Profiles\GetProfilesCreate', $candidates[0]);
        $this->assertContains('App\Action\Profiles\GetProfiles', $candidates);
    }

    private function convention(): Convention
    {
        return new Convention('App\Action', new PhalconRouterCandidates());
    }
}
