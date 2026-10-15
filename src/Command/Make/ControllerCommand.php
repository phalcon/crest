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

use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Generator\ControllerBase;
use Crest\Generator\Outcome;
use Crest\Project\Flavor;

use function array_map;
use function implode;
use function preg_replace;
use function preg_split;
use function sprintf;
use function str_ends_with;
use function strlen;
use function strtolower;
use function substr;

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
 * (route()), because no URL reaches a controller that it cannot find.
 *
 * handle() builds the values itself, and does not use replacements(): the
 * checks of the name in route() need the name as the user typed it.
 */
final class ControllerCommand extends NamedArtifactCommand
{
    public function handle(Input $input, Output $output): int
    {
        $config        = $this->config($input);
        $name          = $input->argumentString('name');
        $placement     = $this->placement($config, $name, $this->key(), $this->suffix());
        $baseNamespace = ControllerBase::namespace($placement->namespace);
        $base          = ControllerBase::file($config->path($this->key()));
        $values        = [
            'base'          => ControllerBase::NAME,
            'baseNamespace' => $baseNamespace,
            'class'         => $placement->class,
            'namespace'     => $placement->namespace,
            'route'         => $this->route($name, $placement->class),
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

        $this->guidance($config, $output, $values);

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
        return 'Controller';
    }

    /**
     * The rule of the dispatcher (AbstractDispatcher::toCamelCase() in Phalcon
     * v5 and v6): split at `-` and `_`, make the first letter of each part
     * uppercase, and join the parts.
     */
    private function camelize(string $route): string
    {
        return implode('', array_map('ucfirst', preg_split('/[_-]+/', $route) ?: []));
    }

    /**
     * The URL segment of the controller with the default routes, after two
     * checks of the name. Both stop the command before it writes a file.
     *
     * The part of the class name before the suffix must not end in the
     * suffix, in any case: `Usercontroller` gives UsercontrollerController.
     *
     * The segment has `-` before each ASCII uppercase letter except the
     * first, and is in lowercase: UserProfilesController gives
     * `user-profiles`. The dispatcher must make the class name again from it
     * (camelize()). `users` gives usersController, but the URL /users looks
     * for UsersController.
     */
    private function route(string $name, string $class): string
    {
        $suffix = $this->suffix();
        $stem   = substr($class, 0, -strlen($suffix));

        if (true === str_ends_with(strtolower($stem), strtolower($suffix))) {
            throw new Exception(
                sprintf(
                    "'%s' gives %s, with '%s' twice; type the name without '%s'",
                    $name,
                    $class,
                    $suffix,
                    $suffix
                )
            );
        }

        $route = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $stem) ?? $stem);

        if ('' === $route || $stem !== $this->camelize($route)) {
            throw new Exception(
                sprintf(
                    "'%s' gives %s, which the dispatcher cannot find from a URL; use a name like 'Users'",
                    $name,
                    $class
                )
            );
        }

        return $route;
    }
}
