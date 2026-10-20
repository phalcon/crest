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

namespace Crest\Command;

use Crest\Commands;
use Crest\Console\Command\Command;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\ClassName;
use Crest\Generator\Project\Answers;
use Crest\Generator\Project\DatabaseChoice;
use Crest\Generator\Project\Skeleton;
use Crest\Generator\Project\Skeletons;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Process\Runner;
use Crest\Process\ShellRunner;
use Crest\Project\Config;
use Crest\Project\Flavor;
use Crest\Project\Layout;
use Crest\Project\Runtime;
use Crest\Project\Settings;
use Crest\Project\Steps;
use FilesystemIterator;
use Throwable;

use function array_filter;
use function array_keys;
use function array_map;
use function array_slice;
use function chmod;
use function count;
use function escapeshellarg;
use function file_exists;
use function getcwd;
use function implode;
use function is_dir;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function strtolower;
use function var_export;
use function version_compare;

use const ARRAY_FILTER_USE_KEY;
use const PHP_BINARY;

/**
 * Creates a new project from stubs. The parts of each flavor - its files, its
 * values and its first artifacts - are in its skeleton
 * (Crest\Generator\Project\Skeleton).
 *
 * This command runs where no project exists yet: no crest.php, no
 * composer.json, no vendor/. Thus it extends the console Command, not
 * ProjectCommand. It gets its values from its arguments. When an option is
 * not given, it asks the question, through the code that `crest init` uses.
 * A run without interaction takes the default of the option. It writes
 * crest.php, and reads back only its `new` key.
 *
 * By default, it runs nothing: no composer, no docker, no network. The `new`
 * key of crest.php can list commands that it runs after it writes the files
 * (Steps). It checks all its input before it writes the first file, also the
 * published project stubs: a copy without a placeholder that the project
 * needs stops it (checkPlaceholders()). The `new` key is the exception: it
 * reads the key from the crest.php that it writes, so it checks the key after
 * the write. Only a write error, for example a full disk, can stop it after
 * it has written some of the files.
 */
final class NewCommand extends Command
{
    /**
     * The crest that the new project requires. This crest creates the project,
     * and the project crest (vendor/bin/crest) runs the project commands,
     * because they need the project autoloader and its Phalcon.
     */
    private const CREST = 'dev-master';

    /**
     * Stub name => path in the new project: the files of each flavor. Each
     * flavor has its own project-front and project-readme, and the stub
     * lookup finds them. The other files of a flavor come from its skeleton.
     */
    private const FILES = [
        Stub::PROJECT_PREFIX . 'composer'   => 'composer.json',
        Stub::PROJECT_PREFIX . 'config'     => 'crest.php',
        Stub::PROJECT_PREFIX . 'env'        => Layout::ENV_FILE,
        Stub::PROJECT_PREFIX . 'gitignore'  => '.gitignore',
        Stub::PROJECT_PREFIX . 'htrouter'   => Layout::ROUTER,
        Stub::PROJECT_PREFIX . 'readme'     => 'README.md',
        Stub::PROJECT_PREFIX . 'launcher'   => self::LAUNCHER,
        Stub::PROJECT_PREFIX . 'compose'    => 'docker-compose.yml',
        Stub::PROJECT_PREFIX . 'dockerfile' => 'resources/docker/Dockerfile',
        Stub::PROJECT_PREFIX . 'index'      => Layout::DOCUMENT_ROOT . '/index.php',
        Stub::PROJECT_PREFIX . 'front'      => Layout::SOURCE . '/' . Layout::FRONT . '.php',
    ];

    /**
     * The launcher in the project root. `./crest` runs crest with docker
     * only: PHP on the host is not necessary.
     */
    private const LAUNCHER = 'crest';

    /**
     * A project name: letters, digits, '-' and '_', with a letter or digit
     * first. It is a directory name, not a path. It is also the docker
     * container prefix, which must start with a letter or digit.
     */
    private const NAME = '/^[A-Za-z0-9][A-Za-z0-9_-]*\z/';

    /**
     * Variant => the composer requirement and its constraint. v5 needs 5.18,
     * because Phalcon\ADR first ships in cphalcon 5.18.0; an older extension
     * installs and then fails on every request. v6 has @RC because
     * phalcon/phalcon has no stable 6.0 release yet. Composer still selects a
     * stable release when one exists.
     */
    private const PHALCON = [
        'v5' => ['ext-phalcon', '^5.18'],
        'v6' => ['phalcon/phalcon', '^6.0@RC'],
    ];

    /**
     * major.minor only, with no leading zeros. The Dockerfile base image
     * `php:<version>-cli` has no patch tags, and composer.json uses the same
     * value.
     */
    private const PHP = '/^[1-9]\d*\.(?:0|[1-9]\d*)\z/';

    /**
     * The oldest PHP that runs the generated code. It uses readonly promoted
     * properties, which need PHP 8.1.
     */
    private const PHP_FLOOR = '8.1';

    /**
     * The placeholders of #54, and the project stub of each. A copy of a stub
     * that a team published before #54 does not have them (spec 3.5).
     */
    private const PLACEHOLDERS = [
        'databaseEnv'       => 'env',
        'databaseIgnore'    => 'gitignore',
        'databaseInstall'   => 'dockerfile',
        'databaseService'   => 'compose',
        'dbProvider'        => 'front',
        'dbUse'             => 'front',
        'dependsOn'         => 'compose',
        'models'            => 'config',
        'phql'              => 'composer',
        'registerProviders' => 'front',
    ];

    private readonly Runner $runner;

    /**
     * The default lets the kernel's `new $class()` work. A test gives a fake
     * runner, and checks the exact argv.
     */
    public function __construct(?Runner $runner = null)
    {
        $this->runner = $runner ?? new ShellRunner();
    }

    /**
     * The directory that the project goes into.
     *
     * --directory is the global project-root option. This command has no
     * project yet, so for it the option names where the project goes.
     *
     * The project stub overrides are also read from here. stub:publish calls
     * this method, so that it writes the overrides where this command reads
     * them.
     *
     * An empty value reads as absent, as optionString() reads every other
     * option. Otherwise `--directory="$DIR"` with an unset variable puts the
     * project in the filesystem root.
     */
    public static function parent(Input $input): string
    {
        $directory = $input->optionString('directory');

        return rtrim('' === $directory ? (string) getcwd() : $directory, '/');
    }

    public function define(): Definition
    {
        return Definition::for('new', 'Create an ADR or MVC project')
            ->argument('name', true, 'Project directory, e.g. my-app')
            ->option('flavor=s', 'Flavor: adr or mvc', 'adr')
            ->option('template-engine=s', 'The view engine of an MVC project: phtml. Default: phtml')
            ->option('namespace=s', 'Root namespace for the generated code', 'App')
            ->option('php=s', 'PHP version the project targets, major.minor', '8.4')
            ->option('phalcon=s', 'Phalcon: v5 (extension) or v6 (package)', 'v5')
            ->option('runtime=s', 'Where the project commands run: host or docker. Default: docker')
            ->option('service=s', 'The docker compose service, for docker. Default: app')
            ->option('database=s', 'The database: none, mariadb, mysql, pgsql or sqlite', 'none')
            ->option('camel-case', 'MVC: property names in camelCase (models.camelCase of crest.php)')
            ->option('force', 'Write into a directory that is not empty, and overwrite files with the same names');
    }

    public function handle(Input $input, Output $output): int
    {
        $name   = $this->name($input->argumentString('name'));
        $parent = self::parent($input);
        $target = $parent . '/' . $name;

        // Before the questions: a refused target asks nothing.
        $this->guard($target, true === $input->option('force'));

        // An option answers its question. Each question shows the default of
        // its option. Without interaction, the default is the answer.
        //
        // The flavor first: its skeleton has the files, the values and the
        // first artifacts of the project.
        $skeleton = Skeletons::for(
            true === $input->hasOption('flavor')
                ? $input->optionString('flavor')
                : $output->choice('Flavor', Skeletons::flavors(), $input->optionString('flavor'))
        );
        $flavor   = $skeleton->flavor();

        // Before the other questions: a refused engine asks nothing more.
        $engine = $skeleton->engines()->choose($output, $input->optionStringOrNull('template-engine'));

        $namespace = true === $input->hasOption('namespace')
            ? ClassName::namespace($input->optionString('namespace'))
            : Questions::namespace($output, $input->optionString('namespace'));

        $php = $this->php(
            true === $input->hasOption('php')
                ? $input->optionString('php')
                : $output->ask('PHP version', $input->optionString('php'), Questions::checkWith($this->php(...)))
        );

        $variant = strtolower(
            true === $input->hasOption('phalcon')
                ? $input->optionString('phalcon')
                : $output->choice('Phalcon version', array_keys(self::PHALCON), $input->optionString('phalcon'))
        );

        if (false === isset(self::PHALCON[$variant])) {
            throw new Exception(
                sprintf("unknown Phalcon version '%s'; expected v5 or v6", $variant)
            );
        }

        [$package, $constraint] = self::PHALCON[$variant];

        $runtime = Questions::runtime(
            $output,
            Runtime::docker(),
            $input->optionStringOrNull('runtime'),
            $input->optionStringOrNull('service')
        );

        $database = DatabaseChoice::select(
            true === $input->hasOption('database')
                ? $input->optionString('database')
                : $output->choice('Database', DatabaseChoice::names(), $input->optionString('database'))
        );

        $camelCase = $this->camelCase($input, $output, $flavor, $database);

        // After the questions, and before the first write.
        $this->checkService($runtime, $database);

        $answers = new Answers($name, $flavor, $namespace, $php, $variant, $runtime, $engine, $database);

        // Overrides come from the directory that the project goes into. A
        // team that publishes the project stubs there gets its own
        // conventions in each project that it creates there.
        $stub = new Stub(Paths::stubs(), $parent);

        // crest.php holds the decisions of the user. `crest init` writes it
        // through the same stub and the same Settings.
        $settings = new Settings(
            $flavor,
            $namespace,
            $namespace . '\\' . Layout::FRONT,
            Config::defaultPaths($flavor),
            $runtime,
            $camelCase
        );

        $replacements = [
            ...$settings->replacements(),
            'autoloader'        => Layout::AUTOLOADER,
            'crestConstraint'   => self::CREST,
            'documentRoot'      => Layout::DOCUMENT_ROOT,
            // The v5 lines of the Dockerfile name the extension also in a v6
            // project, where they are comments.
            'extensionConstraint' => self::PHALCON['v5'][1],
            'front'               => Layout::FRONT,
            'jsonNamespace'       => str_replace('\\', '\\\\', $namespace),
            'phalconConstraint'   => $constraint,
            'phalconPackage'      => $package,
            'phalconVariant'      => $variant,
            'phpVersion'          => $php,
            'port'                => (string) Layout::PORT,
            'portVariable'        => Layout::PORT_VARIABLE,
            'project'             => $name,
            'router'              => Layout::ROUTER,
            'service'             => $settings->runtime->service,
            'source'              => Layout::SOURCE,
            // The prefix of each line of the extension install in the
            // Dockerfile: active for v5, commented out for v6.
            'v5'                => 'v5' === $variant ? '' : '# ',
            ...$database->values($stub, $flavor->value, $name),
            ...$skeleton->values($answers),
        ];

        // A published copy from before #54 stops here, before the first
        // write, when the project needs one of its new placeholders.
        $this->checkPlaceholders($stub, $flavor->value, $replacements, $camelCase);

        // All files render before the first write. A published stub that does
        // not render then stops the command before it writes a file.
        $files = [];

        foreach ([...self::FILES, ...$skeleton->files($answers)] as $stubName => $path) {
            // crest.php gets only the values of Settings, as for `crest init`.
            // Thus an old published copy stops here, and does not drop a key.
            $files[$path] = $stub->render(
                $flavor->value,
                $stubName,
                Stub::PROJECT_PREFIX . 'config' === $stubName ? $settings->replacements() : $replacements
            );
        }

        // The first artifacts of the flavor, from the stubs of its
        // generators, and the files of the database that no stub renders.
        // A file of the database that exists stays: git ignores the SQLite
        // file, so nothing can restore its data.
        $files = [
            ...$files,
            ...$skeleton->seed($stub, $answers),
            ...array_filter(
                $database->files(),
                static fn (string $path): bool => false === file_exists($target . '/' . $path),
                ARRAY_FILTER_USE_KEY
            ),
        ];

        // guard() has refused a directory that is not empty, unless --force
        // is given. Thus a file that exists here can be overwritten.
        foreach ($files as $path => $contents) {
            ArtifactWriter::write($target . '/' . $path, $contents);
        }

        // The return value is not checked: this command has just written the
        // file, so chmod() cannot fail on a usual filesystem.
        chmod($target . '/' . self::LAUNCHER, 0o755);

        $shown = '' === $input->optionString('directory') ? $name : $target;

        $output->success(sprintf('Created %s/', $shown));
        $output->line();

        $steps = $this->steps($target, $shown, $stub, $flavor->value);

        if ([] === $steps) {
            $this->report($output, $shown, $skeleton, $database);

            return 0;
        }

        return $this->runSteps($output, $steps, $target, $shown);
    }

    /**
     * The models.camelCase answer of an MVC project. Null for ADR: make:model
     * is MVC only, so crest.php gets no models key. --camel-case answers it.
     * A project without a database has no tables yet, so it gets false with
     * no question.
     */
    private function camelCase(Input $input, Output $output, Flavor $flavor, DatabaseChoice $database): ?bool
    {
        if (Flavor::MVC !== $flavor) {
            return null;
        }

        if (true === $input->option('camel-case')) {
            return true;
        }

        if (DatabaseChoice::NONE === $database) {
            return false;
        }

        return 'yes' === $output->choice('Property names in camelCase', ['no', 'yes'], 'no');
    }

    /**
     * The written crest.php must have the flavor of the project. A published
     * project-config copy with a fixed flavor gives another one, and the
     * commands of crest then treat the project as that flavor. No key is adr,
     * as Config reads it.
     */
    private function checkFlavor(string $file, string $flavor): void
    {
        /** @var array<string, mixed> $declared */
        $declared = require $file;
        $written  = $declared['flavor'] ?? Flavor::ADR->value;

        if ($flavor !== $written) {
            throw new Exception(
                sprintf(
                    "the flavor is %s, not '%s'; keep 'flavor' => '{{ flavor }}' in the stub",
                    var_export($written, true),
                    $flavor
                )
            );
        }
    }

    /**
     * Each placeholder of PLACEHOLDERS that has a value must be in the stub
     * that crest uses: render() drops a value that has no placeholder, and
     * the project would lose its database service, or its models key, with
     * no message. An empty value is not lost, so an old copy works for a
     * project without a database. The models key with camelCase false says
     * what make:model does with no key, so only true needs the placeholder.
     *
     * @param array<string, string> $replacements
     */
    private function checkPlaceholders(Stub $stub, string $flavor, array $replacements, ?bool $camelCase): void
    {
        foreach (self::PLACEHOLDERS as $placeholder => $file) {
            $name   = Stub::PROJECT_PREFIX . $file;
            $needed = 'models' === $placeholder
                ? true === $camelCase
                : '' !== ($replacements[$placeholder] ?? '');

            if (false === $needed || true === $stub->hasPlaceholder($flavor, $name, $placeholder)) {
                continue;
            }

            throw new Exception(
                sprintf(
                    'the published stub %s has no {{ %s }}; publish it again with crest stub:publish %s '
                    . '--flavor=%s --force, and make your changes in it again',
                    $stub->resolve($flavor, $name),
                    $placeholder,
                    $name,
                    $flavor
                )
            );
        }
    }

    /**
     * The app service cannot have the name of the database service: compose
     * would have one service for two. The host runtime always has app, so
     * only a docker answer can fail.
     */
    private function checkService(Runtime $runtime, DatabaseChoice $database): void
    {
        if (false === $database->hasService() || DatabaseChoice::SERVICE !== $runtime->service) {
            return;
        }

        throw new Exception(
            sprintf(
                "the service '%s' is the name of the database service; choose another service name",
                DatabaseChoice::SERVICE
            )
        );
    }

    /**
     * The words of a step, as a user types them in a shell.
     *
     * @param non-empty-list<string> $step
     */
    private function display(array $step): string
    {
        return implode(' ', array_map($this->quote(...), $step));
    }

    /**
     * Refuses a target that would mix the new project into other files,
     * unless --force says that this is the intent. A missing or empty
     * directory is always correct.
     */
    private function guard(string $target, bool $force): void
    {
        if (true === file_exists($target) && false === is_dir($target)) {
            throw new Exception(sprintf('%s exists and is not a directory', $target));
        }

        if (true === $force || false === is_dir($target)) {
            return;
        }

        // FilesystemIterator skips . and .., so valid() is true only when the
        // directory contains something.
        if (true === (new FilesystemIterator($target))->valid()) {
            throw new Exception(
                sprintf('%s exists and is not empty; pass --force to write into it', $target)
            );
        }
    }

    /**
     * A name, not a path: `crest new ../elsewhere` must not write outside the
     * directory it runs in.
     */
    private function name(string $name): string
    {
        if (0 === preg_match(self::NAME, $name)) {
            throw new Exception(
                sprintf(
                    "'%s' is not a usable project name; expected letters, digits, '-' and '_', "
                    . 'starting with a letter or digit',
                    $name
                )
            );
        }

        return $name;
    }

    /**
     * major.minor, and not older than the generated code needs.
     */
    private function php(string $version): string
    {
        if (0 === preg_match(self::PHP, $version)) {
            throw new Exception(
                sprintf("'%s' is not a PHP version; expected major.minor, e.g. 8.4", $version)
            );
        }

        if (true === version_compare($version, self::PHP_FLOOR, '<')) {
            throw new Exception(
                sprintf(
                    'PHP %s is too old; the generated code needs %s or later',
                    $version,
                    self::PHP_FLOOR
                )
            );
        }

        return $version;
    }

    /**
     * The argv for the runner. The word `crest` runs the crest that runs this
     * command, with the same PHP: the user can have no crest on the PATH.
     *
     * @param non-empty-list<string> $step
     *
     * @return non-empty-list<string>
     */
    private function program(array $step): array
    {
        if (Commands::NAME !== $step[0]) {
            return $step;
        }

        return [PHP_BINARY, Paths::root() . '/bin/crest', ...array_slice($step, 1)];
    }

    /**
     * Quoted when the word has a space, so that the line works when pasted.
     */
    private function quote(string $word): string
    {
        return true === str_contains($word, ' ') ? escapeshellarg($word) : $word;
    }

    /**
     * Both ways to run the project, always. The generated files are the same
     * on every host. Only this text names the two ways, so nothing here
     * examines the environment. The lines of the database come last.
     */
    private function report(Output $output, string $shown, Skeleton $skeleton, DatabaseChoice $database): void
    {
        // Quoted when the path has a space, so that the line works when pasted.
        $cd = '    cd ' . $this->quote($shown);

        $output->line('Nothing runs it yet. With docker:');
        $output->line();
        $output->line($cd);
        $output->line('    ./crest up');
        $output->line('    ./crest install');
        $output->line();
        $output->line('Or with PHP and composer on the host:');
        $output->line();
        $output->line($cd);
        $output->line('    composer install');
        $output->line('    crest serve');
        $output->line();
        $output->line(sprintf('Then GET / answers from %s', $skeleton->entry()));

        if (DatabaseChoice::NONE === $database) {
            return;
        }

        $output->line();

        if (true === $database->hasService()) {
            $output->line(
                sprintf(
                    "The database: %s, in the docker service '%s'. The values are in .env.",
                    $database->value,
                    DatabaseChoice::SERVICE
                )
            );
            $output->line('On the host, start it with: docker compose up -d ' . DatabaseChoice::SERVICE);
            $output->line(sprintf('PHP on the host also needs %s.', $database->extension()));
        }

        if (DatabaseChoice::SQLITE === $database) {
            $output->line(sprintf('The database: sqlite, in %s.', DatabaseChoice::SQLITE_FILE));
        }

        // make:model is MVC only.
        if (Flavor::MVC === $skeleton->flavor()) {
            $output->line('Then crest make:model <table> reads it.');
        }
    }

    /**
     * Runs the commands of the `new` key in the project, in order. The first
     * command that fails stops the list, and its exit status is the result.
     * The output of each command goes directly to the terminal.
     *
     * @param list<non-empty-list<string>> $steps
     */
    private function runSteps(Output $output, array $steps, string $target, string $shown): int
    {
        foreach ($steps as $step) {
            $display = $this->display($step);

            $output->line('Running: ' . $display);

            $status = $this->runner->run($this->program($step), $target);

            if (0 !== $status) {
                $output->error(
                    sprintf(
                        "%s: '%s' failed with exit status %d; the files in %s/ stay",
                        Commands::NAME,
                        $display,
                        $status,
                        $shown
                    )
                );

                return $status;
            }
        }

        $count = count($steps);

        $output->success(
            sprintf("Ran %d %s from the 'new' key of crest.php", $count, 1 === $count ? 'command' : 'commands')
        );

        return 0;
    }

    /**
     * The `new` key of the crest.php that this command has just written. A
     * bad key, or a file that PHP cannot parse, comes from a published
     * project-config stub: the packaged stub has no key. A change to the
     * written crest.php does nothing, so the error names the stub that `new`
     * used: a copy in the flavor folder or in the shared folder. The files
     * stay, and nothing runs.
     *
     * @return list<non-empty-list<string>>
     */
    private function steps(string $target, string $shown, Stub $stub, string $flavor): array
    {
        $file = self::FILES[Stub::PROJECT_PREFIX . 'config'];

        try {
            $this->checkFlavor($target . '/' . $file, $flavor);

            return Steps::fromFile($target . '/' . $file);
        } catch (Throwable $throwable) {
            throw new Exception(
                sprintf(
                    '%s/%s: %s; the files in %s/ stay; fix %s and run new again with --force',
                    $shown,
                    $file,
                    $throwable->getMessage(),
                    $shown,
                    $stub->resolve($flavor, Stub::PROJECT_PREFIX . 'config')
                )
            );
        }
    }
}
