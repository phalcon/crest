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

use Crest\Console\Command\Command;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\ClassName;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Project\Flavor;
use FilesystemIterator;

use function escapeshellarg;
use function file_exists;
use function getcwd;
use function is_dir;
use function preg_match;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_replace;
use function strtolower;
use function version_compare;

/**
 * Creates a new ADR project from stubs.
 *
 * This command runs where no project exists yet: no crest.php, no
 * composer.json, no vendor/. Thus it extends the console Command, not
 * ProjectCommand. It gets all values from its arguments, and it writes
 * crest.php. It does not read it.
 *
 * It runs nothing: no composer, no docker, no network. It checks all its
 * input before it writes the first file. Only a write error, for example a
 * full disk, can stop it after it has written some of the files.
 */
final class NewCommand extends Command
{
    /**
     * Where the actions go, relative to the project root. The seed action and
     * the front controller use it.
     */
    private const ACTION_PATH = 'src/Action';

    /**
     * The crest that the new project requires. This crest creates the project,
     * and the project crest (vendor/bin/crest) runs the project commands,
     * because they need the project autoloader and its Phalcon.
     */
    private const CREST = 'dev-master';

    /**
     * Stub name => path in the new project. The seed action is not here: it
     * uses the action stub with its own placeholders.
     */
    private const FILES = [
        Stub::PROJECT_PREFIX . 'composer'   => 'composer.json',
        Stub::PROJECT_PREFIX . 'config'     => 'crest.php',
        Stub::PROJECT_PREFIX . 'env'        => '.env',
        Stub::PROJECT_PREFIX . 'gitignore'  => '.gitignore',
        Stub::PROJECT_PREFIX . 'htrouter'   => ServeCommand::ROUTER,
        Stub::PROJECT_PREFIX . 'readme'     => 'README.md',
        Stub::PROJECT_PREFIX . 'compose'    => 'docker-compose.yml',
        Stub::PROJECT_PREFIX . 'dockerfile' => 'resources/docker/Dockerfile',
        Stub::PROJECT_PREFIX . 'index'      => 'public/index.php',
        Stub::PROJECT_PREFIX . 'front'      => 'src/AppFront.php',
    ];

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
     * The class of the seed action, which answers GET /. This is a copy of the
     * framework routing rule, because there is no vendor/ to ask yet.
     * GeneratedProjectTest sends GET / through the generated application to
     * make sure that the copy is correct.
     */
    private const SEED = 'Get';

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
        return Definition::for('new', 'Create an ADR project')
            ->argument('name', true, 'Project directory, e.g. my-app')
            ->option('namespace=s', 'Root namespace for the generated code', 'App')
            ->option('php=s', 'PHP version the project targets, major.minor', '8.4')
            ->option('phalcon=s', 'Phalcon: v5 (extension) or v6 (package)', 'v5')
            ->option('force', 'Write into a directory that is not empty, and overwrite files with the same names');
    }

    public function handle(Input $input, Output $output): int
    {
        $name      = $this->name($input->argumentString('name'));
        $namespace = ClassName::namespace($input->optionString('namespace'));
        $php       = $this->php($input->optionString('php'));
        $variant   = strtolower($input->optionString('phalcon'));

        if (false === isset(self::PHALCON[$variant])) {
            throw new Exception(
                sprintf("unknown Phalcon version '%s'; expected v5 or v6", $variant)
            );
        }

        [$package, $constraint] = self::PHALCON[$variant];

        $parent = self::parent($input);
        $target = $parent . '/' . $name;
        $force  = true === $input->option('force');

        $this->guard($target, $force);

        // Overrides come from the directory that the project goes into. A
        // team that publishes the project stubs there gets its own
        // conventions in each project that it creates there.
        $stub   = new Stub(Paths::stubs(), $parent);
        $flavor = Flavor::ADR->value;

        $replacements = [
            'actionNamespace'   => $namespace . '\\Action',
            'actionPath'        => self::ACTION_PATH,
            'crestConstraint'   => self::CREST,
            'jsonNamespace'     => str_replace('\\', '\\\\', $namespace),
            'namespace'         => $namespace,
            'phalconConstraint' => $constraint,
            'phalconPackage'    => $package,
            'phalconVariant'    => $variant,
            'phpVersion'        => $php,
            'project'           => $name,
            'seed'              => self::SEED,
            'service'           => InstallCommand::SERVICE,
            // The prefix of each line of the extension install in the
            // Dockerfile: active for v5, commented out for v6.
            'v5'                => 'v5' === $variant ? '' : '# ',
        ];

        // All files render before the first write. A published stub that does
        // not render then stops the command before it writes a file.
        $files = [];

        foreach (self::FILES as $stubName => $path) {
            $files[$path] = $stub->render($flavor, $stubName, $replacements);
        }

        // The seed action uses the usual action stub. Convention cannot name
        // it, because Convention asks the router, and there is no vendor/ yet.
        $files[self::ACTION_PATH . '/' . self::SEED . '.php'] = $stub->render(
            $flavor,
            'action',
            [
                'attributes' => '',
                'class'      => self::SEED,
                'namespace'  => $namespace . '\\Action',
                'params'     => '',
            ]
        );

        // guard() has refused a directory that is not empty, unless --force
        // is given. Thus a file that exists here can be overwritten.
        foreach ($files as $path => $contents) {
            ArtifactWriter::write($target . '/' . $path, $contents);
        }

        $this->report(
            $output,
            '' === $input->optionString('directory') ? $name : $target
        );

        return 0;
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
     * Both ways to run the project, always. The generated files are the same
     * on every host. Only this text names the two ways, so nothing here
     * examines the environment.
     */
    private function report(Output $output, string $shown): void
    {
        // Quoted when the path has a space, so that the line works when pasted.
        $cd = sprintf('    cd %s', true === str_contains($shown, ' ') ? escapeshellarg($shown) : $shown);

        $output->success(sprintf('Created %s/', $shown));
        $output->line();
        $output->line('Nothing runs it yet. With docker:');
        $output->line();
        $output->line($cd);
        $output->line('    crest up');
        $output->line('    crest install');
        $output->line();
        $output->line('Or with PHP and composer on the host:');
        $output->line();
        $output->line($cd);
        $output->line('    composer install');
        $output->line('    crest serve');
        $output->line();
        $output->line(sprintf('Then GET / answers from %s/%s.php', self::ACTION_PATH, self::SEED));
    }
}
