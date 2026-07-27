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
use Crest\Console\Parsing\OptionMode;
use PHPUnit\Framework\TestCase;

final class DefinitionTest extends TestCase
{
    public function testBareSpecIsAFlag(): void
    {
        $definition = Definition::for('make:action')->option('force', 'Overwrite');

        $option = $definition->findOption('force');

        $this->assertNotNull($option);
        $this->assertSame(OptionMode::None, $option->mode);
        $this->assertNull($option->short);
        $this->assertSame('Overwrite', $option->description);
    }

    public function testEmptyModeSuffixIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("option spec 'stub=' is missing a mode");

        Definition::for('make:action')->option('stub=', 'Stub');
    }

    public function testEqualsLSpecIsAList(): void
    {
        // A list option always defaults to an array, never null - callers
        // foreach over it without a guard.
        $definition = Definition::for('make:model')->option('fields=l', 'Fields', []);

        $this->assertSame(OptionMode::List, $definition->findOption('fields')?->mode);
        // No `?->` here: the assertSame above already narrowed the same
        // expression to non-null, and PHPStan rejects a redundant nullsafe.
        $this->assertSame([], $definition->findOption('fields')->default);
    }

    public function testEqualsSOptionalSpecIsOptional(): void
    {
        $definition = Definition::for('make:action')->option('output=s?', 'Output');

        $this->assertSame(OptionMode::Optional, $definition->findOption('output')?->mode);
    }

    public function testEqualsSSpecRequiresAValue(): void
    {
        $definition = Definition::for('make:action')->option('stub=s', 'Stub');

        $this->assertSame(OptionMode::Required, $definition->findOption('stub')?->mode);
    }

    public function testFindOptionResolvesAShortAlias(): void
    {
        $definition = Definition::for('about')->option('help|h', 'Help');

        $this->assertSame('help', $definition->findOption('h')?->name);
    }

    public function testMergeThrowsOnACollidingOptionName(): void
    {
        $globals = Definition::for('')->option('force', 'Global force');
        $command = Definition::for('make:action')->option('force', 'Command force');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("option '--force' is already declared");

        $command->merge($globals);
    }

    public function testMergeThrowsOnACollidingShortAlias(): void
    {
        $globals = Definition::for('')->option('help|h', 'Help');
        $command = Definition::for('make:action')->option('host|h', 'Host');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("option '-h' is already declared");

        $command->merge($globals);
    }

    public function testMergeKeepsBothSetsOfOptions(): void
    {
        $globals = Definition::for('')->option('trace', 'Trace');
        $command = Definition::for('make:action')->option('force', 'Force');

        $merged = $command->merge($globals);

        $this->assertNotNull($merged->findOption('trace'));
        $this->assertNotNull($merged->findOption('force'));
        $this->assertSame('make:action', $merged->getName());
    }

    public function testOptionalArgumentsMayNotPrecedeRequiredOnes(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("required argument 'path' cannot follow optional argument 'method'");

        Definition::for('make:action')
            ->argument('method', false)
            ->argument('path', true);
    }

    public function testDescriptionIsExposed(): void
    {
        $this->assertSame('Make it', Definition::for('make:action', 'Make it')->getDescription());
    }

    public function testUnknownModeSuffixIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown option mode '=x'");

        Definition::for('make:action')->option('stub=x', 'Stub');
    }

    public function testArgumentsAreOptionalUnlessAskedToBeRequired(): void
    {
        // No explicit second argument: the default must leave it optional, so
        // binding nothing is legal.
        $definition = Definition::for('greet')->argument('subject');

        $this->assertNull($definition->bind([])->argument('subject'));
    }

    public function testDuplicateOptionOnTheSameDefinitionIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("option '--force' is already declared");

        Definition::for('make:action')->option('force')->option('force');
    }

    public function testModeSuffixIsTakenWholeNotUpToTheSecondEquals(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("unknown option mode '=s=x'");

        Definition::for('make:action')->option('stub=s=x', 'Stub');
    }

    public function testShortAliasIsEverythingAfterTheFirstPipe(): void
    {
        $definition = Definition::for('about')->option('help|h|x', 'Help');

        $this->assertSame('help', $definition->findOption('h|x')?->name);
        $this->assertNull($definition->findOption('h'));
    }

    public function testUnknownOptionReturnsNull(): void
    {
        $definition = Definition::for('about');

        $this->assertNull($definition->findOption('nope'));
    }
}
