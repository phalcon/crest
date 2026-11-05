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
use Crest\Generator\ControllerRoute;
use PHPUnit\Framework\TestCase;

final class ControllerRouteTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        yield 'one word' => ['UsersController', 'users'];
        yield 'two words' => ['UserProfilesController', 'user-profiles'];
        yield 'a word in capitals' => ['APIKeysController', 'a-p-i-keys'];
        yield 'a first letter that is not ASCII' => ['ÜnitsController', 'Ünits'];
    }

    /**
     * @dataProvider routes
     */
    public function testAClassGivesItsRoute(string $class, string $route): void
    {
        $this->assertSame($route, ControllerRoute::of('name', $class));
    }

    public function testAClassThatTheDispatcherCannotFindStops(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "'users' gives usersController, which the dispatcher cannot find from a URL; use a name like 'Users'"
        );

        ControllerRoute::of('users', 'usersController');
    }

    public function testASuffixTwoTimesStops(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "'Usercontroller' gives UsercontrollerController, with 'Controller' twice; "
            . "type the name without 'Controller'"
        );

        ControllerRoute::of('Usercontroller', 'UsercontrollerController');
    }
}
