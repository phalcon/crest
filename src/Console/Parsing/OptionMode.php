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

namespace Crest\Console\Parsing;

/**
 * How an option consumes its value. The backing values are the devtools v4
 * shorthand carried into the spec syntax: `force`, `stub=s`, `output=s?`,
 * `fields=l`.
 */
enum OptionMode: string
{
    case List     = 'l';
    case None     = '';
    case Optional = 's?';
    case Required = 's';
}
