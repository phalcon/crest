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

use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\ModuleLocation;
use Crest\Project\Flavor;

/**
 * Generates an MVC module (#55): Module.php, which implements
 * Phalcon\Mvc\ModuleDefinitionInterface, and the folders Controllers, Models
 * and views. ModuleLocation holds the structure, and finds the folder of an
 * existing module from its key.
 *
 * The developer owns Module.php and adds the services of the module to it.
 * An existing Module.php stops the command, as #55 says, and --force
 * replaces only that file: the folders and their files stay.
 *
 * crest does not change the front controller or the router. It prints the
 * registration and the routes instead (the stub fragment-guidance-module).
 */
final class ModuleCommand extends NamedArtifactCommand
{
    public function handle(Input $input, Output $output): int
    {
        $config   = $this->config($input);
        $location = ModuleLocation::of($config, $input->argumentString('name'));

        $this->writer($config)->render(
            $location->file(),
            $this->key(),
            ['class' => ModuleLocation::NAME, 'module' => $location->key, 'namespace' => $location->namespace],
            true === $input->option('force')
        );

        $output->success('Created ' . $location->file());

        foreach ([ModuleLocation::CONTROLLERS, ModuleLocation::MODELS, ModuleLocation::VIEWS] as $part) {
            $folder = $location->folder($part);

            if (true === ArtifactWriter::directory($folder)) {
                $output->success('Created ' . $folder);
            }
        }

        $this->guidance($config, $output, ['module' => $location->key, 'namespace' => $location->namespace]);

        return 0;
    }

    protected function description(): string
    {
        return 'Create an MVC module';
    }

    protected function example(): string
    {
        return 'admin';
    }

    protected function flavors(): array
    {
        return [Flavor::MVC];
    }

    protected function key(): string
    {
        return 'module';
    }

    /**
     * Not used: handle() places the module itself, and the class is always
     * Module.
     */
    protected function suffix(): string
    {
        return '';
    }
}
