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

namespace Crest\Generator\Project;

use Crest\Generator\ControllerBase;
use Crest\Generator\Stub;
use Crest\Project\Config;
use Crest\Project\Flavor;

/**
 * The MVC project of `new`: IndexController and its view answer GET /. The
 * controllers extend the base that crest owns (ControllerBase, #46). Two
 * providers set the dispatcher and the view.
 *
 * A stub that depends on the view engine has the engine name after the
 * prefix: project-<engine>-<part>. A view file has the engine name as its
 * extension.
 */
final class MvcSkeleton implements Skeleton
{
    /**
     * The controller that answers GET / with the default routes of the
     * router. GeneratedMvcProjectTest sends GET / through the generated
     * application to make sure of it.
     */
    private const SEED = 'IndexController';

    public function engines(): TemplateEngines
    {
        return new TemplateEngines($this->flavor(), ['phtml']);
    }

    public function entry(): string
    {
        return $this->path('controller') . '/' . self::SEED . '.php';
    }

    public function files(Answers $answers): array
    {
        $engine   = $answers->engine ?? $this->engines()->names()[0];
        $prefix   = Stub::PROJECT_PREFIX . $engine . '-';
        $provider = $this->path('provider');
        $views    = $this->path('views');

        return [
            Stub::PROJECT_PREFIX . 'provider-dispatcher' => $provider . '/DispatcherProvider.php',
            $prefix . 'provider'                         => $provider . '/ViewProvider.php',
            $prefix . 'layout'                           => $views . '/index.' . $engine,
            $prefix . 'index'                            => $views . '/index/index.' . $engine,
        ];
    }

    public function flavor(): Flavor
    {
        return Flavor::MVC;
    }

    /**
     * The base and the controller come from the stubs of make:controller.
     * The base is the same file as make:controller writes. Thus a later
     * make:controller finds it the same, or updates it.
     */
    public function seed(Stub $stub, Answers $answers): array
    {
        $namespace = $this->controllerNamespace($answers);
        $base      = ControllerBase::namespace($namespace);

        return [
            ControllerBase::file($this->path('controller')) => $stub->render(
                $this->flavor()->value,
                'controller-base',
                ['class' => ControllerBase::NAME, 'namespace' => $base]
            ),
            $this->entry() => $stub->render(
                $this->flavor()->value,
                'controller',
                [
                    'base'          => ControllerBase::NAME,
                    'baseNamespace' => $base,
                    'class'         => self::SEED,
                    'namespace'     => $namespace,
                ]
            ),
        ];
    }

    public function values(Answers $answers): array
    {
        return [
            'baseFolder'          => ControllerBase::FOLDER,
            'controllerNamespace' => $this->controllerNamespace($answers),
            'controllerPath'      => $this->path('controller'),
            'providerNamespace'   => $answers->namespace . '\\Providers',
            'providerPath'        => $this->path('provider'),
            'seed'                => self::SEED,
            'viewsPath'           => $this->path('views'),
        ];
    }

    private function controllerNamespace(Answers $answers): string
    {
        return $answers->namespace . '\\Controllers';
    }

    /**
     * A default path of the flavor, relative to the project root.
     */
    private function path(string $key): string
    {
        return Config::defaultPaths(Flavor::MVC)[$key];
    }
}
