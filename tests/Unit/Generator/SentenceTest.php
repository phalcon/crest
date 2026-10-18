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

use Crest\Generator\Sentence;
use PHPUnit\Framework\TestCase;

/**
 * Names joined as in a sentence, for the messages of make:model (#53).
 */
final class SentenceTest extends TestCase
{
    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function lists(): iterable
    {
        yield 'no names' => [[], ''];
        yield 'one name' => [['users'], 'users'];
        yield 'two names' => [['accounts', 'users'], 'accounts and users'];
        yield 'three names' => [['a', 'b', 'c'], 'a, b and c'];
    }

    /**
     * @param list<string> $names
     *
     * @dataProvider lists
     */
    public function testJoinGivesTheNamesAsInASentence(array $names, string $expected): void
    {
        $this->assertSame($expected, Sentence::join($names));
    }
}
