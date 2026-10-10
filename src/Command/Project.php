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
use Crest\Project\Config;
use Crest\Project\ProjectContext;

/**
 * The project of a call, by the root rule of all commands: the file that
 * --config names, or the nearest crest.php above --directory or the working
 * directory. Without crest.php, the command stops with the `crest init`
 * hint.
 *
 * A package that adds commands calls context(). The commands of crest call
 * config(), which gives the whole configuration.
 *
 * @api
 */
final class Project
{
    /**
     * The whole configuration. Its shape changes with crest, so a package
     * uses context().
     *
     * @internal
     */
    public static function config(Input $input): Config
    {
        return Config::discover(
            $input->optionStringOrNull('directory'),
            $input->optionStringOrNull('config')
        );
    }

    /**
     * The project of the call, as a package sees it.
     */
    public static function context(Input $input): ProjectContext
    {
        return self::config($input);
    }
}
