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

namespace Crest\Generator;

use Crest\Attribute\RelationAlias;

use function sprintf;
use function var_export;

/**
 * The RelationAlias attributes of a model base (#51). Each attribute names a
 * foreign key and its alias:
 *
 *     #[\Crest\Attribute\RelationAlias('belongsTo invoices(inv_cst_id) -> customers(cst_id)', alias: 'InvCst')]
 *
 * The developer can change the alias. make:model reads the attributes before
 * it writes the base again (BaseAttributes, #53), and keeps the aliases.
 *
 * @phpstan-import-type Relation from ModelRelations
 */
final class RelationAliases
{
    private const NOTE = " *\n"
        . " * You can change the alias in a RelationAlias attribute: crest keeps it when\n"
        . " * it writes this file again.\n";

    /**
     * One attribute line for each relation, in the order of the relations:
     * the key, quoted with var_export(), and the alias as a named argument.
     * '' for no relations. Each line ends with a newline, because the stub
     * has no newline after the placeholder.
     *
     * @param list<Relation> $relations
     */
    public static function attributes(array $relations): string
    {
        $text = '';

        foreach ($relations as $relation) {
            $text .= sprintf(
                "#[\\%s(%s, alias: '%s')]\n",
                RelationAlias::class,
                var_export($relation['key'], true),
                $relation['alias']
            );
        }

        return $text;
    }

    /**
     * The paragraph for the docblock of the base that tells the developer
     * about the attributes. '' for no relations, so a base with no relations
     * keeps the docblock of #50.
     *
     * @param list<Relation> $relations
     */
    public static function note(array $relations): string
    {
        return [] === $relations ? '' : self::NOTE;
    }
}
