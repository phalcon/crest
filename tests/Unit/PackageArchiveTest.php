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

namespace Crest\Tests\Unit;

use Crest\Paths;
use PHPUnit\Framework\TestCase;

use function explode;
use function file_get_contents;
use function in_array;
use function json_decode;
use function preg_split;
use function sort;
use function str_starts_with;
use function trim;

use const JSON_THROW_ON_ERROR;
use const SORT_STRING;

/**
 * The files of the package. Packagist gives the zip of GitHub, which git
 * archive makes: it reads export-ignore in .gitattributes, not
 * archive.exclude in composer.json. composer archive reads both. So the two
 * lists are the same.
 */
final class PackageArchiveTest extends TestCase
{
    public function testTheGitAttributesIgnoreThePathsOfArchiveExclude(): void
    {
        $this->assertSame($this->excluded(), $this->ignored());
    }

    public function testThePackageKeepsTheStubs(): void
    {
        foreach ($this->ignored() as $path) {
            $this->assertFalse(str_starts_with('/resources/stubs/', $path . '/'), $path);
        }
    }

    /**
     * The paths of archive.exclude in composer.json, sorted.
     *
     * @return list<string>
     */
    private function excluded(): array
    {
        /** @var array{archive: array{exclude: list<string>}} $composer */
        $composer = json_decode(
            (string) file_get_contents(Paths::root() . '/composer.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $paths    = $composer['archive']['exclude'];

        sort($paths, SORT_STRING);

        return $paths;
    }

    /**
     * The paths with export-ignore in .gitattributes, sorted.
     *
     * @return list<string>
     */
    private function ignored(): array
    {
        $file = Paths::root() . '/.gitattributes';

        $this->assertFileExists($file);

        $paths = [];

        foreach (explode("\n", (string) file_get_contents($file)) as $line) {
            $parts = preg_split('/\s+/', trim($line)) ?: [];

            if (true === in_array('export-ignore', $parts, true)) {
                $paths[] = $parts[0];
            }
        }

        sort($paths, SORT_STRING);

        return $paths;
    }
}
