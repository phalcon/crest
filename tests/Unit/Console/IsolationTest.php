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

namespace Crest\Tests\Unit\Console;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function dirname;
use function file_get_contents;
use function preg_match_all;
use function sprintf;
use function str_starts_with;

/**
 * Two boundaries that keep the console core independent of the tool built on it:
 *
 *  - Nothing under Crest\Console may name crest or reach into the rest of the
 *    tree.
 *  - Crest\Console\Parsing may never reach back into Crest\Console.
 */
final class IsolationTest extends TestCase
{
    private const FORBIDDEN_IN_CONSOLE = [
        'Crest\\Command',
        'Crest\\Project',
        'Crest\\ADR',
        'Crest\\Generator',
        'crest',
        'Crest ',
        // Matching is case-sensitive and has to stay that way - every file here
        // declares `namespace Crest\Console`. So the shouted form is listed
        // separately: a constant named CREST slipped past the lowercase needle
        // once, and naming a tool from inside this cluster is the one thing
        // these tests exist to prevent.
        'CREST',
    ];

    /**
     * @return iterable<string, array{string}>
     */
    public static function consoleFiles(): iterable
    {
        yield from self::filesUnder(dirname(__DIR__, 3) . '/src/Console');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function parsingFiles(): iterable
    {
        yield from self::filesUnder(dirname(__DIR__, 3) . '/src/Console/Parsing');
    }

    /**
     * @return iterable<string, array{string}>
     */
    private static function filesUnder(string $root): iterable
    {
        // SKIP_DOTS is not optional: without it the iterator descends into '.'
        // and never terminates.
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            yield $file->getPathname() => [$file->getPathname()];
        }
    }

    /**
     * @dataProvider consoleFiles
     */
    public function testConsoleFileCarriesNoCrestIdentity(string $path): void
    {
        $contents = (string) file_get_contents($path);

        foreach (self::FORBIDDEN_IN_CONSOLE as $needle) {
            $this->assertStringNotContainsString(
                $needle,
                $contents,
                sprintf('%s references "%s"; Crest\Console must stay tool-agnostic', $path, $needle)
            );
        }
    }

    /**
     * The arrow only ever points application -> parsing. Any
     * `use Crest\Console\X` that is not itself under Parsing reverses it.
     *
     * @dataProvider parsingFiles
     */
    public function testParsingFileNeverReachesIntoTheApplicationCluster(string $path): void
    {
        $contents = (string) file_get_contents($path);

        preg_match_all('/^use\s+(Crest\\\\Console\\\\[^;]+);/m', $contents, $matches);

        // Collected, then asserted once. Asserting inside the loop leaves every
        // file that imports nothing marked risky for performing no assertions.
        $violations = [];
        foreach ($matches[1] as $import) {
            if (false === str_starts_with($import, 'Crest\Console\Parsing\\')) {
                $violations[] = $import;
            }
        }

        $this->assertSame(
            [],
            $violations,
            sprintf('%s may not depend on the application cluster', $path)
        );
    }
}
