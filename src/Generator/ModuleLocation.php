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

namespace Crest\Generator;

use Crest\Console\Exceptions\Exception;
use Crest\Project\Config;

use function count;
use function is_dir;
use function is_file;
use function preg_match;
use function scandir;
use function sort;
use function sprintf;
use function strtolower;

use const SORT_STRING;

/**
 * Where an MVC module is (#55). All the modules are in the `module` path, and
 * each module has the same structure:
 *
 *     <module path>/Admin/Module.php      App\Modules\Admin\Module
 *     <module path>/Admin/Controllers/    App\Modules\Admin\Controllers
 *     <module path>/Admin/Forms/          App\Modules\Admin\Forms
 *     <module path>/Admin/Models/         App\Modules\Admin\Models
 *     <module path>/Admin/views/          no classes
 *
 * The folders with classes are in StudlyCase, because psr-4 needs the folder
 * to match the namespace. The folder of a new module is the name in
 * StudlyCase (ClassName::studly()), and the key of the module is the folder
 * in lowercase. The key goes into registerModules(), the routes and each
 * printed --module.
 *
 * crest finds a module by its key, and uses the spelling of the folder on the
 * disk. So --module=adminpanel finds AdminPanel. On a file system that
 * ignores case, a check of the path alone would find the folder Admin for the
 * name ADMIN, and crest would write App\Modules\ADMIN classes into it.
 */
final class ModuleLocation
{
    /**
     * The folder of the controllers of a module.
     */
    public const CONTROLLERS = 'Controllers';

    /**
     * The folder of the forms of a module. make:module does not write it.
     */
    public const FORMS = 'Forms';

    /**
     * The folder of the models of a module.
     */
    public const MODELS = 'Models';

    /**
     * The class of a module, and the name of its file.
     */
    public const NAME = 'Module';

    /**
     * The folder of the views of a module. It holds no classes.
     */
    public const VIEWS = 'views';

    private function __construct(
        public readonly string $key,
        public readonly string $namespace,
        public readonly string $path,
    ) {
    }

    /**
     * A module that make:controller and make:model write into. Its Module.php
     * must exist: make:module writes it.
     *
     * The hint gives the name as the user typed it, not the key: make:module
     * with that name writes the folder that the error names. The key of
     * admin_panel is adminpanel, which gives the folder Adminpanel, not
     * AdminPanel.
     */
    public static function existing(Config $config, string $name): self
    {
        $location = self::of($config, $name);

        if (false === is_file($location->file())) {
            throw new Exception(
                sprintf(
                    "module '%s' was not found: no %s.php in %s; run crest make:module %s first",
                    $location->key,
                    self::NAME,
                    $location->path,
                    $name
                )
            );
        }

        return $location;
    }

    /**
     * The module of a name: the folder with its key, or the folder of a new
     * module. The checks come in this order: the name, the folders, and the
     * psr-4 entry of the `module` path.
     */
    public static function of(Config $config, string $name): self
    {
        // studly() only removes `_` and makes the first letter of each part
        // uppercase, so a name that is not usable gives a folder that is not
        // usable.
        $folder = ClassName::studly($name);

        if (0 === preg_match(ClassName::PATTERN, $folder)) {
            throw new Exception(
                sprintf("'%s' is not a usable module name; expected a single name like 'admin'", $name)
            );
        }

        $key     = strtolower($folder);
        $modules = $config->path('module');
        $found   = self::folders($modules, $key);

        if (1 < count($found)) {
            throw new Exception(
                sprintf(
                    "two folders give the module '%s': %s/%s and %s/%s",
                    $key,
                    $modules,
                    $found[0],
                    $modules,
                    $found[1]
                )
            );
        }

        $folder = $found[0] ?? $folder;

        return new self($key, $config->namespaceFor('module') . '\\' . $folder, $modules . '/' . $folder);
    }

    /**
     * The folders in the `module` path whose name in lowercase is the key,
     * in byte order. A file is not a module. scandir() sorts with the
     * collation of the locale, so its order alone can change from one
     * environment to the next. A folder that crest cannot read gives no
     * folders and no PHP warning, as Survey does.
     *
     * @return list<string>
     */
    private static function folders(string $modules, string $key): array
    {
        if (false === is_dir($modules)) {
            return [];
        }

        $found = [];

        foreach (@scandir($modules) ?: [] as $entry) {
            if ($key === strtolower($entry) && true === is_dir($modules . '/' . $entry)) {
                $found[] = $entry;
            }
        }

        sort($found, SORT_STRING);

        return $found;
    }

    /**
     * The file of the Module class.
     */
    public function file(): string
    {
        return $this->path . '/' . self::NAME . '.php';
    }

    /**
     * A folder of the module: CONTROLLERS, FORMS, MODELS or VIEWS.
     */
    public function folder(string $part): string
    {
        return $this->path . '/' . $part;
    }

    /**
     * The namespace of a folder of the module: CONTROLLERS, FORMS or MODELS.
     */
    public function namespaceOf(string $part): string
    {
        return $this->namespace . '\\' . $part;
    }
}
