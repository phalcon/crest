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

use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Project\Config;

/**
 * Runs composer install in the project container. On the host, use
 * `composer install` directly. This command exists because crest knows the
 * container: the service of the `runtime` key in crest.php, `app` when the
 * key does not name one.
 */
final class InstallCommand extends ComposeCommand
{
    public function define(): Definition
    {
        return Definition::for('install', 'Install composer dependencies in the project container');
    }

    public function handle(Input $input, Output $output): int
    {
        $service = Config::discover(
            $input->optionStringOrNull('directory'),
            $input->optionStringOrNull('config')
        )->runtime()->service;

        return $this->compose($input, ['exec', $service, 'composer', 'install']);
    }
}
