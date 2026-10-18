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
use Crest\Generator\ClassName;
use Crest\Generator\ForeignKeys;
use Crest\Generator\ModelProperties;
use Crest\Generator\ModelRelations;
use Crest\Generator\ModelTables;
use Crest\Generator\Outcome;
use Crest\Generator\RelationAliases;
use Crest\Generator\Stub;
use Crest\Project\Config;
use Crest\Project\Database;
use Crest\Project\Flavor;

use function array_keys;
use function array_map;
use function count;
use function explode;
use function implode;
use function in_array;
use function sprintf;
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
 * The work for one table has two phases. The read phase reads the columns,
 * the base and the relations, and changes no file. The write phase writes
 * the base and the model. --all (#52) writes each table of ModelTables, and
 * reads the foreign keys one time for the run (ForeignKeys). A crest error in
 * the read phase fails only its table: crest prints the reason, goes to the
 * next table, and lists the failed tables in the summary.
 *
 * Not NamedArtifactCommand: the argument is a table, there is no suffix, and
 * there is no guidance.
 *
 * @phpstan-type Parts array{
 *     base: string,
 *     baseFile: string,
 *     baseNamespace: string,
 *     class: string,
 *     file: string,
 *     namespace: string,
 *     properties: array<string, string>,
 *     relations: ModelRelations,
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

    public function define(): Definition
    {
        return Definition::for('make:model', self::DESCRIPTION)
            ->argument('table', false, 'Table name, e.g. users. Omit it with --all')
            // No declared default: resolveOptions() supplies false for a flag.
            ->option('all', 'Create a model for each table of the database')
            ->option('exclude=s', 'With --all: the tables to skip, separated by commas')
            ->option('force', 'Overwrite an existing model');
    }

    public function handle(Input $input, Output $output): int
    {
        $table   = $input->argumentString('table');
        $all     = true === $input->option('all');
        $exclude = $input->optionStringOrNull('exclude');

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

        $names = null === $exclude
            ? []
            : array_map(static fn (string $name): string => trim($name), explode(',', $exclude));

        if (true === in_array('', $names, true)) {
            throw new Exception('--exclude has an empty table name');
        }

        $config = $this->config($input);
        $force  = true === $input->option('force');

        if (true === $all) {
            return $this->all($config, $names, $force, $output);
        }

        $this->one($config, $table, $force, $output);

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
    private function all(Config $config, array $exclude, bool $force, Output $output): int
    {
        $path      = $config->path(self::KEY);
        $namespace = $config->namespaceFor(self::KEY);
        $adapter   = Database::adapter($config);
        $tables    = ModelTables::of($adapter, $exclude);

        foreach ($tables->notes as $note) {
            $output->line($note);
        }

        if ([] === $tables->names()) {
            $output->line('No tables to write.');

            return 0;
        }

        $keys    = ForeignKeys::read($adapter);
        $created = 0;
        $failed  = [];

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
                $parts = $this->read(
                    $keys,
                    ModelProperties::of($adapter, $table),
                    $table,
                    $tables->classes[$table],
                    $path,
                    $namespace
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
     * make:model <table>: the output of #50 and #51. Each error stops the
     * command.
     */
    private function one(Config $config, string $table, bool $force, Output $output): void
    {
        // These checks do not boot the project, so they come first: a table
        // that gives no class, or a `model` path that no psr-4 entry covers,
        // stops the command before crest boots the project.
        $class     = ClassName::fromTable($table);
        $path      = $config->path(self::KEY);
        $namespace = $config->namespaceFor(self::KEY);
        $adapter   = Database::adapter($config);

        // The columns before the keys: a table that does not exist, or a
        // column that is not a property name, stops the command before crest
        // reads the keys of each table.
        $properties = ModelProperties::of($adapter, $table);
        $keys       = ForeignKeys::read($adapter, $table);
        $parts      = $this->read($keys, $properties, $table, $class, $path, $namespace);

        $this->write($config, $parts, $force, $output);
    }

    /**
     * One fragment-model-property for each column, in the order of the table.
     * A project can publish the fragment and change it.
     *
     * @param array<string, string> $properties The column name and its PHP type
     */
    private function properties(Config $config, array $properties): string
    {
        $flavor = $config->flavor()->value;
        $stub   = $this->stub($config);
        $text   = '';

        foreach ($properties as $column => $type) {
            $text .= $stub->render(
                $flavor,
                Stub::FRAGMENT_PREFIX . 'model-property',
                ['column' => $column, 'type' => $type]
            );
        }

        return $text;
    }

    /**
     * The read phase of one table: the attributes of the base and the
     * relations, after the columns (ModelProperties::of(), which the caller
     * reads first). No file changes here, so an error leaves the files of the
     * table as they are.
     *
     * @param array<string, string> $properties The column name and its PHP type
     *
     * @return Parts
     */
    private function read(
        ForeignKeys $keys,
        array $properties,
        string $table,
        string $class,
        string $path,
        string $namespace
    ): array {
        $base     = $class . self::BASE_SUFFIX;
        $baseFile = $path . '/' . self::BASE_FOLDER . '/' . $base . '.php';

        // The attributes of the base come before each write: a base or an
        // attribute that crest cannot read, or an alias that crest cannot
        // write, stops the read phase, and no file changes.
        $relations = ModelRelations::of(
            $keys,
            $table,
            $namespace,
            array_keys($properties),
            RelationAliases::read($baseFile),
            $baseFile
        );

        return [
            'base'          => $base,
            'baseFile'      => $baseFile,
            'baseNamespace' => $namespace . '\\' . self::BASE_FOLDER,
            'class'         => $class,
            'file'          => $path . '/' . $class . '.php',
            'namespace'     => $namespace,
            'properties'    => $properties,
            'relations'     => $relations,
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
                    'aliases'    => RelationAliases::attributes($relations->relations),
                    'aliasNote'  => RelationAliases::note($relations->relations),
                    'class'      => $parts['base'],
                    'namespace'  => $parts['baseNamespace'],
                    'properties' => $this->properties($config, $parts['properties']),
                    'relations'  => $this->relations($config, $relations),
                    'table'      => $parts['table'],
                ]
            ),
            $parts['baseFile']
        );

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

            return $outcome;
        }

        $output->success(sprintf('Created %s', $parts['file']));

        return $outcome;
    }
}
