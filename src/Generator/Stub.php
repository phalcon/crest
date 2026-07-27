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

use Crest\Console\Exceptions\Exception;

use function file_get_contents;
use function is_file;
use function rtrim;
use function sprintf;
use function str_replace;

/**
 * Loads a stub through a two-level chain - project override, then the copy
 * shipped in the package - and substitutes placeholders. Plain string
 * replacement, deliberately not a template engine.
 */
final class Stub
{
    private ?string $projectRoot;

    private string $packagedRoot;

    public function __construct(string $packagedRoot, ?string $projectRoot = null)
    {
        $this->packagedRoot = rtrim($packagedRoot, '/');
        $this->projectRoot  = null === $projectRoot ? null : rtrim($projectRoot, '/');
    }

    /**
     * @param array<string, string> $replacements
     */
    public function render(string $flavor, string $name, array $replacements): string
    {
        $template = (string) file_get_contents($this->resolve($flavor, $name));

        foreach ($replacements as $key => $value) {
            $template = str_replace('{{ ' . $key . ' }}', $value, $template);
        }

        return $template;
    }

    public function resolve(string $flavor, string $name): string
    {
        $relative = sprintf('%s/%s.stub', $flavor, $name);

        if (null !== $this->projectRoot) {
            $override = $this->projectRoot . '/resources/stubs/' . $relative;

            if (true === is_file($override)) {
                return $override;
            }
        }

        $packaged = $this->packagedRoot . '/' . $relative;

        if (true === is_file($packaged)) {
            return $packaged;
        }

        throw new Exception(sprintf("stub '%s/%s' not found", $flavor, $name));
    }
}
