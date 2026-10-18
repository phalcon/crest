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

use function array_diff;
use function array_slice;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function sort;
use function sprintf;
use function str_starts_with;
use function strtolower;

use const SORT_STRING;

/**
 * The tables that make:model --all writes (#52, spec 3.2): the tables of the
 * database, with no views, no internal tables of SQLite, and no tables of
 * --exclude. Each table gets its class here, before crest writes a file. A
 * table that gives no class, or that gives the class of another table,
 * fails: crest writes the other tables, and lists the failed tables at the
 * end.
 */
final class ModelTables
{
    private const INTERNAL = 'Note: %s is a SQLite table; --all writes no models for it';

    private const VIEW = 'Note: %s is a view; --all writes no models for views';

    /**
     * @param array<string, string> $classes The table and its class, for each table that crest writes
     * @param array<string, string> $failed  The table and the reason, for each table that fails the
     *                                       class check
     * @param list<string>          $notes   One line for each --exclude name that is a view or an SQLite
     *                                       table
     * @param list<string>          $names   The tables of $classes and $failed, sorted by name
     */
    private function __construct(
        public readonly array $classes,
        public readonly array $failed,
        public readonly array $notes,
        private readonly array $names,
    ) {
    }

    /**
     * The tables of the database for --all. A name of --exclude that the
     * database does not have stops crest before it writes a file: with a
     * typo, crest would write the table that the user wants to skip.
     *
     * @param list<string> $exclude The names of --exclude, without spaces
     */
    public static function of(AdapterInterface $adapter, array $exclude): self
    {
        $listed = $adapter->listTables();
        $views  = $adapter->listViews();
        $sqlite = 'sqlite' === $adapter->getType();
        $tables = [];

        foreach ($listed as $table) {
            // SQLite rejects a user table whose name starts with sqlite_, in
            // any case. On the other servers, it is a usual name.
            $internal = $sqlite && str_starts_with(strtolower($table), 'sqlite_');

            if (false === $internal && false === in_array($table, $views, true)) {
                $tables[] = $table;
            }
        }

        $notes   = [];
        $unknown = [];

        foreach (array_unique($exclude) as $name) {
            if (true === in_array($name, $tables, true)) {
                continue;
            }

            if (true === in_array($name, $views, true)) {
                $notes[] = sprintf(self::VIEW, $name);
            } elseif (true === in_array($name, $listed, true)) {
                // A listed name that is not a table and not a view is an
                // internal table of SQLite.
                $notes[] = sprintf(self::INTERNAL, $name);
            } else {
                $unknown[] = $name;
            }
        }

        if ([] !== $unknown) {
            throw new Exception(
                '--exclude: the database has no table ' . implode(', ', $unknown)
                . self::hint($unknown, [...$listed, ...$views])
            );
        }

        $tables = array_diff($tables, $exclude);

        sort($tables, SORT_STRING);

        [$classes, $failed] = self::classes($tables);

        return new self($classes, $failed, $notes, $tables);
    }

    /**
     * The class of each table (spec 3.2, step 4). Two or more tables with the
     * same class all fail, so the result does not depend on the order of the
     * database. PHP and the file systems of macOS and Windows ignore case, so
     * the check ignores case too.
     *
     * @param list<string> $tables Sorted by name
     *
     * @return array{array<string, string>, array<string, string>} The classes, and the failed tables
     */
    private static function classes(array $tables): array
    {
        $classes = [];
        $failed  = [];
        $same    = [];

        foreach ($tables as $table) {
            try {
                $classes[$table] = ClassName::fromTable($table);
            } catch (Exception $exception) {
                $failed[$table] = $exception->getMessage();

                continue;
            }

            $same[strtolower($classes[$table])][] = $table;
        }

        foreach ($same as $group) {
            if (1 === count($group)) {
                continue;
            }

            // The reason names each class: users and USERS give Users and
            // USERS, which PHP sees as the same class.
            $distinct = [];

            foreach ($group as $table) {
                $distinct[$classes[$table]] = $classes[$table];
            }

            $named  = array_values($distinct);
            $reason = 1 === count($named)
                ? sprintf('the tables %s give the same class %s', self::sentence($group), $named[0])
                : sprintf(
                    'the tables %s give the classes %s, which PHP sees as the same class',
                    self::sentence($group),
                    self::sentence($named)
                );

            foreach ($group as $table) {
                $failed[$table] = $reason;

                unset($classes[$table]);
            }
        }

        return [$classes, $failed];
    }

    /**
     * A hint for each --exclude name that differs from a name of the
     * database only in case (#52). MySQL on macOS and Windows, and PostgreSQL
     * for a name with no quotes, keep a name in another case.
     *
     * @param list<string>  $unknown The --exclude names that the database does not have
     * @param array<string> $names   The tables and the views of the database
     */
    private static function hint(array $unknown, array $names): string
    {
        $lower = [];

        foreach ($names as $name) {
            $lower[strtolower($name)] = $name;
        }

        $hints = [];

        foreach ($unknown as $name) {
            if (true === isset($lower[strtolower($name)])) {
                $hints[] = $lower[strtolower($name)];
            }
        }

        return [] === $hints ? '' : '; did you mean ' . implode(', ', $hints) . '?';
    }

    /**
     * The names joined as in a sentence: "a, b and c".
     *
     * @param list<string> $names Two or more names
     */
    private static function sentence(array $names): string
    {
        return implode(', ', array_slice($names, 0, -1)) . ' and ' . $names[count($names) - 1];
    }

    /**
     * The tables of $classes and $failed, sorted by name (SORT_STRING).
     *
     * @return list<string>
     */
    public function names(): array
    {
        return $this->names;
    }
}
