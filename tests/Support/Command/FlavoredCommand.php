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

namespace Crest\Tests\Support\Command;

use Crest\Command\ProjectCommand;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Project\Flavor;

/**
 * A project command for two flavors, ADR and MVC. No command of crest has two
 * flavors yet, and the error of the check must list each of them.
 */
final class FlavoredCommand extends ProjectCommand
{
    public function define(): Definition
    {
        return Definition::for('flavored', 'A project command for the adr and mvc flavors');
    }

    public function handle(Input $input, Output $output): int
    {
        $output->line('ran in ' . $this->config($input)->flavor()->value);

        return 0;
    }

    protected function flavors(): array
    {
        return [Flavor::ADR, Flavor::MVC];
    }
}
