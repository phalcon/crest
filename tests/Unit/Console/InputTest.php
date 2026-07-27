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

use Crest\Console\Input;
use Crest\Console\Parsing\Bound;
use PHPUnit\Framework\TestCase;

final class InputTest extends TestCase
{
    public function testArgumentStringIsEmptyWhenAbsent(): void
    {
        $this->assertSame('', $this->input()->argumentString('missing'));
    }

    public function testArgumentStringReturnsABoundValue(): void
    {
        $this->assertSame('GET', $this->input()->argumentString('method'));
    }

    public function testNonStringValuesDoNotLeakThroughTheStringAccessors(): void
    {
        // A list option is a list<string>, not a string. The typed accessor
        // reports absence rather than handing back something uncastable.
        $input = $this->input();

        $this->assertSame('', $input->optionString('fields'));
        $this->assertNull($input->optionStringOrNull('fields'));
        $this->assertSame(['id', 'name'], $input->option('fields'));
    }

    public function testCommandNameIsExposed(): void
    {
        $this->assertSame('make:action', $this->input()->command);
    }

    public function testHasOptionDelegatesToWhatWasSupplied(): void
    {
        $input = $this->input();

        $this->assertTrue($input->hasOption('responder'));
        $this->assertFalse($input->hasOption('force'));
    }

    public function testOptionAndArgumentReturnRawValues(): void
    {
        $input = $this->input();

        $this->assertFalse($input->option('force'));
        $this->assertSame('/company/all', $input->argument('path'));
    }

    public function testOptionStringIsEmptyWhenAbsent(): void
    {
        $this->assertSame('', $this->input()->optionString('missing'));
    }

    public function testOptionStringOrNullIsNullWhenAbsent(): void
    {
        $this->assertNull($this->input()->optionStringOrNull('missing'));
    }

    public function testOptionStringOrNullReturnsABoundValue(): void
    {
        $this->assertSame('json', $this->input()->optionStringOrNull('responder'));
    }

    public function testOptionStringReturnsABoundValue(): void
    {
        $this->assertSame('json', $this->input()->optionString('responder'));
    }

    private function input(): Input
    {
        return new Input(
            'make:action',
            new Bound(
                ['method' => 'GET', 'path' => '/company/all'],
                ['responder' => 'json', 'force' => false, 'fields' => ['id', 'name']],
                ['responder']
            )
        );
    }
}
