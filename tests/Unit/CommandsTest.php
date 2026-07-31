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

namespace Crest\Tests\Unit;

use Crest\Commands;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function sprintf;

final class CommandsTest extends TestCase
{
    public function testAliasesAreNotListedAsCommands(): void
    {
        $this->assertSame(
            [
                'about',
                'config:show',
                'container:list',
                'event:list',
                'list',
                'make:action',
                'make:command',
                'make:middleware',
                'make:provider',
                'make:responder',
                'route:list',
                'stub:publish',
            ],
            array_keys(Commands::registry()->all())
        );
    }
    public function testEveryRegisteredCommandNamesItselfConsistently(): void
    {
        foreach (Commands::registry()->all() as $name => $class) {
            $command = new $class();

            $this->assertSame(
                $name,
                $command->define()->getName(),
                sprintf(
                    '%s is registered as "%s" but names itself "%s"',
                    $class,
                    $name,
                    $command->define()->getName()
                )
            );
        }
    }

    public function testRegistryIsSeeded(): void
    {
        $this->assertNotSame([], Commands::registry()->all());
    }

    public function testRegistryResolvesTheAboutAliases(): void
    {
        $registry = Commands::registry();

        $this->assertTrue($registry->has('about'));
        $this->assertTrue($registry->has('info'));
        $this->assertTrue($registry->has('i'));
    }

    public function testRegistryResolvesTheListAliases(): void
    {
        // devtools offered all three spellings; retiring it means answering to
        // each of them.
        $registry = Commands::registry();

        $this->assertTrue($registry->has('list'));
        $this->assertTrue($registry->has('commands'));
        $this->assertTrue($registry->has('enumerate'));
    }
}
