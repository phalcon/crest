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
use PHPUnit\Framework\TestCase;

final class ExtractTest extends TestCase
{
    public function testAClusterOfDeclaredLettersIsReadAsBindReadsIt(): void
    {
        $definition = Definition::for('')->option('quiet|q')->option('directory|d=s');

        $extracted = $definition->extract(['-qd', '/srv/app', 'rest']);

        $this->assertSame(['quiet' => true, 'directory' => '/srv/app'], $extracted->options);
        $this->assertSame(['rest'], $extracted->rest);
    }

    public function testAClusterWithAnUndeclaredLetterStaysWhole(): void
    {
        $definition = Definition::for('')->option('quiet|q')->option('directory|d=s');

        $extracted = $definition->extract(['-qx', 'value']);

        $this->assertSame([], $extracted->options);
        $this->assertSame(['-qx', 'value'], $extracted->rest);
    }

    public function testADeclaredOptionTakesTheNextTokenAsItsValue(): void
    {
        $extracted = $this->paths()->extract(['route:list', '--directory', '/srv/app']);

        $this->assertSame('/srv/app', $extracted->option('directory'));
        $this->assertSame(['route:list'], $extracted->rest);
    }

    public function testADeclaredOptionWithAnAttachedValueIsTakenOut(): void
    {
        $extracted = $this->paths()->extract(['route:list', '--directory=/srv/app', '--trace']);

        $this->assertSame(['directory' => '/srv/app'], $extracted->options);
        $this->assertSame(['route:list', '--trace'], $extracted->rest);
    }

    public function testAFlagWithAnAttachedValueDoesNotThrow(): void
    {
        $extracted = Definition::for('')->option('trace')->extract(['--trace=x']);

        $this->assertSame(['trace' => true], $extracted->options);
    }

    public function testAMissingValueAtTheEndIsNull(): void
    {
        $extracted = $this->paths()->extract(['route:list', '--config']);

        $this->assertSame(['config' => null], $extracted->options);
        $this->assertSame(['route:list'], $extracted->rest);
    }

    public function testAnAbsentOptionIsNull(): void
    {
        $this->assertNull($this->paths()->extract(['route:list'])->option('config'));
    }

    public function testAnEmptyAttachedValueIsAnEmptyString(): void
    {
        $this->assertSame('', $this->paths()->extract(['--config='])->option('config'));
    }

    public function testANextTokenThatStartsWithADashIsNotAValue(): void
    {
        $extracted = $this->paths()->extract(['route:list', '--directory', '--trace']);

        $this->assertNull($extracted->option('directory'));
        $this->assertSame(['directory' => null], $extracted->options);
        $this->assertSame(['route:list', '--trace'], $extracted->rest);
    }

    public function testAnOptionThatOnlyStartsLikeADeclaredOneStays(): void
    {
        $extracted = $this->paths()->extract(['route:list', '--configure=x']);

        $this->assertSame([], $extracted->options);
        $this->assertSame(['route:list', '--configure=x'], $extracted->rest);
    }

    public function testAnUndeclaredOptionAndItsValueStayInOrder(): void
    {
        $extracted = $this->paths()->extract(['make:action', '--responder', 'view', '--config=c.php', 'GET']);

        $this->assertSame(['config' => 'c.php'], $extracted->options);
        $this->assertSame(['make:action', '--responder', 'view', 'GET'], $extracted->rest);
    }

    public function testTheDoubleDashAndEachTokenAfterItStay(): void
    {
        $tokens = ['make:action', 'GET', '/x', '--', '--directory', 'y'];

        $extracted = $this->paths()->extract($tokens);

        $this->assertSame([], $extracted->options);
        $this->assertSame($tokens, $extracted->rest);
    }

    public function testTheLastValueWins(): void
    {
        $extracted = $this->paths()->extract(['x', '--directory=/a', '--directory', '/b']);

        $this->assertSame('/b', $extracted->option('directory'));
        $this->assertSame(['x'], $extracted->rest);
    }

    private function paths(): Definition
    {
        return Definition::for('')
            ->option('config=s')
            ->option('directory=s');
    }
}
