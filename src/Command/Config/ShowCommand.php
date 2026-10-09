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
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Project\Config;
use Crest\Project\Locator;

use function ksort;

/**
 * The configuration crest resolved for this project.
 *
 * Printing the values without saying where each came from would answer the
 * easy half of the question: the useful part is knowing which of them the
 * project actually asked for, and which took a default.
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

        $output->table(
            ['ITEM', 'VALUE', 'ORIGIN'],
            [
                ['root', $config->root(), Locator::FILENAME],
                ['flavor', $config->flavor()->value, $this->origin($config, 'flavor')],
                ['namespace', $config->namespace(), $this->origin($config, 'namespace')],
            ]
        );

        $paths = $config->paths();
        ksort($paths);

        $rows = [];
        foreach ($paths as $key => $path) {
            $rows[] = [$key, $path, $this->origin($config, 'paths.' . $key)];
        }

        $output->line();
        $output->table(['PATH', 'LOCATION', 'ORIGIN'], $rows);

        return 0;
    }

    private function origin(Config $config, string $key): string
    {
        return true === $config->isDeclared($key) ? self::DECLARED : self::DEFAULTED;
    }
}
