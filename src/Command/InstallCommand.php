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

/**
 * Runs composer install in the project container. On the host, use
 * `composer install` directly. This command exists because crest knows the
 * container.
 */
final class InstallCommand extends ComposeCommand
{
    /**
     * The service in docker-compose.yml. crest wrote that file
     * (project-compose.stub), so the name belongs to crest. NewCommand writes
     * this value into the stub.
     */
    public const SERVICE = 'app';

    public function define(): Definition
    {
        return Definition::for('install', 'Install composer dependencies in the project container');
    }

    public function handle(Input $input, Output $output): int
    {
        return $this->compose($input, ['exec', self::SERVICE, 'composer', 'install']);
    }
}
