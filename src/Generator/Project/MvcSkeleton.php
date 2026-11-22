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
 * controllers extend the base that crest owns (ControllerBase, #46). Three
 * providers set the dispatcher, the url service and the view. With a database,
 * DbProvider sets the db service that make:model reads (#54).
 *
 * A stub that depends on the view engine has the engine name after the
 * prefix: project-<engine>-<part>. A view file has the engine name as its
 * extension.
 *
 * With the engine none (#83), the project has no views: no view provider, no
 * layout and no view. The front turns the implicit view off, and
 * IndexController returns JSON (project-none-controller).
 *
 * With Volt (#85), Volt writes the compiled views into VOLT_CACHE. A
 * .gitignore in that folder keeps them out of git, and a v6 project requires
 * phalcon/volt: the phalcon/phalcon package has no Volt parser.
 */
final class MvcSkeleton implements Skeleton
{
    /**
     * The folder of the compiled Volt views, relative to the project root.
     */
    public const VOLT_CACHE = 'var/cache/volt';

    /**
     * The requirement of PHQL in composer.json, after the requirement of
     * Phalcon. A model finds rows with PHQL. The extension (v5) has it; the
     * phalcon/phalcon package (v6) does not (#50).
     */
    private const PHQL = ",\n        \"phalcon/phql\": \"1.0.x-dev\"";

    /**
     * The controller that answers GET / with the default routes of the
     * router. GeneratedMvcProjectTest sends GET / through the generated
     * application to make sure of it.
     */
    private const SEED = 'IndexController';

    /**
     * The requirement of the Volt parser in the composer.json of a v6
     * project with Volt, after the requirement of Phalcon.
     */
    private const VOLT = ",\n        \"phalcon/volt\": \"^1.0\"";

    public function engines(): TemplateEngines
    {
        return new TemplateEngines($this->flavor(), ['phtml', TemplateEngines::VOLT, TemplateEngines::NONE]);
    }

    public function entry(): string
    {
        return $this->path('controller') . '/' . self::SEED . '.php';
    }

    public function files(Answers $answers): array
    {
        $engine   = $this->engine($answers);
        $provider = $this->path('provider');
        $files    = [
            Stub::PROJECT_PREFIX . 'provider-dispatcher' => $provider . '/DispatcherProvider.php',
            Stub::PROJECT_PREFIX . 'provider-url'        => $provider . '/UrlProvider.php',
        ];

        if (TemplateEngines::NONE !== $engine) {
            $prefix = Stub::PROJECT_PREFIX . $engine . '-';
            $views  = $this->path('views');
            $files += [
                $prefix . 'provider' => $provider . '/ViewProvider.php',
                $prefix . 'layout'   => $views . '/index.' . $engine,
                $prefix . 'index'    => $views . '/index/index.' . $engine,
            ];
        }

        $database = $answers->database->provider();

        if (null !== $database) {
            $files[$database] = $provider . '/DbProvider.php';
        }

        return $files;
    }

    public function flavor(): Flavor
    {
        return Flavor::MVC;
    }

    /**
     * The base and the controller come from the stubs of make:controller.
     * The base is the same file as make:controller writes. Thus a later
     * make:controller finds it the same, or updates it.
     *
     * With Volt, the cache folder gets a .gitignore that keeps the compiled
     * views out of git, and keeps the folder itself.
     */
    public function seed(Stub $stub, Answers $answers): array
    {
        $namespace = $this->controllerNamespace($answers);
        $base      = ControllerBase::namespace($namespace);
        $cache     = TemplateEngines::VOLT === $this->engine($answers)
            ? [self::VOLT_CACHE . '/.gitignore' => "*\n!.gitignore\n"]
            : [];

        return $cache + [
            ControllerBase::file($this->path('controller')) => $stub->render(
                $this->flavor()->value,
                'controller-base',
                ['class' => ControllerBase::NAME, 'namespace' => $base]
            ),
            $this->entry() => $stub->render(
                $this->flavor()->value,
                TemplateEngines::NONE === $this->engine($answers)
                    ? Stub::PROJECT_PREFIX . TemplateEngines::NONE . '-controller'
                    : 'controller',
                [
                    'base'          => ControllerBase::NAME,
                    'baseNamespace' => $base,
                    'class'         => self::SEED,
                    'namespace'     => $namespace,
                ]
            ),
        ];
    }

    /**
     * The values of the views are whole lines, as dbUse and dbProvider. A
     * project with no views gets empty lines, and the front turns the
     * implicit view off: Phalcon\Mvc\Application otherwise needs a view
     * service. viewsPath stays for a published front from before #83.
     */
    public function values(Answers $answers): array
    {
        $none  = DatabaseChoice::NONE === $answers->database;
        $views = TemplateEngines::NONE !== $this->engine($answers);
        $volt  = TemplateEngines::VOLT === $this->engine($answers);

        return [
            'baseFolder'          => ControllerBase::FOLDER,
            'controllerNamespace' => $this->controllerNamespace($answers),
            'controllerPath'      => $this->path('controller'),
            'dbProvider'          => $this->dbProvider($answers->database),
            'dbUse'               => true === $none
                ? ''
                : 'use ' . $this->providerNamespace($answers) . "\\DbProvider;\n",
            'implicitView'        => true === $views ? '' : '->useImplicitView(false)',
            'phql'                => true === $none || 'v6' !== $answers->phalcon ? '' : self::PHQL,
            'providerNamespace'   => $this->providerNamespace($answers),
            'providerPath'        => $this->path('provider'),
            'seed'                => self::SEED,
            'viewProvider'        => true === $views ? $this->viewProvider($volt) : '',
            'viewUse'             => true === $views
                ? 'use ' . $this->providerNamespace($answers) . "\\ViewProvider;\n"
                : '',
            'viewsPath'           => $this->path('views'),
            'viewsRow'            => true === $views
                ? '| `' . $this->path('views') . '/` | The views. `GET /` shows the view `index/index` in the layout '
                    . "`index` |\n"
                : '',
            'volt'                => true === $volt && 'v6' === $answers->phalcon ? self::VOLT : '',
            'voltCache'           => self::VOLT_CACHE,
        ];
    }

    private function controllerNamespace(Answers $answers): string
    {
        return $answers->namespace . '\\Controllers';
    }

    /**
     * The line of DbProvider in providers() of the front, before
     * DispatcherProvider. The SQLite provider gets the project root, because
     * DB_NAME is relative to it.
     */
    private function dbProvider(DatabaseChoice $database): string
    {
        if (DatabaseChoice::NONE === $database) {
            return '';
        }

        $root = DatabaseChoice::SQLITE === $database ? '$this->projectRoot' : '';

        return '            new DbProvider(' . $root . "),\n";
    }

    /**
     * The engine of the answers, or the default engine.
     */
    private function engine(Answers $answers): string
    {
        return $answers->engine ?? $this->engines()->names()[0];
    }

    /**
     * A default path of the flavor, relative to the project root.
     */
    private function path(string $key): string
    {
        return Config::defaultPaths(Flavor::MVC)[$key];
    }

    private function providerNamespace(Answers $answers): string
    {
        return $answers->namespace . '\\Providers';
    }

    /**
     * The line of ViewProvider in providers() of the front. The Volt provider
     * also gets the cache folder.
     */
    private function viewProvider(bool $volt): string
    {
        $cache = true === $volt ? ", \$this->projectRoot . '/" . self::VOLT_CACHE . "'" : '';

        return "            new ViewProvider(\$this->projectRoot . '/" . $this->path('views') . "'" . $cache . "),\n";
    }
}
