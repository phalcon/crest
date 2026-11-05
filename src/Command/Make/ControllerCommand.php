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
use Crest\Console\Parsing\Definition;
use Crest\Generator\ClassName;
use Crest\Generator\ControllerBase;
use Crest\Generator\ControllerRoute;
use Crest\Generator\ModuleLocation;
use Crest\Generator\Outcome;
use Crest\Generator\Placement;
use Crest\Project\Flavor;

use function dirname;
use function sprintf;

/**
 * Generates an MVC controller with the Generation Gap pattern.
 *
 * The controller extends the base that crest owns (ControllerBase), in its
 * subfolder of the `controller` path. `new` writes the same base for an MVC
 * project. crest writes the base again when its stub changes
 * (ArtifactWriter::renderOwned()), and writes the controller one time
 * (ArtifactWriter::renderOnce()). So a new version of the base stub reaches
 * each controller, and the code of the developer does not change. A second
 * run on an existing controller is the upgrade.
 *
 * The name must give a class that the dispatcher can find from a URL
 * (ControllerRoute), because no URL reaches a controller that it cannot find.
 *
 * With --module (#55), the controller and its base go into the Controllers
 * folder of the module, in the namespace of the module (ModuleLocation).
 * The module must exist: make:module writes it. Its Module.php sets the
 * namespace of the dispatcher, so the guidance is the stub
 * fragment-guidance-controller-module.
 *
 * handle() builds the values itself, and does not use replacements(): the
 * checks of the name in ControllerRoute need the name as the user typed it.
 */
final class ControllerCommand extends NamedArtifactCommand
{
    public function define(): Definition
    {
        return parent::define()->option('module=s', 'Write the controller into a module, e.g. admin');
    }

    public function handle(Input $input, Output $output): int
    {
        $config    = $this->config($input);
        $name      = $input->argumentString('name');
        $module    = $input->optionStringOrNull('module');
        $location  = null === $module ? null : ModuleLocation::existing($config, $module);
        $placement = null === $location
            ? $this->placement($config, $name, $this->key(), $this->suffix())
            : Placement::in(
                $location->folder(ModuleLocation::CONTROLLERS),
                $location->namespaceOf(ModuleLocation::CONTROLLERS),
                ClassName::suffixed($name, $this->suffix())
            );
        // The base goes into the folder of the controller, in the module or
        // in the `controller` path.
        $baseNamespace = ControllerBase::namespace($placement->namespace);
        $base          = ControllerBase::file(dirname($placement->file));
        $values        = [
            'base'          => ControllerBase::NAME,
            'baseNamespace' => $baseNamespace,
            'class'         => $placement->class,
            'namespace'     => $placement->namespace,
            'route'         => ControllerRoute::of($name, $placement->class),
        ];
        $writer        = $this->writer($config);

        // The base first: a controller never exists without its base.
        $this->reportOwned(
            $output,
            $writer->renderOwned(
                $base,
                'controller-base',
                ['class' => ControllerBase::NAME, 'namespace' => $baseNamespace]
            ),
            $base
        );

        $outcome = $writer->renderOnce($placement->file, $this->key(), $values, true === $input->option('force'));

        if (Outcome::Kept === $outcome) {
            $output->line(sprintf('Kept %s: it exists; pass --force to replace it', $placement->file));

            return 0;
        }

        $output->success(sprintf('Created %s', $placement->file));

        if (null === $location) {
            $this->guidance($config, $output, $values);

            return 0;
        }

        // Module.php sets the namespace, so the guidance of a module has no
        // line for the dispatcher.
        $this->guidance($config, $output, $values + ['module' => $location->key], 'module');

        return 0;
    }

    protected function description(): string
    {
        return 'Create an MVC controller';
    }

    protected function example(): string
    {
        return 'Users';
    }

    protected function flavors(): array
    {
        return [Flavor::MVC];
    }

    protected function key(): string
    {
        return 'controller';
    }

    protected function suffix(): string
    {
        return ControllerRoute::SUFFIX;
    }
}
