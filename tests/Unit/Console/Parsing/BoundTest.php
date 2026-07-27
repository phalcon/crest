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

use Crest\Console\Parsing\Bound;
use PHPUnit\Framework\TestCase;

final class BoundTest extends TestCase
{
    public function testArgumentIsNullWhenNotBound(): void
    {
        $this->assertNull($this->bound()->argument('missing'));
    }

    public function testGetArgumentsReturnsTheWholeMap(): void
    {
        $this->assertSame(
            ['method' => 'GET', 'path' => '/health'],
            $this->bound()->getArguments()
        );
    }

    public function testGetOptionsReturnsEveryDeclaredOptionNotOnlySuppliedOnes(): void
    {
        $this->assertSame(
            ['responder' => 'json', 'force' => false],
            $this->bound()->getOptions()
        );
    }

    public function testHasOptionAnswersFromWhatWasSuppliedNotWhatWasDeclared(): void
    {
        $bound = $this->bound();

        // 'force' is declared and defaulted but was never passed; answering
        // from the options map would wrongly report it as supplied.
        $this->assertTrue($bound->hasOption('responder'));
        $this->assertFalse($bound->hasOption('force'));
    }

    public function testHasOptionIsFalseForAnUndeclaredName(): void
    {
        $this->assertFalse($this->bound()->hasOption('missing'));
    }

    public function testOptionIsNullWhenNotBound(): void
    {
        $this->assertNull($this->bound()->option('missing'));
    }

    private function bound(): Bound
    {
        return new Bound(
            ['method' => 'GET', 'path' => '/health'],
            ['responder' => 'json', 'force' => false],
            ['responder']
        );
    }
}
