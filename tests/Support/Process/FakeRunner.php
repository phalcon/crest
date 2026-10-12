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

use function array_values;
use function count;
use function min;

/**
 * Records each command, directory and environment, and returns the set exit
 * statuses in order. Runs nothing.
 */
final class FakeRunner implements Runner
{
    /** @var list<array{list<string>, string|null}> */
    public array $calls = [];

    /** @var list<array<string, string>> The environment of each call, in order. */
    public array $environments = [];

    /** @var list<int> */
    private readonly array $statuses;

    /**
     * Call n returns status n. After the last status, the last status
     * repeats. With no status, each call returns 0.
     */
    public function __construct(int ...$statuses)
    {
        $this->statuses = array_values($statuses);
    }

    public function run(array $command, ?string $directory = null, array $environment = []): int
    {
        $this->calls[]        = [$command, $directory];
        $this->environments[] = $environment;

        return $this->statuses[min(count($this->calls), count($this->statuses)) - 1] ?? 0;
    }
}
