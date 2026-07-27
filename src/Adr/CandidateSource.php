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

namespace Crest\Adr;

/**
 * Where candidate Action class names come from. One method, so a flavor that
 * routes differently supplies its own implementation without touching the
 * generator.
 */
interface CandidateSource
{
    /**
     * Every Action class the router would try for this method and path, in try
     * order, unfiltered by existence.
     *
     * @return list<string>
     */
    public function candidatesFor(string $baseNamespace, string $method, string $path): array;
}
