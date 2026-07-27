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

namespace Crest\Tests\Support;

use Phalcon\Talon\Traits\FileSystemTrait;

use function dirname;
use function file_put_contents;
use function json_encode;
use function mkdir;
use function uniqid;

use const JSON_PRETTY_PRINT;

/**
 * A throwaway project directory under tests/_output.
 *
 * This file lives in tests/Support, so tests/_output is exactly one level up -
 * the single place that knows where scratch space is, and no test has to count
 * dirname() levels for its own nesting depth.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait ScratchDirectory
{
    use FileSystemTrait;

    protected string $root = '';

    protected function makeScratchDirectory(string $prefix, string ...$subdirectories): void
    {
        $this->root = dirname(__DIR__) . '/_output/' . $prefix . '-' . uniqid('', false);

        mkdir($this->root, 0o775, true);

        foreach ($subdirectories as $subdirectory) {
            mkdir($this->root . '/' . $subdirectory, 0o775, true);
        }
    }

    protected function removeScratchDirectory(): void
    {
        $this->safeDeleteDirectory($this->root);
    }

    /**
     * @param array<string, string> $psr4
     */
    protected function writeComposerJson(array $psr4): void
    {
        file_put_contents(
            $this->root . '/composer.json',
            (string) json_encode(['autoload' => ['psr-4' => $psr4]], JSON_PRETTY_PRINT)
        );
    }
}
