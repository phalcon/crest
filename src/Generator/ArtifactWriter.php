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

use function dirname;
use function file_put_contents;
use function is_dir;
use function is_file;
use function mkdir;
use function sprintf;

/**
 * Puts a generated file on disk: refuse to clobber, render, create the
 * directory, write.
 *
 * Every make:* command repeated this sequence verbatim, which meant one
 * mkdir() mode literal per command in the mutation-testing ignore list, and a
 * refuse-to-overwrite message that could drift between commands. One copy now.
 *
 * A collaborator rather than a base class on purpose: make:action derives its
 * target from the route convention and passes a different set of placeholders,
 * so a template method would have to expose that divergence as hooks. Composing
 * keeps every handle() readable top to bottom.
 */
final class ArtifactWriter
{
    public function __construct(
        private readonly Stub $stub,
        private readonly string $flavor,
    ) {
    }

    /**
     * Renders a stub into place.
     *
     * @param array<string, string> $replacements
     */
    public function render(string $file, string $name, array $replacements, bool $force): void
    {
        // is_file(), not file_exists(): the latter is also true of a directory,
        // which would report "already exists" and then fail to write.
        if (true === is_file($file) && false === $force) {
            throw new Exception(sprintf('%s already exists; pass --force to overwrite', $file));
        }

        self::write($file, $this->stub->render($this->flavor, $name, $replacements));
    }

    /**
     * Writes contents to a file, creating the directory if it is missing.
     *
     * Static because it needs nothing from the instance, which lets stub:publish
     * reuse it - that command copies rather than renders, so it has no stub of
     * its own to construct a writer around.
     */
    public static function write(string $file, string $contents): void
    {
        $directory = dirname($file);

        if (false === is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }

        file_put_contents($file, $contents);
    }
}
