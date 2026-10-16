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

use function ucfirst;

final class ClassNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function reservedWords(): iterable
    {
        foreach (ClassName::RESERVED as $word) {
            yield $word => [$word];
        }
    }

    /**
     * The name, and what studly() gives. There is no check.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function studlyNames(): iterable
    {
        yield 'one word' => ['customer', 'Customer'];
        yield 'two parts' => ['billing_address', 'BillingAddress'];
        yield 'a reserved word' => ['list', 'List'];
        yield 'a part with a leading digit' => ['_2nd', '2nd'];
        yield 'an empty name' => ['', ''];
    }

    /**
     * The table name, and the class that it gives.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function tableNames(): iterable
    {
        yield 'one word' => ['users', 'Users'];
        yield 'two parts' => ['user_profiles', 'UserProfiles'];
        yield 'an uppercase first letter' => ['Users', 'Users'];
        yield 'all uppercase' => ['USERS', 'USERS'];
        yield 'a reserved word in a part' => ['list_items', 'ListItems'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unusableTableNames(): iterable
    {
        yield 'a hyphen' => ['user-profiles'];
        yield 'a leading digit' => ['2fa_codes'];
        yield 'a schema' => ['public.users'];
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

    public function testANamespacedNameIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "'Admin/Album' is not a usable class name; expected a single name like 'Album'"
        );

        ClassName::suffixed('Admin/Album', 'Responder');
    }

    public function testANamespaceLosesItsSurroundingBackslashes(): void
    {
        $this->assertSame('Acme\\Shop', ClassName::namespace('\\Acme\\Shop\\'));
    }

    public function testANamespaceMayHaveSeveralSegments(): void
    {
        $this->assertSame('Acme\\Shop', ClassName::namespace('Acme\\Shop'));
    }

    public function testANamespaceWithAHyphenIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "'my-app' is not a usable namespace; expected something like 'App' or 'Acme\\Shop'"
        );

        ClassName::namespace('my-app');
    }

    public function testANamespaceWithAnEmptySegmentIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'Acme\\\\Shop' is not a usable namespace");

        ClassName::namespace('Acme\\\\Shop');
    }

    public function testANamespaceWithATrailingNewlineIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'Acme\n' is not a usable namespace");

        ClassName::namespace("Acme\n");
    }

    public function testANameWithASpaceIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'My Responder' is not a usable class name");

        ClassName::suffixed('My Responder', 'Responder');
    }

    public function testANameWithATrailingNewlineIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'Album\n' is not a usable class name");

        ClassName::suffixed("Album\n", 'Responder');
    }

    public function testAnEmptyNameIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'' is not a usable class name");

        ClassName::suffixed('', 'Responder');
    }

    public function testAnEmptyNamespaceIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'' is not a usable namespace");

        ClassName::namespace('');
    }

    public function testANonLatinNameIsAccepted(): void
    {
        // PHP's own identifier rule allows the high-byte range, so a class named
        // in another script is a legal class - refusing it would be crest being
        // narrower than the language it generates for.
        $this->assertSame('ÜbergabeResponder', ClassName::suffixed('Übergabe', 'Responder'));
    }

    public function testAnUnderscoreNameIsAccepted(): void
    {
        $this->assertSame('Legacy_Responder', ClassName::suffixed('Legacy_', 'Responder'));
    }

    public function testFromTableIgnoresTheCaseOfAReservedName(): void
    {
        // PHP compares class names without regard to case.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'lIST' gives the class LIST, but PHP reserves that name");

        ClassName::fromTable('lIST');
    }

    /**
     * @dataProvider tableNames
     */
    public function testFromTableJoinsThePartsOfTheName(string $table, string $class): void
    {
        $this->assertSame($class, ClassName::fromTable($table));
    }

    /**
     * @dataProvider reservedWords
     */
    public function testFromTableStopsOnAReservedName(string $word): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "'" . $word . "' gives the class " . ucfirst($word) . ', but PHP reserves that name'
        );

        ClassName::fromTable($word);
    }

    /**
     * @dataProvider unusableTableNames
     */
    public function testFromTableStopsOnATableThatGivesNoClass(string $table): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "'" . $table . "' gives no usable class name; expected a table name like 'users' or 'user_profiles'"
        );

        ClassName::fromTable($table);
    }

    public function testMatchingIsCaseSensitive(): void
    {
        // 'middleware' is not the suffix 'Middleware', so it is appended. Exact
        // matching is what keeps the output predictable.
        $this->assertSame('CorsmiddlewareMiddleware', ClassName::suffixed('Corsmiddleware', 'Middleware'));
    }

    /**
     * @dataProvider studlyNames
     */
    public function testStudlyJoinsThePartsWithNoCheck(string $name, string $expected): void
    {
        $this->assertSame($expected, ClassName::studly($name));
    }

    public function testTheNameIsTakenVerbatimOtherwise(): void
    {
        // No case correction: the class written is the class that was asked for.
        $this->assertSame('albumResponder', ClassName::suffixed('album', 'Responder'));
    }

    public function testTheReservedListHasTheWordsOfPhp81(): void
    {
        // PHP 8.1 refuses 84 words as a class name. Three of them have an
        // underscore, so fromTable() cannot give them: __halt_compiler,
        // include_once and require_once.
        $this->assertCount(81, ClassName::RESERVED);
    }

    public function testTheSuffixIsAppendedWhenItIsAbsent(): void
    {
        $this->assertSame('CorsMiddleware', ClassName::suffixed('Cors', 'Middleware'));
    }
}
