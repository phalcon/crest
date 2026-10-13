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

namespace Crest\Generator;

/**
 * What ArtifactWriter::renderOwned() and renderOnce() did with a file. The
 * command prints a line from it.
 */
enum Outcome
{
    case Created;
    case Kept;
    case Unchanged;
    case Updated;
}
