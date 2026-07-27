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

    public function testFlagWithAnAttachedValueIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("option '--force' takes no value");

        $this->definition()->bind(['GET', '/health', '--force=yes']);
    }

    public function testOptionalValueOptionFallsBackToItsDefaultWhenBare(): void
    {
        $definition = Definition::for('make:action')->option('output=s?', 'Output', 'stdout');

        $this->assertSame('stdout', $definition->bind(['--output'])->option('output'));
    }

    public function testOnlyTheFinalLetterOfAClusterConsumesTheNextToken(): void
    {
        $definition = Definition::for('serve')
            ->option('force|f', 'Force', false)
            ->option('output|o=s', 'Output', '');

        $bound = $definition->bind(['-fo', 'build']);

        // 'build' belongs to -o, the last letter; -f stays a bare flag.
        $this->assertTrue($bound->option('force'));
        $this->assertSame('build', $bound->option('output'));
    }

    public function testUnknownShortOptionThrows(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown option '-z'");

        $this->definition()->bind(['GET', '/health', '-z']);
    }

    public function testValueOptionDoesNotSwallowAFollowingOption(): void
    {
        $definition = Definition::for('make:action')
            ->option('output=s?', 'Output', 'stdout')
            ->option('force', 'Force', false);

        $bound = $definition->bind(['--output', '--force']);

        // '--force' looks like the next token but is an option, so --output
        // falls back to its default rather than eating it.
        $this->assertSame('stdout', $bound->option('output'));
        $this->assertTrue($bound->option('force'));
    }

    public function testOptionalArgumentFallsBackToItsDeclaredDefault(): void
    {
        $definition = Definition::for('greet')
            ->argument('subject', false, 'Who', 'world');

        $this->assertSame('world', $definition->bind([])->argument('subject'));
    }

    public function testSuppliedOptionsAreDistinguishedFromDefaulted(): void
    {
        $bound = $this->definition()->bind(['GET', '/health', '--force']);

        $this->assertTrue($bound->hasOption('force'));
        $this->assertFalse($bound->hasOption('responder'));
    }

    public function testALoneDashIsAPositionalNotAnOption(): void
    {
        // Conventionally '-' means stdin; it is one character, so the short
        // option branch must not claim it.
        $definition = Definition::for('cat')->argument('file', false);

        $this->assertSame('-', $definition->bind(['-'])->argument('file'));
    }

    public function testEveryTokenAfterTheDoubleDashIsKept(): void
    {
        $bound = $this->definition()->bind(['--', 'GET', '/health']);

        $this->assertSame('GET', $bound->argument('method'));
        $this->assertSame('/health', $bound->argument('path'));
    }

    public function testParsingContinuesAfterAShortOptionCluster(): void
    {
        $definition = Definition::for('make:action')
            ->argument('method', true)
            ->argument('path', true)
            ->option('force|f', 'Overwrite', false);

        $bound = $definition->bind(['-f', 'GET', '/health']);

        $this->assertTrue($bound->option('force'));
        $this->assertSame('GET', $bound->argument('method'));
        $this->assertSame('/health', $bound->argument('path'));
    }

    public function testAttachedValueMayItselfContainAnEqualsSign(): void
    {
        $bound = $this->definition()->bind(['GET', '/health', '--responder=a=b']);

        $this->assertSame('a=b', $bound->option('responder'));
    }

    public function testAnAttachedValueStopsTheOptionConsumingTheNextToken(): void
    {
        $bound = $this->definition()->bind(['--responder=view', 'GET', '/health']);

        $this->assertSame('view', $bound->option('responder'));
        $this->assertSame('GET', $bound->argument('method'));
        $this->assertSame('/health', $bound->argument('path'));
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
