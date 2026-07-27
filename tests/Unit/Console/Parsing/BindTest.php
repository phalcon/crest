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

namespace Crest\Tests\Unit\Console\Parsing;

use Crest\Console\Parsing\Definition;
use Crest\Console\Parsing\Exceptions\Exception;
use PHPUnit\Framework\TestCase;

final class BindTest extends TestCase
{
    public function testDoubleDashSendsEverythingAfterItToArguments(): void
    {
        $bound = $this->definition()->bind(['GET', '--', '--force']);

        $this->assertSame('GET', $bound->argument('method'));
        $this->assertSame('--force', $bound->argument('path'));
        $this->assertFalse($bound->option('force'));
    }

    public function testFlagBeforeAPositionalDoesNotEatIt(): void
    {
        // The Cop regression this whole layer exists to prevent.
        $bound = $this->definition()->bind(['--force', 'GET', '/company/all']);

        $this->assertTrue($bound->option('force'));
        $this->assertSame('GET', $bound->argument('method'));
        $this->assertSame('/company/all', $bound->argument('path'));
    }

    public function testListOptionSplitsOnCommas(): void
    {
        $definition = Definition::for('make:model')->option('fields=l', 'Fields', []);

        $bound = $definition->bind(['--fields', 'id,name,email']);

        $this->assertSame(['id', 'name', 'email'], $bound->option('fields'));
    }

    public function testMissingRequiredArgumentThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("missing required argument 'path'");

        $this->definition()->bind(['GET']);
    }

    public function testOptionDefaultsAreAppliedWhenAbsent(): void
    {
        $bound = $this->definition()->bind(['GET', '/health']);

        $this->assertFalse($bound->option('force'));
        $this->assertSame('json', $bound->option('responder'));
    }

    public function testRequiredValueOptionWithNoValueThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("option '--responder' requires a value");

        $this->definition()->bind(['GET', '/health', '--responder']);
    }

    public function testShortFlagsClusterIntoSeparateBooleans(): void
    {
        $definition = Definition::for('serve')
            ->option('force|f', 'Force', false)
            ->option('quiet|q', 'Quiet', false);

        $bound = $definition->bind(['-fq']);

        $this->assertTrue($bound->option('force'));
        $this->assertTrue($bound->option('quiet'));
    }

    public function testTooManyArgumentsThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unexpected argument 'extra'");

        $this->definition()->bind(['GET', '/health', 'extra']);
    }

    public function testUnknownOptionThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown option '--frce'");

        $this->definition()->bind(['GET', '/health', '--frce']);
    }

    public function testValueMayBeAttachedWithAnEqualsSign(): void
    {
        $bound = $this->definition()->bind(['GET', '/health', '--responder=view']);

        $this->assertSame('view', $bound->option('responder'));
    }

    public function testValueMayBeTheFollowingToken(): void
    {
        $bound = $this->definition()->bind(['GET', '/health', '--responder', 'view']);

        $this->assertSame('view', $bound->option('responder'));
    }

    private function definition(): Definition
    {
        return Definition::for('make:action')
            ->argument('method', true, 'HTTP method')
            ->argument('path', true, 'Route path')
            ->option('force', 'Overwrite', false)
            ->option('responder=s', 'Responder', 'json');
    }
}
