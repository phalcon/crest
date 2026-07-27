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
use Crest\Console\Exceptions\Exception;
use Crest\Tests\Support\Adr\StubCandidateSource;
use PHPUnit\Framework\TestCase;

final class ConventionTest extends TestCase
{
    public function testEmptyPathIsPassedThroughAsRoot(): void
    {
        $source = new StubCandidateSource(['App\Action\Get']);

        (new Convention('App\Action', $source))->target('GET', '/');

        $this->assertSame(['App\Action', 'GET', '/'], $source->calls[0]);
    }

    public function testMethodIsUppercasedOnTheTarget(): void
    {
        $source = new StubCandidateSource(['App\Action\Company\PostCompany']);

        $target = (new Convention('App\Action', $source))->target('post', '/company');

        $this->assertSame('POST', $target->method);
    }

    public function testMultiplePlaceholdersAllBecomeAttributes(): void
    {
        $source = new StubCandidateSource(['App\Action\Company\GetCompany']);

        $target = (new Convention('App\Action', $source))
            ->target('GET', '/company/{id}/users/{userId}');

        $this->assertSame(['id', 'userId'], $target->attributes);
        $this->assertSame(['App\Action', 'GET', '/company'], $source->calls[0]);
    }

    public function testOnlyTheStaticPrefixReachesTheSource(): void
    {
        $source = new StubCandidateSource(['App\Action\Company\GetCompany']);

        (new Convention('App\Action', $source))->target('GET', '/company/{id}');

        $this->assertSame(['App\Action', 'GET', '/company'], $source->calls[0]);
    }

    public function testTargetSplitsTheFirstCandidateIntoNamespaceClassAndPath(): void
    {
        $source = new StubCandidateSource([
            'App\Action\Company\GetCompanyAll',
            'App\Action\Company\All\GetAll',
        ]);

        $target = (new Convention('App\Action', $source))->target('GET', '/company/all');

        $this->assertSame('App\Action\Company\GetCompanyAll', $target->fqcn);
        $this->assertSame('App\Action\Company', $target->namespace);
        $this->assertSame('GetCompanyAll', $target->class);
        $this->assertSame('Company/GetCompanyAll.php', $target->relativePath);
        $this->assertSame([], $target->attributes);
    }

    public function testNoCandidatesIsAnError(): void
    {
        $source = new StubCandidateSource([]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("no action class could be derived for 'GET /company'");

        (new Convention('App\Action', $source))->target('GET', '/company');
    }

    public function testCandidatesPassesTheStaticPrefixThroughToo(): void
    {
        $source = new StubCandidateSource(['App\Action\Company\GetCompany']);

        $candidates = (new Convention('App\Action', $source))->candidates('GET', '/company/{id}');

        $this->assertSame(['App\Action\Company\GetCompany'], $candidates);
        $this->assertSame(['App\Action', 'GET', '/company'], $source->calls[0]);
    }

    public function testTrailingBackslashesOnTheBaseNamespaceAreIgnored(): void
    {
        // Router::setBaseNamespace() rtrims too; if the two disagreed the
        // relative path would lose or keep a leading separator.
        $source = new StubCandidateSource(['App\Action\Company\GetCompany']);

        $target = (new Convention('App\Action\\\\', $source))->target('GET', '/company');

        $this->assertSame('Company/GetCompany.php', $target->relativePath);
        $this->assertSame(['App\Action', 'GET', '/company'], $source->calls[0]);
    }

    public function testCandidateWithNoNamespaceYieldsAnEmptyNamespace(): void
    {
        $source = new StubCandidateSource(['Get']);

        $target = (new Convention('', $source))->target('GET', '/');

        $this->assertSame('', $target->namespace);
        $this->assertSame('Get', $target->class);
    }

    public function testTopLevelCandidateYieldsAFlatPath(): void
    {
        $source = new StubCandidateSource(['App\Action\Get']);

        $target = (new Convention('App\Action', $source))->target('GET', '/');

        $this->assertSame('Get.php', $target->relativePath);
        $this->assertSame('App\Action', $target->namespace);
    }
}
