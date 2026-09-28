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
use function json_encode;
use function proc_close;
use function proc_open;
use function stream_get_contents;

use const PHP_BINARY;
use const PHP_EOL;

/**
 * Runs bin/crest as a child process, as a user does. The scratch root is a
 * project with a fake vendor/bin/crest that prints its arguments and exits
 * with status 7.
 */
final class BinaryTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('binary', 'vendor/bin');

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
        $this->removeScratchDirectory();
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
     *
     * @return array{int, string} The exit status and stdout.
     */
    private function crest(array $arguments): array
    {
        $process = proc_open(
            [PHP_BINARY, Paths::root() . '/bin/crest', ...$arguments],
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
