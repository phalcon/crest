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
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function fclose;
use function file_put_contents;
use function is_link;
use function json_encode;
use function mkdir;
use function proc_close;
use function proc_open;
use function stream_get_contents;
use function symlink;
use function unlink;

use const PHP_BINARY;
use const PHP_EOL;

/**
 * Runs bin/crest as a child process, as a user does. The scratch root is a
 * project with crest.php and a fake vendor/bin/crest that prints its
 * arguments and exits with status 7.
 */
final class BinaryTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('binary', 'vendor/bin');
        $this->writeCrestPhp();

        file_put_contents(
            $this->root . '/composer.json',
            (string) json_encode(['require-dev' => ['phalcon/crest' => 'dev-master']])
        );
        file_put_contents(
            $this->root . '/vendor/bin/crest',
            "<?php\n\necho 'project crest: ' . implode(' ', array_slice(\$argv, 1)) . PHP_EOL;\n\nexit(7);\n"
        );
    }

    protected function tearDown(): void
    {
        // safeDeleteDirectory() follows a symlink to its target. Remove the
        // links first.
        foreach (['global/vendor/phalcon/crest', 'vendor/phalcon/crest'] as $link) {
            if (true === is_link($this->root . '/' . $link)) {
                unlink($this->root . '/' . $link);
            }
        }

        $this->removeScratchDirectory();
    }

    public function testAGlobalCrestFromASymlinkedPathRepositoryPassesOn(): void
    {
        // A path repository with symlinks: the global install and the project
        // link vendor/phalcon/crest to the same checkout. The composer proxy
        // names the autoloader of the global install.
        mkdir($this->root . '/global/vendor/bin', 0o775, true);
        mkdir($this->root . '/global/vendor/phalcon', 0o775, true);
        mkdir($this->root . '/vendor/phalcon', 0o775, true);
        symlink(Paths::root(), $this->root . '/global/vendor/phalcon/crest');
        symlink(Paths::root(), $this->root . '/vendor/phalcon/crest');

        file_put_contents(
            $this->root . '/global/vendor/autoload.php',
            "<?php\n\necho 'global autoloader' . PHP_EOL;\n\n"
            . "return require '" . Paths::root() . "/vendor/autoload.php';\n"
        );
        file_put_contents(
            $this->root . '/global/vendor/bin/crest',
            "<?php\n\n\$GLOBALS['_composer_autoload_path'] = __DIR__ . '/../autoload.php';\n\n"
            . "return include __DIR__ . '/../phalcon/crest/bin/crest';\n"
        );

        [$status, $stdout] = $this->crest(['route:list'], $this->root . '/global/vendor/bin/crest');

        $this->assertSame(7, $status);
        $this->assertSame('global autoloader' . PHP_EOL . 'project crest: route:list' . PHP_EOL, $stdout);
    }

    public function testAProjectCommandRunsInTheCrestOfTheProject(): void
    {
        [$status, $stdout] = $this->crest(['route:list', '--trace']);

        $this->assertSame(7, $status);
        $this->assertSame('project crest: route:list --trace' . PHP_EOL, $stdout);
    }

    public function testTheVersionComesFromThisCrest(): void
    {
        [$status, $stdout] = $this->crest(['--version']);

        $this->assertSame(0, $status);
        $this->assertStringContainsString('crest', $stdout);
        $this->assertStringNotContainsString('project crest', $stdout);
    }

    /**
     * @param list<string> $arguments
     * @param string|null  $binary    The crest to run. Null for bin/crest.
     *
     * @return array{int, string} The exit status and stdout.
     */
    private function crest(array $arguments, ?string $binary = null): array
    {
        $process = proc_open(
            [PHP_BINARY, $binary ?? Paths::root() . '/bin/crest', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root
        );

        if (false === $process) {
            throw new RuntimeException('could not start bin/crest');
        }

        $stdout = (string) stream_get_contents($pipes[1]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout];
    }
}
