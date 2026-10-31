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

namespace Crest\Command\Make;

use Crest\Command\ProjectCommand;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\BaseAttributes;
use Crest\Generator\ClassName;
use Crest\Generator\ForeignKeys;
use Crest\Generator\ModelNames;
use Crest\Generator\ModelProperties;
use Crest\Generator\ModelRelations;
use Crest\Generator\ModelTables;
use Crest\Generator\Outcome;
use Crest\Generator\PropertyNames;
use Crest\Generator\RelationAliases;
use Crest\Generator\Sentence;
use Crest\Generator\Stub;
use Crest\Project\Config;
use Crest\Project\Database;
use Crest\Project\Flavor;

use function array_diff;
use function array_filter;
use function array_keys;
use function array_map;
use function array_values;
use function count;
use function explode;
use function file_get_contents;
use function implode;
use function in_array;
use function is_file;
use function max;
use function sprintf;
use function str_contains;
use function str_pad;
use function strlen;
use function strrpos;
use function strtolower;
use function substr;
use function trim;
use function var_export;

/**
 * Generates an MVC model from a database table, with the Generation Gap
 * pattern (#46).
 *
 * crest reads the table through the `db` service of the project
 * (Database::adapter()), so crest and the model read the same database. The
 * base that crest owns (<Class>Base in the Base subfolder of the `model` path)
 * has a typed nullable property for each column, the name of the table, and
 * the relations from the foreign keys (ModelRelations, #51). Each relation
 * has a RelationAlias attribute on the base, and the developer can change its
 * alias there (RelationAliases). crest writes the base again when the table
 * or its keys change (ArtifactWriter::renderOwned()). The model extends the
 * base, and crest writes it one time (ArtifactWriter::renderOnce()). So
 * another run brings the model up to date with its table, and the code of the
 * developer does not change.
 *
 * The names (#53) come from ModelNames: the class of a table from
 * models.classes in crest.php, and the property of a column from --property,
 * a PropertyName attribute on the base, or models.camelCase. A property that
 * does not have the name of its column gives a columnMap() in the base. When
 * a property name changes, crest says so, and names the tables whose
 * relations use it. When a line of models.classes gives a table a new class,
 * the old model and base stay, and crest names them in a Note line (#71).
 *
 * The work for one table has two phases. The read phase reads the columns,
 * the base, the names and the relations, and changes no file. The write phase
 * writes the base and the model. --all (#52) writes each table of
 * ModelTables, and reads the foreign keys and the bases one time for the run.
 * A crest error in the read phase fails only its table: crest prints the
 * reason, goes to the next table, and lists the failed tables in the summary.
 *
 * Not NamedArtifactCommand: the argument is a table, there is no suffix, and
 * there is no guidance.
 *
 * @phpstan-type Parts array{
 *     attributes: array<string, string>,
 *     base: string,
 *     baseFile: string,
 *     baseNamespace: string,
 *     class: string,
 *     dropped: list<string>,
 *     file: string,
 *     map: array<string, string>,
 *     names: array<string, string>,
 *     namespace: string,
 *     note: string|null,
 *     properties: array<string, string>,
 *     relations: ModelRelations,
 *     renamed: array<string, array{string, string}>|null,
 *     run: list<string>,
 *     table: string
 * }
 */
final class ModelCommand extends ProjectCommand
{
    private const BASE_FOLDER = 'Base';

    private const BASE_SUFFIX = 'Base';

    // crest reads relations only from foreign keys, so `crest list` says so.
    private const DESCRIPTION = 'Create an MVC model from a database table, or from each table with --all '
        . '(relations come only from foreign keys)';

    private const KEY = 'model';

    private const NO_KEYS = 'No relations for %s: the table has no foreign keys, and no foreign keys point to it. '
        . 'Relations come only from foreign keys.';

    /**
     * The Note line for the old files of a table whose class now comes from
     * models.classes (#71): with only the old base, and with the old model
     * and the old base. The last %s is the end of the line.
     */
    private const OLD_FILE = 'Note: the old file of %s stays: %s; move your code to %s, delete it%s';

    private const OLD_FILES = 'Note: the old files of %s stay: %s and %s; move your code to %s, delete them%s';

    /**
     * The stubs that a base with a columnMap() needs, with the placeholder
     * of #53 in each one.
     */
    private const PLACEHOLDERS = [
        'model-base'                             => 'columnMap',
        Stub::FRAGMENT_PREFIX . 'model-property' => 'property',
    ];

    /**
     * The base of a class, in the Base subfolder of the `model` path.
     */
    private static function baseFile(string $path, string $class): string
    {
        return $path . '/' . self::BASE_FOLDER . '/' . $class . self::BASE_SUFFIX . '.php';
    }

    /**
     * Reads the base of each table one time, and gives its PropertyName
     * attributes to $names (spec 3.2). A table that gives no class has no
     * base. A base that crest cannot read fails each relation that needs its
     * names, and with --all its own table.
     *
     * @param list<string> $tables
     *
     * @return array{ModelNames, array<string, BaseAttributes|Exception>}
     */
    private static function bases(ModelNames $names, array $tables, string $path): array
    {
        $bases = [];

        foreach ($tables as $table) {
            try {
                $file = self::baseFile($path, $names->classOf($table));
            } catch (Exception) {
                continue;
            }

            try {
                $bases[$table] = BaseAttributes::read($file);
                $names         = $names->withStored($table, $bases[$table]->properties, $file);
            } catch (Exception $exception) {
                $bases[$table] = $exception;
                $names         = $names->withFailed($table, $exception->getMessage());
            }
        }

        return [$names, $bases];
    }

    /**
     * The class of the table, or null for a table that gives no class.
     */
    private static function classOrNull(ModelNames $names, string $table): ?string
    {
        try {
            return $names->classOf($table);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * The names of --property: column => name (spec 3.1). Each item is split
     * at its last `=`, and the spaces at the start and the end of each part
     * go, as for --exclude.
     *
     * @return array<string, string>
     */
    private static function given(string $value): array
    {
        $given = [];

        foreach (explode(',', $value) as $item) {
            $at     = strrpos($item, '=');
            $column = trim(false === $at ? $item : substr($item, 0, $at));
            $name   = false === $at ? '' : trim(substr($item, $at + 1));

            if ('' === $column) {
                throw new Exception('--property has an empty column name');
            }

            if ('' === $name) {
                throw new Exception(sprintf("--property: '%s' has no name; use column=name", $column));
            }

            if (true === isset($given[$column])) {
                throw new Exception(sprintf("--property: the column '%s' is in the list two times", $column));
            }

            $given[$column] = $name;
        }

        return $given;
    }

    /**
     * Whether a property does not have the name of its column: then the base
     * needs a columnMap() (spec 3.4).
     *
     * @param array<string, string> $names The column and its property
     */
    private static function mapped(array $names): bool
    {
        foreach ($names as $column => $name) {
            if ((string) $column !== $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * The Note line for the old files of the table (#71), or null. The class
     * of the table comes from models.classes, so crest writes a new model and
     * a new base, and the old files stay. There is a line only when:
     *
     * 1. the table gives a default class;
     * 2. no table gives the default class, without regard to case. The
     *    tables are the tables of models.classes and of the database, and
     *    ForeignKeys::read() puts the table itself in them. When the table
     *    itself gives the default class, there are no old files: PHP sees
     *    User and USER as one class, and a file system that ignores case
     *    sees one file. When another table gives it, the old base can be
     *    the base of that table;
     * 3. the old base exists and has ArtifactWriter::MARKER.
     *
     * The line also names the related tables that give a class and that this
     * run does not write: their bases have relations with the old class.
     *
     * @param list<string> $written The tables that this run writes
     */
    private static function oldFiles(
        ModelNames $names,
        ForeignKeys $keys,
        string $table,
        string $class,
        string $path,
        array $written
    ): ?string {
        try {
            $default = ClassName::fromTable($table);
        } catch (Exception) {
            return null;
        }

        foreach ([...$names->mapped(), ...$keys->tables()] as $other) {
            $found = self::classOrNull($names, $other);

            if (null !== $found && strtolower($found) === strtolower($default)) {
                return null;
            }
        }

        // Suppressed: a missing or unreadable old base gives no line. The
        // line is information, so it does not stop the command.
        $base     = self::baseFile($path, $default);
        $contents = @file_get_contents($base);

        if (false === $contents || false === str_contains($contents, ArtifactWriter::MARKER)) {
            return null;
        }

        $run = [];

        foreach (array_diff($keys->related($table), $written) as $other) {
            if (null !== self::classOrNull($names, $other)) {
                $run[] = $other;
            }
        }

        $then  = [] === $run ? '' : ', then run make:model for ' . Sentence::join($run);
        $model = $path . '/' . $default . '.php';

        return true === is_file($model)
            ? sprintf(self::OLD_FILES, $table, $model, $base, $class, $then)
            : sprintf(self::OLD_FILE, $table, $base, $class, $then);
    }

    /**
     * The keys of the array, as strings: PHP makes a numeric key an integer.
     *
     * @param array<array-key, mixed> $array
     *
     * @return list<string>
     */
    private static function strings(array $array): array
    {
        return array_map(static fn (int | string $key): string => (string) $key, array_keys($array));
    }

    public function define(): Definition
    {
        return Definition::for('make:model', self::DESCRIPTION)
            ->argument('table', false, 'Table name, e.g. users. Omit it with --all')
            // No declared default: resolveOptions() supplies false for a flag.
            ->option('all', 'Create a model for each table of the database')
            ->option('exclude=s', 'With --all: the tables to skip, separated by commas')
            ->option('force', 'Overwrite an existing model')
            ->option('property=s', 'For one table: property names, as column=name, separated by commas');
    }

    public function handle(Input $input, Output $output): int
    {
        $table    = $input->argumentString('table');
        $all      = true === $input->option('all');
        $exclude  = $input->optionStringOrNull('exclude');
        $property = $input->optionStringOrNull('property');

        // These checks read only the command line, so they come first.
        if ('' === $table && false === $all) {
            throw new Exception('give a table name, or --all');
        }

        if ('' !== $table && true === $all) {
            throw new Exception('give a table name or --all, not both');
        }

        if (null !== $exclude && false === $all) {
            throw new Exception('--exclude is only for --all');
        }

        if (null !== $property && true === $all) {
            throw new Exception('--property is only for one table, not --all');
        }

        $excluded = null === $exclude
            ? []
            : array_map(static fn (string $name): string => trim($name), explode(',', $exclude));

        if (true === in_array('', $excluded, true)) {
            throw new Exception('--exclude has an empty table name');
        }

        $given  = null === $property ? [] : self::given($property);
        $config = $this->config($input);
        // The models key of crest.php, before crest boots the project (#53).
        $names = ModelNames::of($config->models());
        $force = true === $input->option('force');

        if (true === $all) {
            return $this->all($config, $names, $excluded, $force, $output);
        }

        $this->one($config, $names->withGiven($table, $given), $table, $force, $output);

        return 0;
    }

    protected function flavors(): array
    {
        // MVC only: an ADR project uses repositories, not Active Record models.
        return [Flavor::MVC];
    }

    /**
     * make:model --all (#52, spec 3.4). A crest error in the read phase fails
     * only its table. An error in the write phase, or an error that is not a
     * crest error, is not about one table, so it stops the run.
     *
     * The Failed lines go to stdout with the other lines. With no terminal
     * (docker compose exec -T, a pipe), stdout and stderr are two streams, and
     * their lines can come in the wrong order.
     *
     * @param list<string> $exclude The names of --exclude, without spaces
     */
    private function all(Config $config, ModelNames $names, array $exclude, bool $force, Output $output): int
    {
        $path      = $config->path(self::KEY);
        $namespace = $config->namespaceFor(self::KEY);
        $adapter   = Database::adapter($config);
        $tables    = ModelTables::of($adapter, $exclude, $names);

        foreach ($tables->notes as $note) {
            $output->line($note);
        }

        if ([] === $tables->names()) {
            $output->line('No tables to write.');

            return 0;
        }

        $keys            = ForeignKeys::read($adapter);
        [$names, $bases] = self::bases($names, $keys->tables(), $path);
        $written         = self::strings($tables->classes);
        $created         = 0;
        $failed          = [];

        foreach ($tables->names() as $index => $table) {
            if (0 < $index) {
                $output->line();
            }

            $output->line('Table ' . $table);

            if (true === isset($tables->failed[$table])) {
                $failed[$table] = $tables->failed[$table];
                $output->line('Failed: ' . $failed[$table]);

                continue;
            }

            try {
                $base = $bases[$table];

                // A base that crest cannot read fails its table.
                if ($base instanceof Exception) {
                    throw $base;
                }

                $columns = ModelProperties::of($adapter, $table);
                $parts   = $this->read(
                    $config,
                    $names,
                    $keys,
                    $base,
                    $columns,
                    $names->properties($table, self::strings($columns)),
                    $table,
                    $tables->classes[$table],
                    $path,
                    $namespace,
                    $written
                );
            } catch (Exception $exception) {
                $failed[$table] = $exception->getMessage();
                $output->line('Failed: ' . $failed[$table]);

                continue;
            }

            if (Outcome::Kept !== $this->write($config, $parts, $force, $output)) {
                $created++;
            }
        }

        $this->summary($output, count($tables->names()), $created, $failed);

        return [] === $failed ? 0 : 1;
    }

    /**
     * A base with a columnMap() needs the placeholders of #53 in the stubs
     * that crest uses. A copy that the project published before #53 does not
     * have them, and crest would write a base with renamed properties and no
     * columnMap() (spec 3.4).
     */
    private function checkStubs(Config $config): void
    {
        $flavor = $config->flavor()->value;
        $stub   = $this->stub($config);

        foreach (self::PLACEHOLDERS as $name => $placeholder) {
            if (false === $stub->hasPlaceholder($flavor, $name, $placeholder)) {
                throw new Exception(sprintf(
                    'the published stub %s has no {{ %s }}; publish it again with crest stub:publish %s --force, '
                    . 'and make your changes in it again',
                    $stub->resolve($flavor, $name),
                    $placeholder,
                    $name
                ));
            }
        }
    }

    /**
     * The columnMap() of the base: '' when each property has the name of its
     * column. Else each column and its property, in the order of the table:
     * Phalcon needs each column in the map (spec 3.4).
     *
     * @param array<string, string> $map
     */
    private function columnMap(Config $config, array $map): string
    {
        if ([] === $map) {
            return '';
        }

        $width = 0;

        foreach (self::strings($map) as $column) {
            $width = max($width, strlen(var_export($column, true)));
        }

        $pairs = '';

        foreach ($map as $column => $name) {
            $pairs .= sprintf(
                "            %s => %s,\n",
                str_pad(var_export((string) $column, true), $width),
                var_export($name, true)
            );
        }

        return $this->stub($config)->render(
            $config->flavor()->value,
            Stub::FRAGMENT_PREFIX . 'model-column-map',
            ['pairs' => $pairs]
        );
    }

    /**
     * One quoted column name, or an array of names for a composite key.
     * var_export() escapes a quote in a name.
     *
     * @param list<string> $columns
     */
    private function names(array $columns): string
    {
        $quoted = array_map(static fn (string $column): string => var_export($column, true), $columns);

        if (1 === count($quoted)) {
            return $quoted[0];
        }

        return '[' . implode(', ', $quoted) . ']';
    }

    /**
     * make:model <table>: the output of #50 to #52, with the names of #53.
     * Each error stops the command.
     */
    private function one(Config $config, ModelNames $names, string $table, bool $force, Output $output): void
    {
        // These checks do not boot the project, so they come first: a table
        // that gives no class, or a `model` path that no psr-4 entry covers,
        // stops the command before crest boots the project.
        $class     = $names->classOf($table);
        $path      = $config->path(self::KEY);
        $namespace = $config->namespaceFor(self::KEY);
        $adapter   = Database::adapter($config);

        // The columns and the names before the keys: a table that does not
        // exist, a base that crest cannot read, or a column with no usable
        // property name stops the command before crest reads the keys of
        // each table.
        $columns  = ModelProperties::of($adapter, $table);
        $baseFile = self::baseFile($path, $class);
        $base     = BaseAttributes::read($baseFile);
        $names    = $names->withStored($table, $base->properties, $baseFile);
        $final    = $names->properties($table, self::strings($columns));
        $keys     = ForeignKeys::read($adapter, $table);

        // The relations need the property names of the related tables.
        [$names] = self::bases($names, $keys->related($table), $path);

        $this->write(
            $config,
            $this->read(
                $config,
                $names,
                $keys,
                $base,
                $columns,
                $final,
                $table,
                $class,
                $path,
                $namespace,
                [$table]
            ),
            $force,
            $output
        );
    }

    /**
     * One fragment-model-property for each column, in the order of the
     * table, with the property name of the column. A project can publish the
     * fragment and change it.
     *
     * @param array<string, string> $properties The column name and its PHP type
     * @param array<string, string> $names      The column name and its property
     */
    private function properties(Config $config, array $properties, array $names): string
    {
        $flavor = $config->flavor()->value;
        $stub   = $this->stub($config);
        $text   = '';

        foreach ($properties as $column => $type) {
            $text .= $stub->render(
                $flavor,
                Stub::FRAGMENT_PREFIX . 'model-property',
                ['column' => (string) $column, 'property' => $names[$column], 'type' => $type]
            );
        }

        return $text;
    }

    /**
     * The read phase of one table: the relations, the names and the lines
     * about them, after the columns, the base and the final names, which the
     * caller reads first. No file changes here, so an error leaves the files
     * of the table as they are.
     *
     * @param array<string, string> $columns The column name and its PHP type
     * @param array<string, string> $final   The column name and its property
     * @param list<string>          $written The tables that this run writes
     *
     * @return Parts
     */
    private function read(
        Config $config,
        ModelNames $names,
        ForeignKeys $keys,
        BaseAttributes $base,
        array $columns,
        array $final,
        string $table,
        string $class,
        string $path,
        string $namespace,
        array $written
    ): array {
        $baseFile = self::baseFile($path, $class);
        $list     = self::strings($columns);
        $mapped   = self::mapped($final);

        // An alias of an attribute that crest cannot write stops the read
        // phase, and no file changes.
        $relations = ModelRelations::of(
            $keys,
            $names,
            $table,
            $namespace,
            array_values($final),
            $base->aliases,
            $baseFile
        );

        if (true === $mapped) {
            $this->checkStubs($config);
        }

        $renamed = $base->renamed($final);

        return [
            'attributes'    => $names->attributes($table, $list),
            'base'          => $class . self::BASE_SUFFIX,
            'baseFile'      => $baseFile,
            'baseNamespace' => $namespace . '\\' . self::BASE_FOLDER,
            'class'         => $class,
            'dropped'       => $names->dropped($table, $list),
            'file'          => $path . '/' . $class . '.php',
            'map'           => true === $mapped ? $final : [],
            'names'         => $final,
            'namespace'     => $namespace,
            'note'          => self::oldFiles($names, $keys, $table, $class, $path, $written),
            'properties'    => $columns,
            'relations'     => $relations,
            'renamed'       => $renamed,
            // The tables whose relations use a renamed column, and that this
            // run does not write (decision 6). A table that gives no class has
            // no base to change.
            'run'           => array_values(array_filter(
                array_diff($keys->using($table, self::strings($renamed ?? [])), $written),
                static fn (string $other): bool => null !== self::classOrNull($names, $other)
            )),
            'table'         => $table,
        ];
    }

    /**
     * One fragment-model-belongs-to or fragment-model-has-many for each
     * relation, in the order of ModelRelations. A project can publish the
     * fragments and change them.
     */
    private function relations(Config $config, ModelRelations $relations): string
    {
        $flavor = $config->flavor()->value;
        $stub   = $this->stub($config);
        $text   = '';

        foreach ($relations->relations as $relation) {
            $text .= $stub->render(
                $flavor,
                Stub::FRAGMENT_PREFIX . 'model-' . $relation['kind'],
                [
                    'alias'      => $relation['alias'],
                    'class'      => $relation['class'],
                    'fields'     => $this->names($relation['fields']),
                    'referenced' => $this->names($relation['referenced']),
                ]
            );
        }

        return $text;
    }

    /**
     * The end of --all: the counts of the model files, then each failed
     * table with its reason. The output of a large database scrolls, so the
     * reasons come again here.
     *
     * @param array<string, string> $failed The table and the reason, in the order of the tables
     */
    private function summary(Output $output, int $tables, int $created, array $failed): void
    {
        $output->line();
        $output->line(
            sprintf(
                '%d %s: %d %s created, %d kept, %d failed.',
                $tables,
                1 === $tables ? 'table' : 'tables',
                $created,
                1 === $created ? 'model' : 'models',
                $tables - $created - count($failed),
                count($failed)
            )
        );

        if ([] === $failed) {
            return;
        }

        $output->line('Failed tables:');

        foreach ($failed as $table => $reason) {
            $output->line(sprintf('  %s: %s', $table, $reason));
        }
    }

    /**
     * The write phase of one table: the base, its lines, and the model. The
     * lines are the lines of make:model <table>.
     *
     * @param Parts $parts
     */
    private function write(Config $config, array $parts, bool $force, Output $output): Outcome
    {
        $writer    = $this->writer($config);
        $relations = $parts['relations'];

        // The base first: a model never exists without its base.
        $this->reportOwned(
            $output,
            $writer->renderOwned(
                $parts['baseFile'],
                'model-base',
                [
                    'aliases'    => PropertyNames::attributes($parts['attributes'])
                        . RelationAliases::attributes($relations->relations),
                    'aliasNote'  => PropertyNames::note($parts['attributes'])
                        . RelationAliases::note($relations->relations),
                    'class'      => $parts['base'],
                    'columnMap'  => $this->columnMap($config, $parts['map']),
                    'namespace'  => $parts['baseNamespace'],
                    'properties' => $this->properties($config, $parts['properties'], $parts['names']),
                    'relations'  => $this->relations($config, $relations),
                    'table'      => $parts['table'],
                ]
            ),
            $parts['baseFile']
        );

        // A property with another name breaks the code that uses it, so
        // crest says so (decision 6).
        if (null === $parts['renamed']) {
            $output->line(sprintf(
                'Note: crest cannot read columnMap() of %s, so it cannot list the renamed properties',
                $parts['baseFile']
            ));
        }

        foreach ($parts['renamed'] ?? [] as [$old, $new]) {
            $output->line(sprintf('Renamed $%s to $%s: change the code that uses it', $old, $new));
        }

        foreach ($parts['dropped'] as $line) {
            $output->line($line);
        }

        // A relation that crest cannot write is not in the base. The
        // developer can give it an alias with an attribute, or add it in the
        // model.
        foreach ($relations->skipped as $line) {
            $output->line($line);
        }

        // An attribute that the next base does not have is dropped, and the
        // developer must know that its alias is gone.
        foreach ($relations->dropped as $line) {
            $output->line($line);
        }

        // Each key gives a relation or a skipped line, so no relations and no
        // skipped lines means no keys. crest reads relations only from
        // foreign keys, and this line tells the user so.
        if ([] === $relations->relations && [] === $relations->skipped) {
            $output->line(sprintf(self::NO_KEYS, $parts['table']));
        }

        if ([] !== $parts['run']) {
            $output->line(sprintf(
                'Run make:model for %s too: %s relations use the property names of %s',
                Sentence::join($parts['run']),
                1 === count($parts['run']) ? 'its' : 'their',
                $parts['table']
            ));
        }

        $outcome = $writer->renderOnce(
            $parts['file'],
            self::KEY,
            [
                'base'          => $parts['base'],
                'baseNamespace' => $parts['baseNamespace'],
                'class'         => $parts['class'],
                'namespace'     => $parts['namespace'],
            ],
            $force
        );

        if (Outcome::Kept === $outcome) {
            $output->line(sprintf('Kept %s: it exists; pass --force to replace it', $parts['file']));
        } else {
            $output->success(sprintf('Created %s', $parts['file']));
        }

        // The old files of the table stay: the developer moves the code and
        // deletes them (#71).
        if (null !== $parts['note']) {
            $output->line($parts['note']);
        }

        return $outcome;
    }
}
