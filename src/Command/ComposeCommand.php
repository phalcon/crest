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
use Crest\Console\Input;
use Crest\Process\Compose;
use Crest\Process\Runner;
use Crest\Process\ShellRunner;
use Crest\Project\Config;

use function stream_isatty;

use const STDIN;

/**
 * The base for the commands that control the project containers.
 *
 * They are thin, but they are not aliases. crest wrote docker-compose.yml, so
 * crest knows the service of the project. They run docker compose in the
 * project root, which the root rule of all commands finds: the folder of the
 * nearest crest.php. They do not need vendor/, because they run before
 * composer install.
 */
abstract class ComposeCommand extends Command
{
    private readonly Runner $runner;

    private readonly bool $terminal;

    /**
     * The defaults let the kernel's `new $class()` work. A test gives a fake
     * runner, and checks the exact argv without docker.
     *
     * @param bool|null $terminal Whether stdin is a terminal. Null asks the
     *                            stream.
     */
    public function __construct(?Runner $runner = null, ?bool $terminal = null)
    {
        $this->runner   = $runner ?? new ShellRunner();
        $this->terminal = $terminal ?? stream_isatty(STDIN);
    }

    /**
     * Runs `docker compose` with the arguments in the project root.
     *
     * @param list<string> $arguments
     */
    protected function compose(Input $input, array $arguments): int
    {
        return $this->runner->run(
            ['docker', 'compose', ...$arguments],
            Config::rootFor($input->optionStringOrNull('directory'), $input->optionStringOrNull('config'))
        );
    }

    /**
     * Runs a command in a compose service of the project, with the -T rule
     * of Compose::exec().
     *
     * @param list<string> $command
     */
    protected function exec(Input $input, string $service, array $command): int
    {
        return $this->compose($input, Compose::exec($service, $command, $this->terminal));
    }
}
