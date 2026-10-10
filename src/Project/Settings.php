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

namespace Crest\Project;

use function array_keys;
use function array_map;
use function max;
use function sprintf;
use function str_pad;
use function strlen;
use function var_export;

/**
 * The decisions of the user that crest.php holds: the root namespace, the
 * front controller, the paths and the runtime (D07). `crest new` and
 * `crest init` write crest.php from them through the project-config stub.
 */
final class Settings
{
    /**
     * @param array<string, string> $paths
     */
    public function __construct(
        public readonly string $namespace,
        public readonly ?string $bootstrap,
        public readonly array $paths,
        public readonly Runtime $runtime,
    ) {
    }

    /**
     * The values of the project-config stub. The stub stays plain string
     * replacement, so the parts that change in shape are whole lines here.
     *
     * @return array<string, string>
     */
    public function replacements(): array
    {
        return [
            'bootstrap' => null === $this->bootstrap
                ? ''
                : sprintf("    'bootstrap' => %s::class,\n", $this->bootstrap),
            'namespace' => $this->namespace,
            'paths'     => $this->pathLines(),
            'runtime'   => $this->runtime->render(),
        ];
    }

    /**
     * One line for each path. The keys line up, as in the rest of the file.
     */
    private function pathLines(): string
    {
        $lengths = array_map(static fn (string $key): int => strlen($key), array_keys($this->paths));
        $width   = max([0, ...$lengths]) + 2;
        $lines   = '';

        foreach ($this->paths as $key => $path) {
            $lines .= sprintf("        %s => %s,\n", str_pad("'" . $key . "'", $width), var_export($path, true));
        }

        return $lines;
    }
}
