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
 *
 * In a project with no views (#83), the module has no views folder, its
 * Module.php does not set the views (the stub module-no-views), and the
 * guidance keeps the implicit view off (fragment-guidance-module-no-views).
 */
final class ModuleCommand extends NamedArtifactCommand
{
    public function handle(Input $input, Output $output): int
    {
        $config   = $this->config($input);
        $location = ModuleLocation::of($config, $input->argumentString('name'));
        $views    = $config->hasViews();
        $parts    = [ModuleLocation::CONTROLLERS, ModuleLocation::MODELS];

        if (true === $views) {
            $parts[] = ModuleLocation::VIEWS;
        }

        $this->writer($config)->render(
            $location->file(),
            true === $views ? $this->key() : $this->key() . '-no-views',
            ['class' => ModuleLocation::NAME, 'module' => $location->key, 'namespace' => $location->namespace],
            true === $input->option('force')
        );

        $output->success('Created ' . $location->file());

        foreach ($parts as $part) {
            $folder = $location->folder($part);

            if (true === ArtifactWriter::directory($folder)) {
                $output->success('Created ' . $folder);
            }
        }

        $this->guidance(
            $config,
            $output,
            ['module' => $location->key, 'namespace' => $location->namespace],
            true === $views ? '' : 'no-views'
        );

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
