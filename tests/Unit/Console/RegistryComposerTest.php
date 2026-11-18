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

use Composer\Autoload\ClassLoader;
use Crest\Console\Registry;
use Crest\Tests\Support\Console\FakeCommand;
use Crest\Tests\Support\Console\ThrowingCommand;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function fclose;
use function file_put_contents;
use function json_encode;
use function proc_close;
use function proc_open;
use function stream_get_contents;

/**
 * The command discovery reads what a real `composer install` writes: the
 * `extra` block of an installed package, and the one of the root project.
 * The other discovery tests write the same files by hand
 * (InstalledPackages).
 */
final class RegistryComposerTest extends TestCase
{
    use ScratchDirectory;

    private const KEY = 'crest-registry-test-key';

    private ?ClassLoader $loader = null;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('registry-composer', 'package');
    }

    protected function tearDown(): void
    {
        $this->loader?->unregister();
        $this->removeScratchDirectory();
    }

    public function testDiscoveryReadsWhatComposerInstalls(): void
    {
        file_put_contents(
            $this->root . '/package/composer.json',
            (string) json_encode([
                'name'    => 'crest-test/package',
                'version' => '1.0.0',
                'extra'   => [self::KEY => ['commands' => ['fake' => FakeCommand::class]]],
            ])
        );
        file_put_contents(
            $this->root . '/composer.json',
            (string) json_encode([
                'require'      => ['crest-test/package' => '1.0.0'],
                'repositories' => [
                    ['type' => 'path', 'url' => 'package', 'options' => ['symlink' => false]],
                    ['packagist.org' => false],
                ],
                'extra'        => [self::KEY => ['commands' => ['boom' => ThrowingCommand::class]]],
            ])
        );

        [$status, $output] = $this->composer(['install', '--no-interaction', '--no-progress', '--no-plugins']);

        $this->assertSame(0, $status, $output);

        // The autoloader of composer registers a class loader for each vendor
        // folder. This one stands for the autoloader of the project.
        $this->loader = new ClassLoader($this->root . '/vendor');
        $this->loader->register();

        $this->assertSame(
            ['fake' => FakeCommand::class, 'boom' => ThrowingCommand::class],
            (new Registry())->withDiscovery(self::KEY)->all()
        );
    }

    /**
     * Runs composer in the scratch folder. It needs no network: the root
     * turns packagist off, and the package comes from a path repository.
     *
     * @param list<string> $arguments
     *
     * @return array{int, string} The exit status, and stdout and stderr.
     */
    private function composer(array $arguments): array
    {
        $process = proc_open(
            ['composer', ...$arguments],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->root
        );

        if (false === $process) {
            throw new RuntimeException('could not start composer');
        }

        $output = (string) stream_get_contents($pipes[1]) . (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $output];
    }
}
