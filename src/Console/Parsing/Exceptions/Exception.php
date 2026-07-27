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

namespace Crest\Console\Parsing\Exceptions;

use RuntimeException;

/**
 * A schema or binding failure: an unknown option, a missing required argument,
 * a malformed option spec.
 */
class Exception extends RuntimeException
{
}
