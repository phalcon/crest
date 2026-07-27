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

namespace Crest\Tests\Support\Adr;

use Crest\Adr\CandidateSource;

/**
 * Returns canned candidates and records what it was asked. Deliberately not a
 * second implementation of the routing convention - it computes nothing.
 */
final class StubCandidateSource implements CandidateSource
{
    /** @var list<array{0: string, 1: string, 2: string}> */
    public array $calls = [];

    /**
     * @param list<string> $candidates
     */
    public function __construct(
        private readonly array $candidates,
    ) {
    }

    /**
     * @return list<string>
     */
    public function candidatesFor(string $baseNamespace, string $method, string $path): array
    {
        $this->calls[] = [$baseNamespace, $method, $path];

        return $this->candidates;
    }
}
