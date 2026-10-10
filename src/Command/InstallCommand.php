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

use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Project\Config;
use Crest\Project\Runtime;

/**
 * Runs composer install in the project container. On the host, use
 * `composer install` directly. This command exists because crest knows the
 * container: the service of the `runtime` key in crest.php, `app` when the
 * key does not name one. As the hand-off, it reads only that key.
 */
final class InstallCommand extends ComposeCommand
{
    public function define(): Definition
    {
        return Definition::for('install', 'Install composer dependencies in the project container');
    }

    public function handle(Input $input, Output $output): int
    {
        $file = Config::file($input->optionStringOrNull('directory'), $input->optionStringOrNull('config'))
            ?? throw new Exception(Config::MISSING);

        return $this->exec($input, Runtime::fromFile($file)->service, ['composer', 'install']);
    }
}
