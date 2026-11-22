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
use Crest\Generator\Project\DatabaseChoice;
use Crest\Generator\Project\DatabaseSetup;
use Crest\Project\Flavor;

/**
 * Adds a database to a project that exists (#95), as `crest new --database`
 * does for a new project. DatabaseSetup does the work: `crest init
 * --database` uses it too.
 */
final class DatabaseCommand extends ProjectCommand
{
    public function define(): Definition
    {
        return Definition::for('make:database', 'Add a database to the project')
            ->argument('driver', true, 'The database: mariadb, mysql, pgsql or sqlite')
            // No declared default: resolveOptions() supplies false for a flag.
            ->option('force', 'Overwrite an existing DbProvider');
    }

    public function handle(Input $input, Output $output): int
    {
        $database = DatabaseChoice::select($input->argumentString('driver'));
        $config   = $this->config($input);

        (new DatabaseSetup($config, $this->stub($config)))->add($database, $output, true === $input->option('force'));

        return 0;
    }

    protected function flavors(): array
    {
        // A DbProvider for each flavor, as `crest new --database`.
        return [Flavor::ADR, Flavor::MVC];
    }
}
