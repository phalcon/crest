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

use Crest\Console\Exceptions\Exception;
use Phalcon\Db\ReferenceInterface;

use function array_map;
use function array_values;
use function count;
use function implode;
use function in_array;
use function ksort;
use function max;
use function min;
use function preg_match;
use function sort;
use function sprintf;
use function strtolower;

/**
 * The relations of a model that make:model writes (#51): a belongsTo() for
 * each foreign key of the table, and a hasMany() for each foreign key of a
 * table that points to this table.
 *
 * The related class is in the namespace of the `model` path, also when crest
 * did not write its model yet. crest gives no schema, so it reads the default
 * schema of the connection. SQLite gives one reference for each column of a
 * composite key, so there each column gives one relation. Identical keys give
 * one relation.
 *
 * The class and the property names come from ModelNames (#53): fields has the
 * property names of the table, referenced the property names of the related
 * table.
 *
 * A relation whose key has a RelationAlias attribute in the base keeps the
 * alias of that attribute (RelationAliases). A relation that crest cannot
 * write is not in `relations`. `skipped` has one line for it, with the
 * reason. The developer can give it an alias with an attribute, or add it in
 * the model. `dropped` has one line for each attribute that the next base
 * does not have.
 *
 * @phpstan-type Relation array{
 *     kind: string,
 *     fields: list<string>,
 *     class: string,
 *     referenced: list<string>,
 *     alias: string,
 *     key: string
 * }
 * @phpstan-type Draft array{relation: Relation, reason: string|null}
 * @phpstan-type Stored array<string, array{alias: string, line: int}>
 */
final class ModelRelations
{
    private const BELONGS_TO = 'belongs-to';

    private const HAS_MANY = 'has-many';

    /**
     * @param list<Relation> $relations belongsTo() first, then hasMany(), each sorted by alias
     * @param list<string>   $skipped   One line for each relation that crest skips, sorted
     * @param list<string>   $dropped   One line for each attribute that crest drops, sorted
     */
    private function __construct(
        public readonly array $relations,
        public readonly array $skipped,
        public readonly array $dropped,
    ) {
    }

    /**
     * The relations of the table. An alias of an attribute in the base that
     * crest cannot write stops crest, with the file and the line of the
     * attribute (spec 3.4).
     *
     * @param ForeignKeys  $keys       The foreign keys of the database, read one time (#52)
     * @param ModelNames   $names      The classes and the property names (#53)
     * @param list<string> $properties The property names of the table, for the alias check
     * @param Stored       $stored     The attributes in the base, by key
     * @param string       $file       The base, for the errors about the attributes
     */
    public static function of(
        ForeignKeys $keys,
        ModelNames $names,
        string $table,
        string $namespace,
        array $properties,
        array $stored,
        string $file
    ): self {
        $drafts = [];

        foreach (self::unique($keys->of($table)) as $reference) {
            $drafts[] = self::draft(
                self::BELONGS_TO,
                $table,
                $reference,
                $names,
                $namespace,
                $properties,
                $stored,
                $file,
                ''
            );
        }

        foreach ($keys->tables() as $other) {
            $references = [];

            foreach (self::unique($keys->of($other)) as $reference) {
                if ($table === $reference->getReferencedTable()) {
                    $references[] = $reference;
                }
            }

            foreach ($references as $reference) {
                // More than one key to this table: the alias of each key
                // makes the aliases different.
                $by = 1 < count($references) ? 'By' . self::keyAlias($reference) : '';

                $drafts[] = self::draft(
                    self::HAS_MANY,
                    $other,
                    $reference,
                    $names,
                    $namespace,
                    $properties,
                    $stored,
                    $file,
                    $by
                );
            }
        }

        return self::checked($drafts, $stored, $file);
    }

    /**
     * Check 3 of spec 3.3: two or more relations with the same alias are all
     * skipped, so the result does not depend on the order of the database.
     * An alias of an attribute wins over an alias from the rules, and two
     * aliases of attributes that are the same stop crest (spec 3.4). Phalcon
     * finds an alias without regard to case, so the check does too. Then the
     * relations and the lines are sorted.
     *
     * @param list<Draft> $drafts
     * @param Stored      $stored The attributes in the base, by key
     */
    private static function checked(array $drafts, array $stored, string $file): self
    {
        $count  = [];
        $listed = [];
        $seen   = [];

        foreach ($drafts as $draft) {
            $key        = $draft['relation']['key'];
            $seen[$key] = $key;

            if (null !== $draft['reason']) {
                continue;
            }

            $alias         = strtolower($draft['relation']['alias']);
            $count[$alias] = ($count[$alias] ?? 0) + 1;

            if (false === isset($stored[$key])) {
                continue;
            }

            if (true === isset($listed[$alias])) {
                throw new Exception(sprintf(
                    '%s, lines %d and %d: two attributes have the alias %s; change one of them',
                    $file,
                    min($listed[$alias], $stored[$key]['line']),
                    max($listed[$alias], $stored[$key]['line']),
                    $draft['relation']['alias']
                ));
            }

            $listed[$alias] = $stored[$key]['line'];
        }

        $relations = [];
        $skipped   = [];
        $written   = [];

        foreach ($drafts as $draft) {
            $alias  = $draft['relation']['alias'];
            $reason = $draft['reason'];

            // An alias of an attribute wins: crest skips only the relations
            // with an alias from the rules.
            if (
                null === $reason
                && false === isset($stored[$draft['relation']['key']])
                && 1 < $count[strtolower($alias)]
            ) {
                $reason = sprintf('two or more relations have the alias %s', $alias);
            }

            if (null === $reason) {
                // The alias is unique, so the key is unique. belongs-to
                // sorts before has-many: the two kinds start with different
                // letters, so they need no separator.
                $relations[$draft['relation']['kind'] . $alias] = $draft['relation'];
                $written[$draft['relation']['key']]             = $alias;

                continue;
            }

            $skipped[] = sprintf('Skipped the relation %s: %s', $draft['relation']['key'], $reason);
        }

        ksort($relations);
        sort($skipped);

        return new self(array_values($relations), $skipped, self::dropped($stored, $seen, $written));
    }

    /**
     * One relation, after checks 0 to 2 of spec 3.3. A key with an attribute
     * in the base gets the alias of that attribute (spec 3.4).
     *
     * @param list<string> $properties The property names of the table
     * @param Stored       $stored     The attributes in the base, by key
     *
     * @return Draft
     */
    private static function draft(
        string $kind,
        string $keyTable,
        ReferenceInterface $reference,
        ModelNames $names,
        string $namespace,
        array $properties,
        array $stored,
        string $file,
        string $by
    ): array {
        $belongsTo  = self::BELONGS_TO === $kind;
        $keys       = array_values($reference->getColumns());
        $referenced = array_values($reference->getReferencedColumns());
        $relation   = [
            'kind'       => $kind,
            'fields'     => $belongsTo ? $keys : $referenced,
            'class'      => '',
            'referenced' => $belongsTo ? $referenced : $keys,
            'alias'      => '',
            'key'        => sprintf(
                '%s %s(%s) -> %s(%s)',
                $belongsTo ? 'belongsTo' : 'hasMany',
                $keyTable,
                implode(', ', $keys),
                $reference->getReferencedTable(),
                implode(', ', $referenced)
            ),
        ];

        // SQLite accepts `REFERENCES customers` with no column. crest does not
        // find the primary key of the table itself.
        if (true === in_array(null, $referenced, true)) {
            return self::skip($relation, 'the key does not name the referenced columns');
        }

        // The class and the property names of both tables (#53). A table
        // that gives no class, a related base that crest cannot read, or a
        // column with no usable property name skips the relation.
        try {
            $class      = $names->classOf($belongsTo ? $reference->getReferencedTable() : $keyTable);
            $keyNames   = array_map(
                static fn (string $column): string => $names->fieldOf($keyTable, $column),
                $keys
            );
            $otherNames = array_map(
                static fn (string $column): string => $names->fieldOf($reference->getReferencedTable(), $column),
                $referenced
            );
        } catch (Exception $exception) {
            return self::skip($relation, $exception->getMessage());
        }

        $rules                  = $belongsTo ? self::keyAlias($reference) : $class . $by;
        $relation['fields']     = $belongsTo ? $keyNames : $otherNames;
        $relation['class']      = '\\' . $namespace . '\\' . $class;
        $relation['referenced'] = $belongsTo ? $otherNames : $keyNames;
        $relation['alias']      = $stored[$relation['key']]['alias'] ?? $rules;

        // Phalcon finds an alias without regard to case.
        if (false === in_array(strtolower($relation['alias']), array_map('strtolower', $properties), true)) {
            return ['relation' => $relation, 'reason' => null];
        }

        // A skip removes the relation from the base, and the code that uses
        // the alias stops working. So an alias of an attribute stops crest,
        // and the base does not change.
        if (true === isset($stored[$relation['key']])) {
            throw new Exception(sprintf(
                "%s, line %d: the alias %s of '%s' has the name of a column; change it",
                $file,
                $stored[$relation['key']]['line'],
                $relation['alias'],
                $relation['key']
            ));
        }

        return self::skip($relation, sprintf('the alias %s has the name of a column', $relation['alias']));
    }

    /**
     * One line for each attribute of the base that the next base does not
     * have (decision 8): the developer must know that the alias is gone.
     *
     * @param Stored                $stored  The attributes in the base, by key
     * @param array<string, string> $seen    The keys that the database gives
     * @param array<string, string> $written The keys of the relations in the next base
     *
     * @return list<string>
     */
    private static function dropped(array $stored, array $seen, array $written): array
    {
        $dropped = [];

        foreach ($stored as $key => $attribute) {
            if (true === isset($written[$key])) {
                continue;
            }

            $dropped[] = sprintf(
                "Dropped the alias %s of '%s': %s",
                $attribute['alias'],
                $key,
                true === isset($seen[$key]) ? 'crest skips the relation' : 'the database has no such key'
            );
        }

        sort($dropped);

        return $dropped;
    }

    /**
     * The alias of a belongsTo() (spec 3.2): the key column without `_id`, as
     * fromTable() joins a name (`customer_id` gives Customer). If the key has
     * more than one column, or the column gives no name, the alias is the
     * class of the referenced table. An alias is not a class name, so a
     * reserved word is a correct alias.
     */
    private static function keyAlias(ReferenceInterface $reference): string
    {
        $keys = array_values($reference->getColumns());

        if (1 === count($keys) && 1 === preg_match('/(.+)_id\z/i', $keys[0], $match)) {
            $alias = ClassName::studly($match[1]);

            if (1 === preg_match(ClassName::PATTERN, $alias)) {
                return $alias;
            }
        }

        return ClassName::studly($reference->getReferencedTable());
    }

    /**
     * A relation that crest skips, with the reason.
     *
     * @param Relation $relation
     *
     * @return Draft
     */
    private static function skip(array $relation, string $reason): array
    {
        return ['relation' => $relation, 'reason' => $reason];
    }

    /**
     * The references with no repeat: the database can have two foreign keys
     * with the same columns and the same target, and they are one relation
     * (decision 8). They are the same, so the last one stays.
     *
     * @param array<array-key, ReferenceInterface> $references
     *
     * @return array<string, ReferenceInterface>
     */
    private static function unique(array $references): array
    {
        $unique = [];

        foreach ($references as $reference) {
            $unique[sprintf(
                '%s -> %s(%s)',
                implode(', ', $reference->getColumns()),
                $reference->getReferencedTable(),
                implode(', ', $reference->getReferencedColumns())
            )] = $reference;
        }

        return $unique;
    }
}
