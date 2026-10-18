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

use Crest\Generator\ModelRelations;
use Crest\Generator\RelationAliases;
use PHPUnit\Framework\TestCase;

/**
 * The RelationAlias attribute lines and the docblock note of a model base
 * (#51, spec 3.4). BaseAttributesTest has the read tests (#53).
 *
 * @phpstan-import-type Relation from ModelRelations
 */
final class RelationAliasesTest extends TestCase
{
    private const KEY = 'belongsTo invoices(customer_id) -> customers(id)';

    private const PAYMENTS = 'hasMany payments(invoice_id) -> invoices(id)';

    public function testAttributesGivesALineForEachRelation(): void
    {
        $this->assertSame(
            "#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Customer')]\n"
            . "#[\\Crest\\Attribute\\RelationAlias('" . self::PAYMENTS . "', alias: 'Payments')]\n",
            RelationAliases::attributes(
                [$this->relation(self::KEY, 'Customer'), $this->relation(self::PAYMENTS, 'Payments')]
            )
        );
    }

    public function testAttributesGivesNothingForNoRelations(): void
    {
        // An empty value keeps the class line of #50.
        $this->assertSame('', RelationAliases::attributes([]));
    }

    public function testAttributesQuotesTheKey(): void
    {
        // var_export() escapes the quote and the backslash.
        $this->assertSame(
            "#[\\Crest\\Attribute\\RelationAlias('hasMany notes(it\\'s_id, a\\\\b) -> users(id)', alias: 'Notes')]\n",
            RelationAliases::attributes([$this->relation("hasMany notes(it's_id, a\\b) -> users(id)", 'Notes')])
        );
    }

    public function testNoteGivesNothingForNoRelations(): void
    {
        // An empty value keeps the docblock of #50.
        $this->assertSame('', RelationAliases::note([]));
    }

    public function testNoteGivesTheParagraph(): void
    {
        $this->assertSame(
            " *\n"
            . " * You can change the alias in a RelationAlias attribute: crest keeps it when\n"
            . " * it writes this file again.\n",
            RelationAliases::note([$this->relation(self::KEY, 'Customer')])
        );
    }

    /**
     * A relation in the form of ModelRelations. attributes() and note() use
     * only the key and the alias.
     *
     * @return Relation
     */
    private function relation(string $key, string $alias): array
    {
        return [
            'kind'       => 'belongs-to',
            'fields'     => ['customer_id'],
            'class'      => '\\App\\Models\\Customers',
            'referenced' => ['id'],
            'alias'      => $alias,
            'key'        => $key,
        ];
    }
}
