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
 *
 * The static path builders exist so that stub:publish writes exactly where
 * resolve() reads. Nothing outside this class assembles a stub path.
 */
final class Stub
{
    /**
     * Where a project keeps the stubs it has taken over, relative to its root.
     *
     * Private: callers ask for a path rather than assembling one, so the layout
     * lives here alone. Two copies of the convention that drifted would put
     * published stubs somewhere resolution never looks - silent, and maddening
     * to diagnose.
     */
    private const OVERRIDE_DIRECTORY = 'resources/stubs';

    private ?string $projectRoot;

    private string $packagedRoot;

    /**
     * Roots are stored as given. Normalising a trailing slash is the path
     * builders' job, and doing it here as well would mean two places could be
     * changed independently while the tests still passed.
     */
    public function __construct(string $packagedRoot, ?string $projectRoot = null)
    {
        $this->packagedRoot = $packagedRoot;
        $this->projectRoot  = $projectRoot;
    }

    /**
     * Where a project's own copy of a stub lives - the path stub:publish writes
     * and resolve() prefers. Returned whether or not it exists.
     */
    public static function overridePath(string $projectRoot, string $flavor, string $name): string
    {
        return self::packagedPath(
            rtrim($projectRoot, '/') . '/' . self::OVERRIDE_DIRECTORY,
            $flavor,
            $name
        );
    }

    /**
     * The directory a package keeps a flavor's stubs in. The whole-directory
     * answer, for callers that enumerate rather than name one stub.
     */
    public static function packagedDirectory(string $packagedRoot, string $flavor): string
    {
        return rtrim($packagedRoot, '/') . '/' . $flavor;
    }

    /**
     * A stub's location as shipped in a package.
     */
    public static function packagedPath(string $packagedRoot, string $flavor, string $name): string
    {
        return self::packagedDirectory($packagedRoot, $flavor) . '/' . $name . '.stub';
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
        if (null !== $this->projectRoot) {
            $override = self::overridePath($this->projectRoot, $flavor, $name);

            if (true === is_file($override)) {
                return $override;
            }
        }

        $packaged = self::packagedPath($this->packagedRoot, $flavor, $name);

        if (true === is_file($packaged)) {
            return $packaged;
        }

        throw new Exception(sprintf("stub '%s/%s' not found", $flavor, $name));
    }
}
