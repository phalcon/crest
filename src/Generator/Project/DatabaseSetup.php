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

use Crest\Console\Exceptions\Exception;
use Crest\Console\Output;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\Stub;
use Crest\Project\Bootstrap;
use Crest\Project\Config;
use Crest\Project\Flavor;

use function basename;
use function explode;
use function file_exists;
use function is_file;
use function sprintf;
use function trim;

/**
 * Adds a database to a project that exists (#82): make:database, and
 * `crest init --database` after it writes crest.php (#95). The project gets
 * what `crest new --database` gives a new project, from the same stubs.
 *
 * Crest changes no file that exists. It writes DbProvider and the SQLite
 * files, and prints the lines to add to each file that exists: the front
 * controller, .gitignore and .env.
 */
final class DatabaseSetup
{
    /**
     * The class of the provider in the provider path, as `crest new` writes
     * it.
     */
    public const PROVIDER = 'DbProvider';

    public function __construct(private readonly Config $config, private readonly Stub $stub)
    {
    }

    /**
     * Stops when the provider folder has DbProvider, unless --force. init
     * calls it before it writes crest.php: thus a run that stops changes
     * nothing.
     */
    public static function guard(string $folder, bool $force): void
    {
        $file = self::file($folder);

        if (true === is_file($file) && false === $force) {
            throw new Exception(sprintf('%s already exists; pass --force to overwrite', $file));
        }
    }

    /**
     * The last line of the database lines of `new` and make:database.
     * make:model is MVC only, make:repository ADR only (#84).
     */
    public static function next(Flavor $flavor): string
    {
        return Flavor::MVC === $flavor
            ? 'Then crest make:model <table> reads it.'
            : 'Then crest make:repository <table> reads it.';
    }

    private static function file(string $folder): string
    {
        return $folder . '/' . self::PROVIDER . '.php';
    }

    /**
     * Writes DbProvider and the SQLite files, then prints the lines to add.
     * A SQLite file that exists stays, also with --force: git ignores it, so
     * nothing can restore its data.
     */
    public function add(DatabaseChoice $database, Output $output, bool $force): void
    {
        $name   = $database->provider()
            ?? throw new Exception('no database to add; choose mariadb, mysql, pgsql or sqlite');
        $folder = $this->config->path('provider');

        self::guard($folder, $force);

        $flavor    = $this->config->flavor();
        $root      = $this->config->root();
        $namespace = $this->config->namespaceFor('provider');
        // DB_NAME and DB_USERNAME come from the folder name, as `new` uses
        // the name of the project.
        $values = [
            ...$database->values($this->stub, $flavor->value, basename($root)),
            'providerNamespace' => $namespace,
        ];

        ArtifactWriter::write(self::file($folder), $this->stub->render($flavor->value, $name, $values));
        $output->success(sprintf('Created %s', self::file($folder)));

        foreach ($database->files() as $path => $contents) {
            $file = $root . '/' . $path;

            if (true === file_exists($file)) {
                $output->line(sprintf('Kept %s: it exists', $file));

                continue;
            }

            ArtifactWriter::write($file, $contents);
            $output->success(sprintf('Created %s', $file));
        }

        $this->guidance($database, $output, $namespace);
        $this->lines($output, '.gitignore', $values['databaseIgnore']);
        $this->lines($output, '.env', $values['databaseEnv']);
        $output->line();
        $output->line(self::next($flavor));
    }

    /**
     * The registration of DbProvider in the front controller, from the stub
     * fragment-guidance-database. An ADR front that declares
     * registerProviders() gets only the line to add (#81).
     */
    private function guidance(DatabaseChoice $database, Output $output, string $namespace): void
    {
        $flavor    = $this->config->flavor();
        $bootstrap = $this->config->bootstrap();
        $front     = Flavor::ADR === $flavor && null !== $bootstrap ? Bootstrap::overriding($bootstrap) : null;
        $name      = Stub::FRAGMENT_PREFIX . 'guidance-database';
        $values    = [
            'arguments' => $database->arguments(),
            'class'     => self::PROVIDER,
            'namespace' => $namespace,
        ];

        $output->write(
            null === $front
                ? $this->stub->render($flavor->value, $name, $values)
                : $this->stub->render($flavor->value, $name . '-override', $values + ['front' => $front])
        );
    }

    /**
     * The lines to add to a file that exists, each with an indent. Nothing
     * when the database has no lines for the file.
     */
    private function lines(Output $output, string $file, string $lines): void
    {
        $lines = trim($lines, "\n");

        if ('' === $lines) {
            return;
        }

        $output->line();
        $output->line(sprintf('Add to %s:', $file));
        $output->line();

        foreach (explode("\n", $lines) as $line) {
            $output->line('' === $line ? '' : '    ' . $line);
        }
    }
}
