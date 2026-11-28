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
use Phalcon\Db\Adapter\AdapterInterface;
use Phalcon\Mvc\Model;

use function array_diff;
use function array_filter;
use function array_is_list;
use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function lcfirst;
use function method_exists;
use function preg_match;
use function property_exists;
use function sort;
use function sprintf;
use function str_contains;
use function strpos;
use function strtolower;
use function substr;
use function trim;

use const SORT_STRING;

/**
 * The names of make:model (#53): the class of a table, and the property of a
 * column of a table.
 *
 * - The class: the line of models.classes in crest.php, else
 *   ClassName::fromTable().
 * - The property, in this order: --property (only for the table of a run for
 *   one table), the PropertyName attribute in the base of the table, the
 *   camelCase rule when models.camelCase is true, the column.
 *
 * The camelCase rule is ClassName::studly() with a lowercase first letter:
 * first_name gives firstName. The other letters do not change.
 *
 * ModelCommand makes one ModelNames for each run, and gives it the names in
 * the bases (withStored()). ModelTables and ModelRelations get the names
 * here, so the relations use the property names of both tables.
 *
 * models.schema (#69, #86) is one schema or a list of schemas. The first is
 * the default schema. A name with no schema is a table of the default schema,
 * and a table of another schema is <schema>.<table> (name()). The reads get
 * the schema of a name through schemaOf(), and its table through tableOf().
 *
 * @phpstan-import-type Names from GeneratorTypes
 */
final class ModelNames
{
    /**
     * The keys of models in crest.php.
     */
    private const KEYS = ['classes', 'camelCase', 'getSet', 'schema'];

    private const MODEL = 'has the name of a property of ' . Model::class;

    /**
     * @param array<string, string>                   $classes models.classes: the table and its class
     * @param list<string>                            $schemas models.schema: the schemas, the first is the
     *                                                         default; empty for no key
     * @param bool                                    $getSet  models.getSet: a getter and a setter for each
     *                                                         property (#70)
     * @param array<string, Names>                    $stored  The PropertyName attributes of each base, by table
     * @param array<string, string>                   $files   The base of each table of $stored, for the errors
     * @param array<string, array<array-key, string>> $given   --property: the column and its name, by table
     * @param array<string, string>                   $failed  The reason, by table, for a base that crest cannot
     *                                                         read
     * @param array<string, list<string>>             $byClass The names of withTables(), by the class of each
     *                                                         name in lowercase (#86)
     */
    private function __construct(
        private readonly array $classes,
        private readonly bool $camelCase,
        private readonly array $schemas,
        private readonly bool $getSet,
        private readonly array $stored = [],
        private readonly array $files = [],
        private readonly array $given = [],
        private readonly array $failed = [],
        private readonly array $byClass = [],
    ) {
    }

    /**
     * Checks the models key of crest.php (spec 3.1), before crest boots the
     * project. Config gives the key as the file has it, so a bad key stops
     * only make:model.
     */
    public static function of(mixed $models): self
    {
        if (false === is_array($models)) {
            throw new Exception('crest.php: models must be an array');
        }

        foreach (array_keys($models) as $key) {
            if (false === in_array($key, self::KEYS, true)) {
                throw new Exception(
                    sprintf(
                        "crest.php: models has an unknown key '%s'; "
                        . 'the keys are classes, camelCase, getSet and schema',
                        $key
                    )
                );
            }
        }

        $classes   = $models['classes'] ?? [];
        $camelCase = $models['camelCase'] ?? false;

        if (false === is_array($classes)) {
            throw new Exception('crest.php: models.classes must be an array of table => class');
        }

        if (false === is_bool($camelCase)) {
            throw new Exception('crest.php: models.camelCase must be true or false');
        }

        // A null value counts as no value, as for camelCase (#70).
        $getSet = $models['getSet'] ?? false;

        if (false === is_bool($getSet)) {
            throw new Exception('crest.php: models.getSet must be true or false');
        }

        return new self(self::classes($classes), $camelCase, self::schemaList($models['schema'] ?? null), $getSet);
    }

    /**
     * Checks each line of models.classes: a usable class name that PHP does
     * not reserve (the rules of ClassName::fromTable()), and no class for two
     * tables, without regard to case. A class that is the default class of
     * another table is found by the same-class check of --all (#52).
     *
     * @param array<mixed> $lines
     *
     * @return array<string, string>
     */
    private static function classes(array $lines): array
    {
        $classes = [];
        $same    = [];

        foreach ($lines as $table => $class) {
            $table = (string) $table;

            if (false === is_string($class)) {
                throw new Exception(sprintf("crest.php: models.classes: the class of '%s' must be a string", $table));
            }

            if (0 === preg_match(ClassName::PATTERN, $class)) {
                throw new Exception(sprintf(
                    "crest.php: models.classes: '%s' gives '%s', which is not a usable class name",
                    $table,
                    $class
                ));
            }

            if (true === in_array(strtolower($class), ClassName::RESERVED, true)) {
                throw new Exception(sprintf(
                    "crest.php: models.classes: '%s' gives the class %s, but PHP reserves that name",
                    $table,
                    $class
                ));
            }

            $classes[$table]            = $class;
            $same[strtolower($class)][] = $table;
        }

        foreach ($same as $tables) {
            if (1 === count($tables)) {
                continue;
            }

            sort($tables, SORT_STRING);

            // The message names each class: User and USER are two classes
            // that PHP sees as one.
            $distinct = [];

            foreach ($tables as $table) {
                $distinct[$classes[$table]] = $classes[$table];
            }

            $named = array_values($distinct);

            throw new Exception(
                1 === count($named)
                    ? sprintf(
                        'crest.php: models.classes: the tables %s give the same class %s',
                        Sentence::join($tables),
                        $named[0]
                    )
                    : sprintf(
                        'crest.php: models.classes: the tables %s give the classes %s, '
                        . 'which PHP sees as the same class',
                        Sentence::join($tables),
                        Sentence::join($named)
                    )
            );
        }

        return $classes;
    }

    /**
     * A hint for a --property column that differs from a column of the table
     * only in case, as the hint of --exclude.
     *
     * @param list<string> $columns
     */
    private static function hint(string $column, array $columns): string
    {
        foreach ($columns as $candidate) {
            if (strtolower($candidate) === strtolower($column)) {
                return '; did you mean ' . $candidate . '?';
            }
        }

        return '';
    }

    /**
     * Checks models.schema (#86): a name, or a list of names with no name two
     * times. A null value counts as no value. Crest keeps each name as the
     * file gives it. The compare of two names is without regard to case: two
     * names that differ only in case are a mistake.
     *
     * @return list<string>
     */
    private static function schemaList(mixed $schema): array
    {
        if (null === $schema) {
            return [];
        }

        if (true === is_string($schema) && '' !== trim($schema)) {
            return [$schema];
        }

        if (false === is_array($schema) || false === array_is_list($schema)) {
            throw new Exception('crest.php: models.schema must be the name of a schema, or a list of names');
        }

        if ([] === $schema) {
            throw new Exception('crest.php: models.schema is an empty list; give one schema or more');
        }

        $seen = [];

        foreach ($schema as $index => $entry) {
            if (false === is_string($entry) || '' === trim($entry)) {
                throw new Exception(
                    sprintf('crest.php: models.schema: entry %d must be the name of a schema', $index + 1)
                );
            }

            $lower = strtolower($entry);

            if (true === in_array($lower, $seen, true)) {
                throw new Exception(sprintf("crest.php: models.schema has the schema '%s' two times", $entry));
            }

            $seen[] = $lower;
        }

        return $schema;
    }

    /**
     * A property name that the base can declare: an identifier, and not a
     * property of the Phalcon model. A typed property with the name of a
     * property of the model is a fatal error, also when that property is
     * protected. property_exists() reads the Phalcon of the project.
     */
    private static function usable(string $name): bool
    {
        return 1 === preg_match(ClassName::PATTERN, $name) && false === property_exists(Model::class, $name);
    }

    /**
     * The PropertyName attributes of the next base of the table, column =>
     * name, in the order of the columns: each name of --property, and each
     * attribute of the base whose column the table still has (spec 3.3).
     *
     * @param list<string> $columns
     *
     * @return array<string, string>
     */
    public function attributes(string $table, array $columns): array
    {
        $attributes = [];

        foreach ($columns as $column) {
            $name = $this->given[$table][$column] ?? $this->stored[$table][$column]['name'] ?? null;

            if (null !== $name) {
                $attributes[$column] = $name;
            }
        }

        return $attributes;
    }

    /**
     * models.camelCase, for config:show (#71).
     */
    public function camelCase(): bool
    {
        return $this->camelCase;
    }

    /**
     * Stops when the name has a schema that models.schema does not have
     * (#86). The list gives the schemas of the project. The compare keeps the
     * case, because PostgreSQL keeps it.
     */
    public function checkName(string $name): void
    {
        $schema = $this->schemaOf($name);

        if (true === str_contains($name, '.') && false === in_array($schema, $this->schemas, true)) {
            throw new Exception(sprintf("the schema '%s' of '%s' is not in models.schema", $schema, $name));
        }
    }

    /**
     * Stops when models.schema has a value and the db service is SQLite,
     * which has no schemas (spec 3.4). The check needs the adapter, so it
     * runs after the boot.
     */
    public function checkSchema(AdapterInterface $adapter): void
    {
        if ([] !== $this->schemas && 'sqlite' === $adapter->getType()) {
            throw new Exception(sprintf(
                "crest.php: models.schema is '%s', but SQLite has no schemas; remove the key",
                implode(', ', $this->schemas)
            ));
        }
    }

    /**
     * The line of models.classes, else ClassName::fromTable() of the table
     * part of the name, which stops for a table that gives no usable class.
     *
     * A table of another schema with no line stops when another name of
     * withTables() gives the same class, without regard to case (#86). The
     * table of the default schema keeps the class, so the message names only
     * the name of the call. With no table of the default schema in the
     * group, one line fixes the clash, so the message names each name with a
     * schema.
     */
    public function classOf(string $table): string
    {
        if (true === isset($this->classes[$table])) {
            return $this->classes[$table];
        }

        $class = ClassName::fromTable($this->tableOf($table));

        if (false === str_contains($table, '.')) {
            return $class;
        }

        $group = $this->byClass[strtolower($class)] ?? [];

        if ([] === array_diff($group, [$table])) {
            return $class;
        }

        $group = array_unique([...$group, $table]);

        sort($group, SORT_STRING);

        $default = array_filter($group, static fn (string $name): bool => false === str_contains($name, '.'));
        $named   = [] === $default ? $group : [$table];

        throw new Exception(sprintf(
            'the tables %s give the class %s; add %s to models.classes',
            Sentence::join($group),
            $class,
            implode(' or ', array_map(static fn (string $name): string => "'" . $name . "'", $named))
        ));
    }

    /**
     * One line for each attribute of the base whose column the table no
     * longer has, sorted. crest drops the attribute (spec 3.3).
     *
     * @param list<string> $columns
     *
     * @return list<string>
     */
    public function dropped(string $table, array $columns): array
    {
        $dropped = [];

        foreach ($this->stored[$table] ?? [] as $column => $attribute) {
            if (false === in_array((string) $column, $columns, true)) {
                $dropped[] = sprintf(
                    "Dropped the property name %s of column '%s': the table has no such column",
                    $attribute['name'],
                    $column
                );
            }
        }

        sort($dropped);

        return $dropped;
    }

    /**
     * The property of a column for a relation (spec 3.4): propertyOf(), and
     * a stop for a name that is not usable. ModelRelations skips the relation
     * with the message.
     */
    public function fieldOf(string $table, string $column): string
    {
        $name = $this->propertyOf($table, $column);

        if (false === self::usable($name)) {
            throw new Exception(sprintf("column '%s' of table '%s' has no usable property name", $column, $table));
        }

        return $name;
    }

    /**
     * models.getSet: protected properties with a getter and a setter for
     * each (#70).
     */
    public function getSet(): bool
    {
        return $this->getSet;
    }

    /**
     * Two schemas or more (#86). Then the reads use getReferencedSchema() for
     * the schema of a key. One schema, or no key, gives the reads of #69.
     */
    public function isList(): bool
    {
        return 1 < count($this->schemas);
    }

    /**
     * The tables of models.classes, as strings: for the notes of --all
     * (ModelTables), for config:show, and for the old files of make:model
     * (#71).
     *
     * @return list<string>
     */
    public function mapped(): array
    {
        return array_map(static fn (int | string $table): string => (string) $table, array_keys($this->classes));
    }

    /**
     * The name of a table of a schema (#86): the table for the default
     * schema, else <schema>.<table>. A null schema is the default schema of
     * the connection.
     */
    public function name(?string $schema, string $table): string
    {
        return null === $schema || $schema === $this->schema() ? $table : $schema . '.' . $table;
    }

    /**
     * The final property of each column of the table that crest writes,
     * column => name, in the order of the columns, after the checks of spec
     * 3.2: each column of --property is a column of the table, each name is
     * usable, and two columns do not give one name. PHP property names are
     * case-sensitive, so the last check is too.
     *
     * @param list<string> $columns
     *
     * @return array<string, string>
     */
    public function properties(string $table, array $columns): array
    {
        foreach (array_keys($this->given[$table] ?? []) as $column) {
            // PHP makes a numeric column an integer key.
            $column = (string) $column;

            if (false === in_array($column, $columns, true)) {
                throw new Exception(sprintf(
                    "--property: table '%s' has no column '%s'%s",
                    $table,
                    $column,
                    self::hint($column, $columns)
                ));
            }
        }

        $properties = [];
        $owners     = [];

        foreach ($columns as $column) {
            $name = $this->propertyOf($table, $column);

            $this->check($table, $column, $name);

            if (true === isset($owners[$name])) {
                throw new Exception(sprintf(
                    "columns '%s' and '%s' of table '%s' give the same property %s; "
                    . 'give one of them another name with --property',
                    $owners[$name],
                    $column,
                    $table,
                    $name
                ));
            }

            $owners[$name]       = $column;
            $properties[$column] = $name;
        }

        if (true === $this->getSet) {
            $this->checkMethods($table, $properties);
        }

        return $properties;
    }

    /**
     * The property of one column, in the order of the class docblock. A
     * table whose base crest cannot read stops with the reason.
     */
    public function propertyOf(string $table, string $column): string
    {
        if (true === isset($this->failed[$table])) {
            throw new Exception($this->failed[$table]);
        }

        return $this->given[$table][$column] ?? $this->stored[$table][$column]['name'] ?? $this->rule($column);
    }

    /**
     * The default schema: the first schema of models.schema, or null for the
     * default schema of the connection.
     */
    public function schema(): ?string
    {
        return $this->schemas[0] ?? null;
    }

    /**
     * The schema of a name: the text before the first dot of
     * <schema>.<table>, else the default schema (#86).
     */
    public function schemaOf(string $name): ?string
    {
        $dot = strpos($name, '.');

        return false === $dot ? $this->schema() : substr($name, 0, $dot);
    }

    /**
     * The schemas of models.schema, in the order of the file. Empty for no
     * key.
     *
     * @return list<string>
     */
    public function schemas(): array
    {
        return $this->schemas;
    }

    /**
     * The table of a name: the text after the first dot of <schema>.<table>,
     * else the name.
     */
    public function tableOf(string $name): string
    {
        $dot = strpos($name, '.');

        return false === $dot ? $name : substr($name, $dot + 1);
    }

    /**
     * The table has a base that crest cannot read. Each name of the table
     * stops with the reason, so a relation that needs a name is skipped.
     */
    public function withFailed(string $table, string $reason): self
    {
        $failed         = $this->failed;
        $failed[$table] = $reason;

        return new self(
            $this->classes,
            $this->camelCase,
            $this->schemas,
            $this->getSet,
            $this->stored,
            $this->files,
            $this->given,
            $failed,
            $this->byClass
        );
    }

    /**
     * The names of --property for the table of a run for one table.
     *
     * @param array<array-key, string> $given The column and its name. PHP makes a numeric column an integer key.
     */
    public function withGiven(string $table, array $given): self
    {
        $all         = $this->given;
        $all[$table] = $given;

        return new self(
            $this->classes,
            $this->camelCase,
            $this->schemas,
            $this->getSet,
            $this->stored,
            $this->files,
            $all,
            $this->failed,
            $this->byClass
        );
    }

    /**
     * The PropertyName attributes of the base of the table (BaseAttributes),
     * and the base, for the errors about the attributes.
     *
     * @param Names $stored
     */
    public function withStored(string $table, array $stored, string $file): self
    {
        $all           = $this->stored;
        $files         = $this->files;
        $all[$table]   = $stored;
        $files[$table] = $file;

        return new self(
            $this->classes,
            $this->camelCase,
            $this->schemas,
            $this->getSet,
            $all,
            $files,
            $this->given,
            $this->failed,
            $this->byClass
        );
    }

    /**
     * The names of the tables of the database (#86): ModelTables and
     * ForeignKeys give them. classOf() uses them to find two tables that give
     * one class. A name that gives no class is not in the map: classOf()
     * stops for it.
     *
     * @param list<string> $names
     */
    public function withTables(array $names): self
    {
        $byClass = [];

        foreach ($names as $name) {
            try {
                $class = $this->classes[$name] ?? ClassName::fromTable($this->tableOf($name));
            } catch (Exception) {
                continue;
            }

            $byClass[strtolower($class)][] = $name;
        }

        return new self(
            $this->classes,
            $this->camelCase,
            $this->schemas,
            $this->getSet,
            $this->stored,
            $this->files,
            $this->given,
            $this->failed,
            $byClass
        );
    }

    /**
     * The checks of one final name (spec 3.2). Each error names the source
     * of the name: --property, an attribute, or the column and the rule.
     */
    private function check(string $table, string $column, string $name): void
    {
        if (true === self::usable($name)) {
            return;
        }

        $model = 1 === preg_match(ClassName::PATTERN, $name);

        if (true === isset($this->given[$table][$column])) {
            throw new Exception(sprintf(
                "--property: '%s' gives '%s', which %s",
                $column,
                $name,
                true === $model ? self::MODEL : 'is not a usable property name'
            ));
        }

        if (true === isset($this->stored[$table][$column])) {
            throw new Exception(sprintf(
                "%s, line %d: the property name '%s' %s; change it",
                $this->files[$table] ?? '',
                $this->stored[$table][$column]['line'],
                $name,
                true === $model ? self::MODEL : 'is not usable'
            ));
        }

        throw new Exception(sprintf(
            "column '%s' of table '%s'%s %s; give it %s with --property=\"%s=<name>\"",
            $column,
            $table,
            $name === $column ? '' : sprintf(" gives '%s' with camelCase, which", $name),
            true === $model ? self::MODEL : 'is not a usable property name',
            true === $model ? 'another name' : 'one',
            $column
        ));
    }

    /**
     * The checks of the getters and the setters (#70, spec 3.4), with the key
     * only. A method of Phalcon\Mvc\Model (conflict a) names its source, as
     * check() does. Two properties that give one method name (conflict b)
     * stop too: PHP method names are not case-sensitive.
     *
     * @param array<string, string> $properties The column and its property
     */
    private function checkMethods(string $table, array $properties): void
    {
        $owners = [];

        foreach ($properties as $column => $name) {
            $method = ClassName::studly($name);
            $taken  = true === method_exists(Model::class, 'get' . $method)
                ? 'get' . $method
                : (true === method_exists(Model::class, 'set' . $method) ? 'set' . $method : null);

            if (null !== $taken) {
                $has = sprintf('gives the method %s(), which %s has', $taken, Model::class);

                if (true === isset($this->given[$table][$column])) {
                    throw new Exception(sprintf("--property: '%s' %s", $name, $has));
                }

                if (true === isset($this->stored[$table][$column])) {
                    throw new Exception(sprintf(
                        "%s, line %d: the property name '%s' %s; change it",
                        $this->files[$table] ?? '',
                        $this->stored[$table][$column]['line'],
                        $name,
                        $has
                    ));
                }

                throw new Exception(sprintf(
                    "column '%s' of table '%s' %s; give it another name with --property=\"%s=<name>\"",
                    $column,
                    $table,
                    $has,
                    $column
                ));
            }

            $key = strtolower($method);

            if (true === isset($owners[$key])) {
                [$first, $firstMethod] = $owners[$key];

                throw new Exception(sprintf(
                    "columns '%s' and '%s' of table '%s' give the same methods get%s() and set%s(); "
                    . 'give one of them another name with --property',
                    $first,
                    $column,
                    $table,
                    $firstMethod,
                    $firstMethod
                ));
            }

            $owners[$key] = [$column, $method];
        }
    }

    /**
     * The name of a column with no --property and no attribute.
     */
    private function rule(string $column): string
    {
        return true === $this->camelCase ? lcfirst(ClassName::studly($column)) : $column;
    }
}
