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
use Crest\Generator\ControllerBase;
use Crest\Generator\ControllerRoute;
use Crest\Generator\FormElements;
use Crest\Generator\ModelNames;
use Crest\Generator\ModelProperties;
use Crest\Generator\ModuleLocation;
use Crest\Generator\Outcome;
use Crest\Generator\Placement;
use Crest\Generator\Stub;
use Crest\Project\Config;
use Crest\Project\Database;
use Crest\Project\Flavor;
use Phalcon\Db\ColumnInterface;
use Phalcon\Mvc\Model;

use function array_filter;
use function array_values;
use function count;
use function dirname;
use function htmlspecialchars;
use function method_exists;
use function sprintf;
use function str_replace;
use function ucfirst;

/**
 * Generates the CRUD pages of one table for an MVC project (#56): a model, a
 * form, a controller and three views.
 *
 * crest owns the base of the model, the base of the form and the base of the
 * controller, and writes them again when they change. The developer owns the
 * model, the form, the controller and the views, and crest writes them one
 * time. --force replaces the form, the controller and the views. It does not
 * replace the model: make:model --force does that. So a second run after a new
 * column updates the two bases, and the pages show the column.
 *
 * Each check comes before the first write (spec 3.4), so a run that stops
 * leaves the project as it was. The checks that read only files come before
 * crest boots the project, and crest boots it one time.
 *
 * With --module, the files go into the module, in its namespace
 * (ModuleLocation). The module must exist: make:module writes it.
 */
final class ScaffoldCommand extends ProjectCommand
{
    private const BASE_FOLDER = 'Base';

    private const KEPT = 'Kept %s: it exists; pass --force to replace it';

    /**
     * The folder and the namespace of a kind of class: a path of the project,
     * or a folder of the module. A path that no psr-4 entry covers stops the
     * command.
     *
     * @return array{string, string}
     */
    private static function place(Config $config, ?ModuleLocation $module, string $key, string $part): array
    {
        if (null === $module) {
            return [$config->path($key), $config->namespaceFor($key)];
        }

        return [$module->folder($part), $module->namespaceOf($part)];
    }

    public function define(): Definition
    {
        return Definition::for('make:scaffold', 'Create MVC CRUD pages for a database table')
            ->argument('table', true, 'Table name, e.g. users')
            // No declared default: resolveOptions() supplies false for a flag.
            ->option('force', 'Overwrite the controller, the form and the views')
            ->option('module=s', 'Write the pages into a module, e.g. admin');
    }

    public function handle(Input $input, Output $output): int
    {
        $table  = $input->argumentString('table');
        $force  = true === $input->option('force');
        $config = $this->config($input);
        $value  = $input->optionStringOrNull('module');
        $module = null === $value ? null : ModuleLocation::existing($config, $value);
        $names  = ModelNames::of($config->models());
        $class  = $names->classOf($table);

        $route  = ControllerRoute::of($table, $class . ControllerRoute::SUFFIX);

        [$formFolder, $formNamespace]             = self::place($config, $module, 'form', ModuleLocation::FORMS);
        [$controllerFolder, $controllerNamespace] = self::place(
            $config,
            $module,
            'controller',
            ModuleLocation::CONTROLLERS
        );

        // The result is not necessary: this stops the command before the boot when
        // no psr-4 entry covers the path of the models.
        self::place($config, $module, 'model', ModuleLocation::MODELS);

        $adapter = Database::adapter($config);

        ModelProperties::of($adapter, $table);

        $columns = $adapter->describeColumns($table);

        $key    = $this->primaryKey($columns, $table);
        $models = new ModelCommand();
        $parts  = $models->readTable($adapter, $config, $module, $names, $table);

        $this->checkAccessors($parts['names'], $table);

        $elements = FormElements::of($columns, $parts['names']);

        if (0 === $elements->editCount) {
            throw new Exception(sprintf("table '%s' has no columns for a form", $table));
        }

        $models->writeTable($config, $parts, false, $output, 'make:model --force replaces it');

        $writer = $this->writer($config);
        $base   = $class . 'FormBase';
        $file   = $formFolder . '/' . self::BASE_FOLDER . '/' . $base . '.php';

        $this->reportOwned(
            $output,
            $writer->renderOwned(
                $file,
                'scaffold-form-base',
                [
                    'class'     => $base,
                    'command'   => 'make:scaffold ' . $table . (null === $module ? '' : ' --module=' . $module->key),
                    'elements'  => $elements->code,
                    'imports'   => $elements->imports,
                    'namespace' => $formNamespace . '\\' . self::BASE_FOLDER,
                ]
            ),
            $file
        );

        foreach ($elements->skipped as $column) {
            $output->line(sprintf('Skipped %s: a binary column has no field in the form', $column));
        }

        $this->report(
            $output,
            $writer->renderOnce(
                $formFolder . '/' . $class . 'Form.php',
                'scaffold-form',
                [
                    'base'          => $base,
                    'baseNamespace' => $formNamespace . '\\' . self::BASE_FOLDER,
                    'class'         => $class . 'Form',
                    'namespace'     => $formNamespace,
                ],
                $force
            ),
            $formFolder . '/' . $class . 'Form.php'
        );

        $placement = Placement::in($controllerFolder, $controllerNamespace, $class . ControllerRoute::SUFFIX);
        $url       = (null === $module ? '' : $module->key . '/') . $route;
        $values    = [
            'base'           => ControllerBase::NAME,
            'baseNamespace'  => ControllerBase::namespace($placement->namespace),
            'class'          => $placement->class,
            'form'           => $class . 'Form',
            'formNamespace'  => $formNamespace,
            'key'            => $parts['names'][$key->getName()],
            'keyLabel'       => htmlspecialchars(ucfirst(str_replace('_', ' ', $key->getName()))),
            'keyValue'       => 'int' === ModelProperties::type($key->getType()) ? '(int) $id' : 'rawurldecode($id)',
            'model'          => $parts['class'],
            'modelNamespace' => $parts['namespace'],
            'namespace'      => $placement->namespace,
            'route'          => $route,
            'table'          => $table,
            'url'            => $url,
        ];
        $controllerBase = ControllerBase::file(dirname($placement->file));

        // The base first: a controller never exists without its base.
        $this->reportOwned(
            $output,
            $writer->renderOwned(
                $controllerBase,
                'controller-base',
                ['class' => ControllerBase::NAME, 'namespace' => $values['baseNamespace']]
            ),
            $controllerBase
        );
        $this->report(
            $output,
            $writer->renderOnce($placement->file, 'scaffold-controller', $values, $force),
            $placement->file
        );

        $views = null === $module ? $config->path('views') : $module->folder(ModuleLocation::VIEWS);

        foreach (['index', 'new', 'edit'] as $view) {
            $file = $views . '/' . $route . '/' . $view . '.phtml';

            $this->report($output, $writer->renderOnce($file, 'scaffold-view-' . $view, $values, $force), $file);
        }

        $output->write(
            $this->stub($config)->render(
                $config->flavor()->value,
                Stub::FRAGMENT_PREFIX . 'guidance-scaffold' . (null === $module ? '' : '-module'),
                [
                    'module'    => null === $module ? '' : $module->key,
                    'namespace' => $placement->namespace,
                    'table'     => $table,
                    'url'       => $url,
                ]
            )
        );

        return 0;
    }

    protected function flavors(): array
    {
        return [Flavor::MVC];
    }

    /**
     * Form::bind() calls set<Name>() on the entity when it exists, and
     * Form::getValue() calls get<Name>(). So a column `source` calls
     * Model::setSource(), and each save fails. PHP finds a method without
     * regard to case, as Form::toCamelize() needs.
     *
     * @param array<string, string> $names The column and its property
     */
    private function checkAccessors(array $names, string $table): void
    {
        foreach ($names as $column => $property) {
            $name = ClassName::studly($property);

            if (
                true === method_exists(Model::class, 'get' . $name)
                || true === method_exists(Model::class, 'set' . $name)
            ) {
                throw new Exception(
                    sprintf(
                        "column '%s' gives the property '%s', and Phalcon\\Mvc\\Model has a method with that name; "
                        . 'give it another name with make:model %s --property=%s=<name>',
                        $column,
                        $property,
                        $table,
                        $column
                    )
                );
            }
        }
    }

    /**
     * The column of the primary key: the pages find a row by one column.
     *
     * @param array<ColumnInterface> $columns
     */
    private function primaryKey(array $columns, string $table): ColumnInterface
    {
        $keys = array_values(
            array_filter($columns, static fn (ColumnInterface $column): bool => $column->isPrimary())
        );

        if ([] === $keys) {
            throw new Exception(sprintf("table '%s' has no primary key; make:scaffold needs one", $table));
        }

        if (1 < count($keys)) {
            throw new Exception(
                sprintf("table '%s' has a composite primary key; make:scaffold needs one column", $table)
            );
        }

        return $keys[0];
    }

    /**
     * The line for a file that the developer owns.
     */
    private function report(Output $output, Outcome $outcome, string $file): void
    {
        if (Outcome::Kept === $outcome) {
            $output->line(sprintf(self::KEPT, $file));

            return;
        }

        $output->success(sprintf('Created %s', $file));
    }
}
