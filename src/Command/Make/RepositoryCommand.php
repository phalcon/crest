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
use Crest\Generator\Outcome;
use Crest\Generator\Placement;
use Crest\Generator\Stub;
use Crest\Generator\TableColumns;
use Crest\Project\Database;
use Crest\Project\Flavor;

use function count;
use function dirname;
use function sprintf;
use function var_export;

/**
 * Generates an ADR repository for a database table (#84), over the data
 * mapper (Phalcon\DataMapper), as the Phalcon sample vokuro-adr: an ADR
 * project has repositories, not the Active Record models of make:model.
 *
 * Two files, as make:model: the base in `<repository path>/Base`, which crest
 * owns and writes again when the table changes, and the repository that
 * extends it, which the developer owns. crest writes the repository one time;
 * --force replaces it. The base has the table, its primary key, its columns,
 * and find(), add(), update() and delete() by the primary key. The rows are
 * arrays.
 *
 * The checks that read only the files come first. Then crest boots the
 * project for the `db` service, and reads the table (TableColumns).
 */
final class RepositoryCommand extends ProjectCommand
{
    private const BASE_FOLDER = 'Base';

    private const BASE_SUFFIX = 'Base';

    private const SUFFIX = 'Repository';

    /**
     * One line for each column, for the COLUMNS constant of the base.
     *
     * @param list<string> $columns
     */
    private static function columns(array $columns): string
    {
        $lines = '';

        foreach ($columns as $column) {
            $lines .= '        ' . var_export($column, true) . ",\n";
        }

        return $lines;
    }

    /**
     * The one column of the primary key: find(), update() and delete() use it.
     *
     * @param list<string> $primaryKey
     */
    private static function key(string $table, array $primaryKey): string
    {
        if ([] === $primaryKey) {
            throw new Exception(sprintf("table '%s' has no primary key; make:repository needs one", $table));
        }

        if (1 < count($primaryKey)) {
            throw new Exception(
                sprintf("table '%s' has a composite primary key; make:repository needs one column", $table)
            );
        }

        return $primaryKey[0];
    }

    public function define(): Definition
    {
        return Definition::for('make:repository', 'Create an ADR repository for a database table')
            ->argument('table', true, 'Table name, e.g. users')
            // No declared default: resolveOptions() supplies false for a flag.
            ->option('force', 'Overwrite an existing repository');
    }

    public function handle(Input $input, Output $output): int
    {
        $table     = $input->argumentString('table');
        $config    = $this->config($input);
        $class     = ClassName::suffixed(ClassName::fromTable($table), self::SUFFIX);
        $placement = Placement::in($config->path('repository'), $config->namespaceFor('repository'), $class);
        $columns   = TableColumns::of(Database::connection($config), $table);
        $key       = self::key($table, $columns->primaryKey);

        $base          = $class . self::BASE_SUFFIX;
        $baseNamespace = $placement->namespace . '\\' . self::BASE_FOLDER;
        $baseFile      = dirname($placement->file) . '/' . self::BASE_FOLDER . '/' . $base . '.php';
        $writer        = $this->writer($config);

        $this->reportOwned(
            $output,
            $writer->renderOwned(
                $baseFile,
                'repository-base',
                [
                    'class'       => $base,
                    'columns'     => self::columns($columns->columns),
                    'namespace'   => $baseNamespace,
                    'quotedKey'   => var_export($key, true),
                    'quotedTable' => var_export($table, true),
                    'table'       => $table,
                ]
            ),
            $baseFile
        );

        $outcome = $writer->renderOnce(
            $placement->file,
            'repository',
            [
                'base'          => $base,
                'baseNamespace' => $baseNamespace,
                'class'         => $class,
                'namespace'     => $placement->namespace,
                'table'         => $table,
            ],
            true === $input->option('force')
        );

        if (Outcome::Kept === $outcome) {
            $output->line(sprintf('Kept %s: it exists; pass --force to replace it', $placement->file));

            return 0;
        }

        $output->success(sprintf('Created %s', $placement->file));
        $output->write(
            $this->stub($config)->render(
                $config->flavor()->value,
                Stub::FRAGMENT_PREFIX . 'guidance-repository',
                ['class' => $class, 'namespace' => $placement->namespace]
            )
        );

        return 0;
    }

    protected function flavors(): array
    {
        // ADR only: an MVC project has the models of make:model.
        return [Flavor::ADR];
    }
}
