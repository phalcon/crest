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
    /**
     * Where a project keeps the stubs it has taken over, relative to its root.
     *
     * Public because stub:publish writes here and this class reads here. Two
     * copies of the convention that disagreed would put published stubs
     * somewhere resolution never looks - silent, and maddening to diagnose.
     */
    public const OVERRIDE_DIRECTORY = 'resources/stubs';

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

    /**
     * A stub's location under a package's stub root, or under a project's
     * override directory once OVERRIDE_DIRECTORY is prefixed.
     */
    public static function relativePath(string $flavor, string $name): string
    {
        return sprintf('%s/%s.stub', $flavor, $name);
    }

    public function resolve(string $flavor, string $name): string
    {
        $relative = self::relativePath($flavor, $name);

        if (null !== $this->projectRoot) {
            $override = $this->projectRoot . '/' . self::OVERRIDE_DIRECTORY . '/' . $relative;

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
