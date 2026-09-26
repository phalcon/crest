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
use Crest\Process\Runner;
use Crest\Process\ShellRunner;
use Crest\Project\Locator;

use function count;
use function dirname;
use function file_get_contents;
use function getcwd;
use function getenv;
use function is_file;
use function preg_match;
use function preg_match_all;
use function rtrim;
use function sprintf;

use const PHP_BINARY;

/**
 * Starts PHP's built-in web server for the project. It uses the router
 * script that `new` writes. The router and the document root are the same
 * as in the CMD of the generated Dockerfile. Thus `serve` and `up` run the
 * same application.
 *
 * It extends the console Command, not ProjectCommand. It does not read
 * crest.php, and it does not load the project's vendor/: the server runs
 * the project in a child process, where public/index.php loads the project
 * autoloader. Thus a global crest can run it.
 */
final class ServeCommand extends Command
{
    /**
     * The router script, relative to the project root. NewCommand writes the
     * file with this name.
     */
    public const ROUTER = '.htrouter.php';

    /**
     * The composer autoloader, relative to the project root. The
     * public/index.php that `new` writes loads it.
     */
    private const AUTOLOADER = 'vendor/autoload.php';

    /**
     * The document root, relative to the project root.
     */
    private const DOCUMENT_ROOT = 'public';

    /**
     * The variables file of docker compose, relative to the project root.
     */
    private const ENV_FILE = '.env';

    /**
     * Only this machine can connect. The container listens on 0.0.0.0. Not
     * localhost: on some hosts localhost gives ::1 first, and then PHP
     * listens on IPv6 only.
     */
    private const HOST = '127.0.0.1';

    private const PORT_DEFAULT = 8080;

    /**
     * An APP_PORT line of .env, as docker compose reads it: an optional
     * `export`, then '=' or ':'. Spaces around the name, the separator and
     * the value are ignored. A quoted value ends at its quote. An unquoted
     * value ends before ' #'. A ' #' comment after the value is ignored.
     */
    private const PORT_LINE = '/^\h*(?:export\h+)?APP_PORT\h*[=:]\h*(?|"([^"]*)"|\'([^\']*)\'|(.*?))\h*(?: #.*)?\r?$/m';

    private const PORT_MAX = 65535;

    /**
     * The variable that docker-compose.yml reads for the published port.
     */
    private const PORT_VARIABLE = 'APP_PORT';

    private readonly Runner $runner;

    /**
     * The default lets the kernel's `new $class()` work. A test gives a fake
     * runner, and checks the exact argv without a server.
     */
    public function __construct(?Runner $runner = null)
    {
        $this->runner = $runner ?? new ShellRunner();
    }

    public function define(): Definition
    {
        return Definition::for('serve', "Start PHP's built-in web server for the project")
            ->option('port=s', 'Port, 1 to 65535. Default: APP_PORT, then 8080');
    }

    /**
     * Checks the router, the autoloader and the port first. An error then
     * stops the command before PHP starts. PHP itself reports a port that is
     * in use and a missing public/ directory.
     */
    public function handle(Input $input, Output $output): int
    {
        $root   = $this->root($input);
        $router = $root . '/' . self::ROUTER;

        if (false === is_file($router)) {
            throw new Exception(
                sprintf('%s was not found; serve uses the router script that crest new writes', $router)
            );
        }

        $autoloader = $root . '/' . self::AUTOLOADER;

        if (false === is_file($autoloader)) {
            throw new Exception(sprintf('%s was not found; run composer install first', $autoloader));
        }

        $port = $this->port($input, $root);

        // The PHP that runs crest, not the first php on the PATH.
        return $this->runner->run(
            [PHP_BINARY, '-S', self::HOST . ':' . $port, '-t', self::DOCUMENT_ROOT, self::ROUTER],
            $root
        );
    }

    /**
     * The APP_PORT value in the .env file. Empty if there is no file or no
     * APP_PORT line. The last line wins, as in docker compose.
     */
    private function envValue(string $file): string
    {
        if (
            true === is_file($file)
            && 0 < preg_match_all(self::PORT_LINE, (string) file_get_contents($file), $matches)
        ) {
            $values = $matches[1];

            return $values[count($values) - 1];
        }

        return '';
    }

    /**
     * --port, if the user gave it. If not, APP_PORT from the environment,
     * then APP_PORT from .env, then PORT_DEFAULT. docker compose uses the
     * same order, so that serve and up use the same port. An empty APP_PORT
     * reads as absent, as ${APP_PORT:-8080} in docker-compose.yml reads it.
     */
    private function port(Input $input, string $root): int
    {
        $option = $input->optionStringOrNull('port');

        if (null !== $option) {
            return $this->portNumber($option, '');
        }

        $variable = (string) getenv(self::PORT_VARIABLE);

        if ('' !== $variable) {
            return $this->portNumber($variable, sprintf(' (%s in the environment)', self::PORT_VARIABLE));
        }

        $file  = $root . '/' . self::ENV_FILE;
        $value = $this->envValue($file);

        if ('' !== $value) {
            return $this->portNumber($value, sprintf(' (%s in %s)', self::PORT_VARIABLE, $file));
        }

        return self::PORT_DEFAULT;
    }

    /**
     * Digits only, from 1 to PORT_MAX. The pattern ends with \z, not $,
     * because $ also accepts a trailing newline. The int goes into the argv,
     * so 0080 gives 80. The source tells the user where the value came from.
     * It is empty for --port.
     */
    private function portNumber(string $value, string $source): int
    {
        $port = (int) $value;

        if (0 === preg_match('/^\d+\z/', $value) || $port < 1 || $port > self::PORT_MAX) {
            throw new Exception(
                sprintf("'%s' is not a port%s; expected an integer from 1 to %d", $value, $source, self::PORT_MAX)
            );
        }

        return $port;
    }

    /**
     * --directory, if the user gave one. An empty value reads as absent, as
     * it does for `new` and `up`. If not, the directory of the nearest
     * crest.php above the working directory, so that serve works from a
     * subdirectory. If there is no crest.php, the working directory.
     */
    private function root(Input $input): string
    {
        $directory = $input->optionString('directory');

        if ('' !== $directory) {
            return rtrim($directory, '/');
        }

        $cwd  = (string) getcwd();
        $file = Locator::locate($cwd);

        return null === $file ? $cwd : dirname($file);
    }
}
