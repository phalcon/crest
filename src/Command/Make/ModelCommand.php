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
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\ClassName;
use Crest\Generator\ModelProperties;
use Crest\Generator\ModelRelations;
use Crest\Generator\Outcome;
use Crest\Generator\RelationAliases;
use Crest\Generator\Stub;
use Crest\Project\Config;
use Crest\Project\Database;
use Crest\Project\Flavor;

use function array_keys;
use function array_map;
use function count;
use function implode;
use function sprintf;
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
 * Not NamedArtifactCommand: the argument is a table, there is no suffix, and
 * there is no guidance.
 */
final class ModelCommand extends ProjectCommand
{
    private const BASE_FOLDER = 'Base';

    private const BASE_SUFFIX = 'Base';

    // crest reads relations only from foreign keys, so `crest list` says so.
    private const DESCRIPTION = 'Create an MVC model from a database table (relations come only from foreign keys)';

    private const KEY = 'model';

    private const NO_KEYS = 'No relations for %s: the table has no foreign keys, and no foreign keys point to it. '
        . 'Relations come only from foreign keys.';

    public function define(): Definition
    {
        return Definition::for('make:model', self::DESCRIPTION)
            ->argument('table', true, 'Table name, e.g. users')
            // No declared default: resolveOptions() supplies false for a flag.
            ->option('force', 'Overwrite an existing model');
    }

    public function handle(Input $input, Output $output): int
    {
        $config = $this->config($input);
        $table  = $input->argumentString('table');

        // These checks do not boot the project, so they come first: a table
        // that gives no class, or a `model` path that no psr-4 entry covers,
        // stops the command before crest boots the project.
        $class     = ClassName::fromTable($table);
        $path      = $config->path(self::KEY);
        $namespace = $config->namespaceFor(self::KEY);

        $adapter       = Database::adapter($config);
        $properties    = ModelProperties::of($adapter, $table);
        $base          = $class . self::BASE_SUFFIX;
        $baseNamespace = $namespace . '\\' . self::BASE_FOLDER;
        $baseFile      = $path . '/' . self::BASE_FOLDER . '/' . $base . '.php';
        $file          = $path . '/' . $class . '.php';
        $writer        = $this->writer($config);

        // The attributes of the base come before each write: a base or an
        // attribute that crest cannot read, or an alias that crest cannot
        // write, stops the command, and no file changes.
        $relations = ModelRelations::of(
            $adapter,
            $table,
            $namespace,
            array_keys($properties),
            RelationAliases::read($baseFile),
            $baseFile
        );

        // The base first: a model never exists without its base.
        $this->reportOwned(
            $output,
            $writer->renderOwned(
                $baseFile,
                'model-base',
                [
                    'aliases'    => RelationAliases::attributes($relations->relations),
                    'aliasNote'  => RelationAliases::note($relations->relations),
                    'class'      => $base,
                    'namespace'  => $baseNamespace,
                    'properties' => $this->properties($config, $properties),
                    'relations'  => $this->relations($config, $relations),
                    'table'      => $table,
                ]
            ),
            $baseFile
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
            $output->line(sprintf(self::NO_KEYS, $table));
        }

        $outcome = $writer->renderOnce(
            $file,
            self::KEY,
            [
                'base'          => $base,
                'baseNamespace' => $baseNamespace,
                'class'         => $class,
                'namespace'     => $namespace,
            ],
            true === $input->option('force')
        );

        if (Outcome::Kept === $outcome) {
            $output->line(sprintf('Kept %s: it exists; pass --force to replace it', $file));

            return 0;
        }

        $output->success(sprintf('Created %s', $file));

        return 0;
    }

    protected function flavors(): array
    {
        // MVC only: an ADR project uses repositories, not Active Record models.
        return [Flavor::MVC];
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
}
