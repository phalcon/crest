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

namespace Crest\Command\Route;

use Crest\ADR\ActionResolver;
use Crest\ADR\PhalconRouterResolver;
use Crest\Console\Command\Command;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Project\Config;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Throwable;

use function array_pop;
use function array_values;
use function class_exists;
use function explode;
use function implode;
use function is_dir;
use function ksort;
use function str_replace;
use function strlen;
use function strtoupper;
use function substr;

/**
 * Every route the application answers.
 *
 * Under ADR there is no routes file: a route is a class name, so the only way
 * to answer "what URLs does this application serve?" is to read the Action
 * classes and ask the framework what each one answers. That is what this does -
 * it derives nothing itself.
 */
final class ListCommand extends Command
{
    public function define(): Definition
    {
        return Definition::for('route:list', 'List the routes the application answers');
    }

    public function handle(Input $input, Output $output): int
    {
        $config = Config::discover(
            $input->optionStringOrNull('directory'),
            $input->optionStringOrNull('config')
        );

        $base      = $config->namespaceFor('action');
        $directory = $config->path('action');
        $resolver  = new PhalconRouterResolver();

        $routes = [];

        foreach ($this->actionFiles($directory) as $file => $class) {
            $fqcn = $base . '\\' . $class;

            // Loaded, not merely named: trailing attributes come from the
            // Action's params(), and an unloaded class reports none.
            $this->load($file, $fqcn);

            $path = $resolver->pathFor($base, $fqcn);

            if (null === $path) {
                continue;
            }

            // Keyed by path alone: one path names exactly one Action, so the
            // path is already unique and sorting by it is sorting the listing.
            $routes[$path] = [$this->verb($class), $path, $fqcn];
        }

        if ([] === $routes) {
            $output->line('no actions found in ' . $directory);

            return 0;
        }

        // The filesystem yields files in whatever order it likes, which is not
        // the order anyone wants to read routes in.
        ksort($routes);

        // Keyed by path for sorting; table() wants a list.
        $output->table(['METHOD', 'PATH', 'ACTION'], array_values($routes));

        return 0;
    }

    /**
     * Every Action file under the action directory, keyed by path, valued by
     * the class name relative to the base namespace.
     *
     * @return array<string, string>
     */
    private function actionFiles(string $directory): array
    {
        if (false === is_dir($directory)) {
            return [];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        $found = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($directory) + 1);

            $found[$file->getPathname()] = str_replace(
                ['/', '.php'],
                ['\\', ''],
                $relative
            );
        }

        return $found;
    }

    /**
     * Bring the Action into memory so its params() can be read.
     *
     * The guard is on the class, not the file. require_once keys on the path,
     * so the same class reached by a different path - an autoloader that
     * already found it, a second project directory in one process - would be
     * declared twice and fatal.
     *
     * User code, so it may also fail to declare: a missing parent class, a
     * syntax error. That is the project's problem to fix and not a reason for
     * the listing to die, so the route is still reported, just without whatever
     * params() would have added.
     */
    private function load(string $file, string $class): void
    {
        if (true === class_exists($class, false)) {
            return;
        }

        try {
            require_once $file;
        } catch (Throwable) {
            // Reported without its attributes rather than not at all.
        }
    }

    /**
     * The HTTP verb, taken as whatever precedes the concatenated namespace
     * segments in the class name.
     *
     * Derived rather than matched against a list of verbs, so crest holds no
     * copy of which verbs the framework recognises - if it gains one, this
     * keeps working.
     */
    private function verb(string $class): string
    {
        $parts = explode('\\', $class);
        $last  = array_pop($parts);

        return strtoupper(substr($last, 0, strlen($last) - strlen(implode('', $parts))));
    }
}
