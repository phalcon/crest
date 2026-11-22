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

namespace Crest\Command\Config;

use Crest\Command\ProjectCommand;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\ModelNames;
use Crest\Generator\Project\TemplateEngines;
use Crest\Project\Config;
use Crest\Project\Flavor;
use Crest\Project\Locator;

use function is_array;
use function ksort;
use function sort;
use function var_export;

use const SORT_STRING;

/**
 * The configuration crest resolved for this project.
 *
 * Printing the values without saying where each came from would answer the
 * easy half of the question: the useful part is knowing which of them the
 * project actually asked for, and which took a default.
 *
 * In an MVC project, the report also shows the models key (#71): camelCase
 * with its origin, the schema (#69) with its origin, and each line of
 * models.classes. A wrong key gives a row with the error, so the report never
 * stops on it.
 */
final class ShowCommand extends ProjectCommand
{
    private const DECLARED = 'declared';

    private const DEFAULTED = 'default';

    public function define(): Definition
    {
        return Definition::for('config:show', 'Show the resolved project configuration');
    }

    public function handle(Input $input, Output $output): int
    {
        $config = $this->config($input);

        $output->line('Source: ' . $config->source());
        $output->line();

        $runtime = $config->runtime();

        $items = [
            ['root', $config->root(), Locator::FILENAME],
            ['flavor', $config->flavor()->value, $this->origin($config, 'flavor')],
            ['namespace', $config->namespace(), $this->origin($config, 'namespace')],
            ['bootstrap', $config->bootstrap() ?? 'none', $this->origin($config, 'bootstrap')],
            ['runtime', $runtime->type, $this->origin($config, 'runtime')],
            ['service', $runtime->service, $this->origin($config, 'runtime.service')],
        ];

        $classes = [];

        if (Flavor::MVC === $config->flavor()) {
            [$models, $classes] = $this->models($config);

            foreach ($models as $row) {
                $items[] = $row;
            }

            // The view engine (#85): no key is phtml.
            $items[] = ['templateEngine', $config->templateEngine(), $this->origin($config, 'templateEngine')];
        }

        $output->table(['ITEM', 'VALUE', 'ORIGIN'], $items);

        $paths = $config->paths();

        // `'views' => false` (#83): the row says that the project has no views.
        if (Flavor::MVC === $config->flavor() && false === $config->hasViews()) {
            $paths['views'] = TemplateEngines::NONE;
        }

        ksort($paths);

        $rows = [];
        foreach ($paths as $key => $path) {
            $rows[] = [$key, $path, $this->origin($config, 'paths.' . $key)];
        }

        $output->line();
        $output->table(['PATH', 'LOCATION', 'ORIGIN'], $rows);

        if ([] !== $classes) {
            $output->line();
            $output->table(['TABLE', 'CLASS'], $classes);
        }

        return 0;
    }

    protected function flavors(): array
    {
        return Flavor::cases();
    }

    /**
     * The models key of an MVC project (#71): the camelCase row, the getSet
     * row (#70) and the schema row (#69), and a row for each line of
     * models.classes, sorted by table. The origins of camelCase and getSet
     * come from the key, because ModelNames does not keep them. A wrong key
     * gives one models row with the error, and no lines: make:model stops on
     * the error, but config:show does not.
     *
     * @return array{list<list<string>>, list<list<string>>}
     */
    private function models(Config $config): array
    {
        $models = $config->models();

        try {
            $names = ModelNames::of($models);
        } catch (Exception $exception) {
            return [[['models', $exception->getMessage(), self::DECLARED]], []];
        }

        // mapped() gives each table as a string: PHP makes a numeric table an
        // integer key.
        $tables = $names->mapped();
        sort($tables, SORT_STRING);

        $rows = [];

        foreach ($tables as $table) {
            $rows[] = [$table, $names->classOf($table)];
        }

        // A null value counts as no value, as in ModelNames::of() (#53, #70).
        $declared = static fn (string $key): bool => true === is_array($models) && true === isset($models[$key]);

        $schema = $names->schema();

        return [
            [
                [
                    'camelCase',
                    var_export($names->camelCase(), true),
                    true === $declared('camelCase') ? self::DECLARED : self::DEFAULTED,
                ],
                [
                    'getSet',
                    var_export($names->getSet(), true),
                    true === $declared('getSet') ? self::DECLARED : self::DEFAULTED,
                ],
                ['schema', $schema ?? 'none', null === $schema ? self::DEFAULTED : self::DECLARED],
            ],
            $rows,
        ];
    }

    private function origin(Config $config, string $key): string
    {
        return true === $config->isDeclared($key) ? self::DECLARED : self::DEFAULTED;
    }
}
