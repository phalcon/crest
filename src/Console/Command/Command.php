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

namespace Crest\Console\Command;

use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;

/**
 * Every command is constructed with no arguments and resolves whatever it
 * needs inside handle(). That keeps the kernel free of any knowledge about
 * project config, flavors or generators.
 */
abstract class Command
{
    abstract public function define(): Definition;

    abstract public function handle(Input $input, Output $output): int;
}
