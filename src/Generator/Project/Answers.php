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

namespace Crest\Generator\Project;

use Crest\Project\Flavor;
use Crest\Project\Runtime;

/**
 * The answers that `new` collects, from the options and the questions. The
 * skeleton of the flavor makes its files and values from them.
 */
final class Answers
{
    /**
     * @param string      $name    The project directory, e.g. my-app
     * @param string      $php     major.minor
     * @param string      $phalcon v5 or v6
     * @param string|null $engine  The view engine. Null when the flavor has
     *                             no engines.
     */
    public function __construct(
        public readonly string $name,
        public readonly Flavor $flavor,
        public readonly string $namespace,
        public readonly string $php,
        public readonly string $phalcon,
        public readonly Runtime $runtime,
        public readonly ?string $engine,
        public readonly DatabaseChoice $database,
    ) {
    }
}
