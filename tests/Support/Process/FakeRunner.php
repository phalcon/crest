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

namespace Crest\Tests\Support\Process;

use Crest\Process\Runner;

/**
 * Records each command, directory and environment, and returns a set exit
 * status. Runs nothing.
 */
final class FakeRunner implements Runner
{
    /** @var list<array{list<string>, string|null}> */
    public array $calls = [];

    /** @var list<array<string, string>> The environment of each call, in order. */
    public array $environments = [];

    public function __construct(
        private readonly int $status = 0,
    ) {
    }

    public function run(array $command, ?string $directory = null, array $environment = []): int
    {
        $this->calls[]        = [$command, $directory];
        $this->environments[] = $environment;

        return $this->status;
    }
}
