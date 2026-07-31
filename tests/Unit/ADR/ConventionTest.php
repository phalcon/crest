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
use Crest\Console\Exceptions\Exception;
use Crest\Tests\Support\ADR\StubActionResolver;
use PHPUnit\Framework\TestCase;

final class ConventionTest extends TestCase
{
    public function testAStaticSegmentAfterAPlaceholderIsRejected(): void
    {
        // The convention cannot name this route, so the user is told rather
        // than handed a file that answers a different URL.
        $resolver = new StubActionResolver('App\Action\Album\Edit\GetAlbumEdit');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "'edit' cannot follow a placeholder; arguments come last, "
            . "so write the route as '/album/edit/{id}'"
        );

        (new Convention('App\Action', $resolver))->target('GET', '/album/{id}/edit');
    }

    public function testClassWithNoNamespaceYieldsAnEmptyNamespace(): void
    {
        $resolver = new StubActionResolver('Get');

        $target = (new Convention('', $resolver))->target('GET', '/');

        $this->assertSame('', $target->namespace);
        $this->assertSame('Get', $target->class);
    }
    public function testEmptyPathIsPassedThroughAsRoot(): void
    {
        $resolver = new StubActionResolver('App\Action\Get');

        (new Convention('App\Action', $resolver))->target('GET', '/');

        $this->assertSame(['App\Action', 'GET', '/'], $resolver->calls[0]);
    }

    public function testMethodIsUppercasedOnTheTarget(): void
    {
        $resolver = new StubActionResolver('App\Action\Company\PostCompany');

        $target = (new Convention('App\Action', $resolver))->target('post', '/company');

        $this->assertSame('POST', $target->method);
    }

    public function testOnlyTheStaticPrefixReachesTheResolver(): void
    {
        $resolver = new StubActionResolver('App\Action\Company\GetCompany');

        (new Convention('App\Action', $resolver))->target('GET', '/company/{id}');

        $this->assertSame(['App\Action', 'GET', '/company'], $resolver->calls[0]);
    }

    public function testTargetSplitsTheClassIntoNamespaceClassAndPath(): void
    {
        $resolver = new StubActionResolver('App\Action\Company\All\GetCompanyAll');

        $target = (new Convention('App\Action', $resolver))->target('GET', '/company/all');

        $this->assertSame('App\Action\Company\All\GetCompanyAll', $target->fqcn);
        $this->assertSame('App\Action\Company\All', $target->namespace);
        $this->assertSame('GetCompanyAll', $target->class);
        $this->assertSame('Company/All/GetCompanyAll.php', $target->relativePath);
        $this->assertSame([], $target->attributes);
    }

    public function testTheSuggestionKeepsEverySegmentInOrder(): void
    {
        // Two statics and two placeholders, so the suggestion has to carry all
        // of both rather than the first of each.
        $resolver = new StubActionResolver('App\Action\Album\Edit\GetAlbumEdit');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("so write the route as '/album/edit/{id}/{slug}'");

        (new Convention('App\Action', $resolver))->target('GET', '/album/{id}/edit/{slug}');
    }

    public function testTopLevelClassYieldsAFlatPath(): void
    {
        $resolver = new StubActionResolver('App\Action\Get');

        $target = (new Convention('App\Action', $resolver))->target('GET', '/');

        $this->assertSame('Get.php', $target->relativePath);
        $this->assertSame('App\Action', $target->namespace);
    }

    public function testTrailingBackslashesOnTheBaseNamespaceAreIgnored(): void
    {
        // Router::setBaseNamespace() rtrims too; if the two disagreed the
        // relative path would lose or keep a leading separator.
        $resolver = new StubActionResolver('App\Action\Company\GetCompany');

        $target = (new Convention('App\Action\\\\', $resolver))->target('GET', '/company');

        $this->assertSame('Company/GetCompany.php', $target->relativePath);
        $this->assertSame(['App\Action', 'GET', '/company'], $resolver->calls[0]);
    }

    public function testTrailingPlaceholdersAllBecomeAttributes(): void
    {
        $resolver = new StubActionResolver('App\Action\Company\Users\GetCompanyUsers');

        $target = (new Convention('App\Action', $resolver))
            ->target('GET', '/company/users/{id}/{userId}');

        $this->assertSame(['id', 'userId'], $target->attributes);
        $this->assertSame(['App\Action', 'GET', '/company/users'], $resolver->calls[0]);
    }
}
