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
use Crest\Generator\Outcome;
use Crest\Generator\Stub;
use Crest\Project\Config;
use Crest\Project\Database;
use Crest\Project\Flavor;

use function sprintf;

/**
 * Generates an MVC model from a database table, with the Generation Gap
 * pattern (#46).
 *
 * crest reads the table through the `db` service of the project
 * (Database::adapter()), so crest and the model read the same database. The
 * base that crest owns (<Class>Base in the Base subfolder of the `model` path)
 * has a typed nullable property for each column and the name of the table.
 * crest writes it again when the table changes (ArtifactWriter::renderOwned()).
 * The model extends the base, and crest writes it one time
 * (ArtifactWriter::renderOnce()). So another run brings the model up to date
 * with its table, and the code of the developer does not change.
 *
 * Not NamedArtifactCommand: the argument is a table, there is no suffix, and
 * there is no guidance.
 */
final class ModelCommand extends ProjectCommand
{
    private const BASE_FOLDER = 'Base';

    private const BASE_SUFFIX = 'Base';

    private const KEY = 'model';

    public function define(): Definition
    {
        return Definition::for('make:model', 'Create an MVC model from a database table')
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

        $properties    = ModelProperties::of(Database::adapter($config), $table);
        $base          = $class . self::BASE_SUFFIX;
        $baseNamespace = $namespace . '\\' . self::BASE_FOLDER;
        $baseFile      = $path . '/' . self::BASE_FOLDER . '/' . $base . '.php';
        $file          = $path . '/' . $class . '.php';
        $writer        = $this->writer($config);

        // The base first: a model never exists without its base.
        $this->reportOwned(
            $output,
            $writer->renderOwned(
                $baseFile,
                'model-base',
                [
                    'class'      => $base,
                    'namespace'  => $baseNamespace,
                    'properties' => $this->properties($config, $properties),
                    'table'      => $table,
                ]
            ),
            $baseFile
        );

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
}
