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

use Crest\Generator\PropertyNames;
use PHPUnit\Framework\TestCase;

/**
 * The PropertyName attributes that make:model writes into a base (#53, spec
 * 3.3).
 */
final class PropertyNamesTest extends TestCase
{
    public function testAttributesGivesALineForEachName(): void
    {
        $this->assertSame(
            "#[\\Crest\\Attribute\\PropertyName('first name', name: 'firstName')]\n"
            . "#[\\Crest\\Attribute\\PropertyName('USER_ID', name: 'userId')]\n",
            PropertyNames::attributes(['first name' => 'firstName', 'USER_ID' => 'userId'])
        );
    }

    public function testAttributesGivesNothingForNoNames(): void
    {
        $this->assertSame('', PropertyNames::attributes([]));
    }

    public function testAttributesQuotesTheColumn(): void
    {
        // var_export() escapes the quote and the backslash.
        $this->assertSame(
            "#[\\Crest\\Attribute\\PropertyName('it\\'s\\\\x', name: 'its')]\n",
            PropertyNames::attributes(["it's\\x" => 'its'])
        );
    }

    public function testAttributesWritesANumericColumnAsAString(): void
    {
        // PHP makes the key 2024 an integer. The attribute needs a string.
        $this->assertSame(
            "#[\\Crest\\Attribute\\PropertyName('2024', name: 'year')]\n",
            PropertyNames::attributes(['2024' => 'year'])
        );
    }

    public function testNoteGivesNothingForNoNames(): void
    {
        $this->assertSame('', PropertyNames::note([]));
    }

    public function testNoteGivesTheParagraph(): void
    {
        $this->assertSame(
            " *\n"
            . " * You can change a property name in a PropertyName attribute: crest keeps\n"
            . " * it when it writes this file again. Then change the code that uses the\n"
            . " * property.\n",
            PropertyNames::note(['first name' => 'firstName'])
        );
    }
}
