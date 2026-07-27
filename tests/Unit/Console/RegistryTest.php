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

namespace Crest\Tests\Unit\Console;

use Crest\Console\Exceptions\Exception;
use Crest\Console\Registry;
use Crest\Tests\Support\Console\FakeCommand;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testAliasesDoNotAppearInAll(): void
    {
        $registry = (new Registry())->add('fake', FakeCommand::class, 'faux', 'f');

        $this->assertSame(['fake' => FakeCommand::class], $registry->all());
    }

    public function testAliasesResolveToTheSameClass(): void
    {
        $registry = (new Registry())->add('fake', FakeCommand::class, 'faux', 'f');

        $this->assertSame(FakeCommand::class, $registry->get('fake'));
        $this->assertSame(FakeCommand::class, $registry->get('faux'));
        $this->assertSame(FakeCommand::class, $registry->get('f'));
    }

    public function testConstructorSeedsFromAMap(): void
    {
        $registry = new Registry(['fake' => FakeCommand::class]);

        $this->assertTrue($registry->has('fake'));
    }

    public function testGetThrowsForAnUnknownName(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown command 'nope'");

        (new Registry())->get('nope');
    }

    public function testGetStillThrowsAfterDiscoveryFindsNothing(): void
    {
        // A miss triggers the deferred scan; nothing in the test environment
        // contributes under this key, so the miss must still surface.
        $registry = (new Registry())
            ->add('fake', FakeCommand::class)
            ->withDiscovery('crest-registry-test-key');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown command 'nope'");

        $registry->get('nope');
    }

    public function testWithDiscoveryIsChainableAndSeededNamesStillResolve(): void
    {
        $registry = (new Registry())
            ->add('fake', FakeCommand::class)
            ->withDiscovery('crest-registry-test-key');

        $this->assertSame(FakeCommand::class, $registry->get('fake'));
    }

    public function testHasIsTrueForAnAlias(): void
    {
        $registry = (new Registry())->add('fake', FakeCommand::class, 'fk');

        $this->assertTrue($registry->has('fk'));
        $this->assertFalse($registry->has('nope'));
    }

    public function testStartsEmpty(): void
    {
        $this->assertSame([], (new Registry())->all());
    }
}
