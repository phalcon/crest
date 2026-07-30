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

namespace Crest\Tests\Unit\Generator;

use Crest\Console\Exceptions\Exception;
use Crest\Generator\ClassName;
use PHPUnit\Framework\TestCase;

final class ClassNameTest extends TestCase
{
    public function testTheSuffixIsAppendedWhenItIsAbsent(): void
    {
        $this->assertSame('CorsMiddleware', ClassName::suffixed('Cors', 'Middleware'));
    }

    public function testAnAlreadySuffixedNameIsLeftAlone(): void
    {
        // The whole reason this exists: CorsMiddlewareMiddleware is what a naive
        // concatenation produces for a user who knows the convention.
        $this->assertSame('CorsMiddleware', ClassName::suffixed('CorsMiddleware', 'Middleware'));
    }

    public function testANameEqualToTheSuffixIsNotDoubled(): void
    {
        $this->assertSame('Middleware', ClassName::suffixed('Middleware', 'Middleware'));
    }

    public function testTheNameIsTakenVerbatimOtherwise(): void
    {
        // No case correction: the class written is the class that was asked for.
        $this->assertSame('albumResponder', ClassName::suffixed('album', 'Responder'));
    }

    public function testAnUnderscoreNameIsAccepted(): void
    {
        $this->assertSame('Legacy_Responder', ClassName::suffixed('Legacy_', 'Responder'));
    }

    public function testMatchingIsCaseSensitive(): void
    {
        // 'middleware' is not the suffix 'Middleware', so it is appended. Exact
        // matching is what keeps the output predictable.
        $this->assertSame('CorsmiddlewareMiddleware', ClassName::suffixed('Corsmiddleware', 'Middleware'));
    }

    public function testANamespacedNameIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "'Admin/Album' is not a usable class name; expected a single name like 'Album'"
        );

        ClassName::suffixed('Admin/Album', 'Responder');
    }

    public function testABackslashedNameIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'Admin\\Album' is not a usable class name");

        ClassName::suffixed('Admin\\Album', 'Responder');
    }

    public function testALeadingDigitIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'2Fast' is not a usable class name");

        ClassName::suffixed('2Fast', 'Responder');
    }

    public function testAnEmptyNameIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'' is not a usable class name");

        ClassName::suffixed('', 'Responder');
    }

    public function testANameWithASpaceIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'My Responder' is not a usable class name");

        ClassName::suffixed('My Responder', 'Responder');
    }
}
