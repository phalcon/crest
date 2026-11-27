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
use Crest\Project\Survey;
use Phalcon\Contracts\Container\Service\Collection;
use Phalcon\Di\DiInterface;
use Throwable;

use function basename;
use function explode;
use function file_exists;
use function in_array;
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
 * controller, the compose file, the Dockerfile, .gitignore and .env.
 */
final class DatabaseSetup
{
    /**
     * The class of the provider in the provider path, as `crest new` writes
     * it.
     */
    public const PROVIDER = 'DbProvider';

    /**
     * The service of the database in the container of the project.
     */
    private const SERVICE = 'db';

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

        $database->checkService($this->config->runtime());
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

        $compose = Survey::composeFile($root);

        $this->guidance($database, $output, $namespace);
        $this->server($database, $output, $values, $compose);
        $this->lines($output, 'Add to .gitignore', $values['databaseIgnore']);

        // Without a compose file, nothing gives .env to the app, and DB_HOST
        // would name a service that does not exist.
        if (null !== $compose || false === $database->hasService()) {
            $this->lines($output, 'Add to .env', $values['databaseEnv']);
        }

        $output->line();
        $output->line(self::next($flavor));
    }

    /**
     * Stops when the container of the project has a db service, and the
     * provider folder has no DbProvider (#97). With a DbProvider, the file
     * check of add() decides: DbProvider gives the db service itself. Only
     * make:database calls it: init runs before the project has its
     * autoloader. A front that does not boot, for example with no vendor/, or
     * no front, does not stop the command.
     */
    public function checkContainer(): void
    {
        if (true === is_file(self::file($this->config->path('provider')))) {
            return;
        }

        try {
            $container = Bootstrap::container($this->config);
        } catch (Throwable) {
            return;
        }

        if (
            ($container instanceof DiInterface || $container instanceof Collection)
            && true === $container->has(self::SERVICE)
        ) {
            throw new Exception(
                sprintf(
                    "the project container has a '%s' service already; make:database adds no %s",
                    self::SERVICE,
                    self::PROVIDER
                )
            );
        }
    }

    /**
     * The registration of DbProvider in the front controller, from the stub
     * fragment-guidance-database. An ADR front that declares
     * registerProviders() gets only the line to add (#81). With no front
     * controller, the line for the container of the project, and no line for
     * a front (#97).
     */
    private function guidance(DatabaseChoice $database, Output $output, string $namespace): void
    {
        $flavor    = $this->config->flavor();
        $bootstrap = $this->config->bootstrap();
        $name      = Stub::FRAGMENT_PREFIX . 'guidance-database';
        $values    = [
            'class'     => self::PROVIDER,
            'namespace' => $namespace,
        ];

        if (null === $bootstrap) {
            $output->write(
                $this->stub->render(
                    $flavor->value,
                    $name . '-no-front',
                    $values + ['arguments' => $database->arguments('$projectRoot')]
                )
            );

            return;
        }

        $front   = Flavor::ADR === $flavor ? Bootstrap::overriding($bootstrap) : null;
        $values += ['arguments' => $database->arguments()];

        $output->write(
            null === $front
                ? $this->stub->render($flavor->value, $name, $values)
                : $this->stub->render($flavor->value, $name . '-override', $values + ['front' => $front])
        );
    }

    /**
     * The lines to add to a file that exists, each with an indent, after
     * their heading. Nothing when the database has no lines for the file.
     */
    private function lines(Output $output, string $heading, string $lines): void
    {
        $lines = trim($lines, "\n");

        if ('' === $lines) {
            return;
        }

        $output->line();
        $output->line($heading . ':');
        $output->line();

        foreach (explode("\n", $lines) as $line) {
            $output->line('' === $line ? '' : '    ' . $line);
        }
    }

    /**
     * The parts of a server database that `new` writes into the compose file
     * and the Dockerfile (#96), from the same stubs. When the compose file
     * has a database service, crest prints a note in place of the service.
     * Without a compose file, the defaults of DbProvider apply: 127.0.0.1 and
     * the default port, as for PHP on the host in `new`.
     *
     * @param array<string, string> $values
     */
    private function server(DatabaseChoice $database, Output $output, array $values, ?string $compose): void
    {
        if (false === $database->hasService()) {
            return;
        }

        if (null === $compose) {
            $output->line();
            $output->line(
                sprintf(
                    'The project has no compose file. PHP needs %s, and the defaults of DbProvider connect to '
                    . '127.0.0.1, port %s.',
                    $database->extension(),
                    $values['databasePort']
                )
            );

            return;
        }

        $file = basename($compose);

        if (true === in_array(DatabaseChoice::SERVICE, Survey::services($compose), true)) {
            $output->line();
            $output->line(
                sprintf(
                    "%s has a service '%s' already, so crest prints no service. DbProvider connects to it.",
                    $file,
                    DatabaseChoice::SERVICE
                )
            );
        } else {
            $this->lines($output, sprintf('Add the service and its volume to %s', $file), $values['databaseService']);
        }

        $this->lines($output, sprintf('Add to the app service of %s', $file), $values['dependsOn']);
        $this->lines(
            $output,
            'Add to the Dockerfile of the app service, after apt-get update',
            $values['databaseInstall']
        );
    }
}
