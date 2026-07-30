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

namespace Crest\Command\Stub;

use Crest\Command\ProjectCommand;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\Stub;
use Crest\Paths;

use function basename;
use function file_get_contents;
use function glob;
use function is_file;
use function preg_match;
use function sprintf;

/**
 * Copies packaged stubs into the project so they can be edited.
 *
 * The two-level stub chain has always worked - a file under the project's
 * override directory wins over the packaged one - but nothing told anybody it
 * was there, and guessing the layout is not a reasonable ask. This command is
 * what makes the mechanism discoverable.
 *
 * Renders nothing, so it is the one generator-adjacent command with no stub of
 * its own and no flavor-specific behavior beyond which directory it reads.
 */
final class PublishCommand extends ProjectCommand
{
    /**
     * A packaged stub name. Hyphens are in because `action-view` is one.
     */
    private const NAME = '/^[A-Za-z0-9_-]+$/';

    public function define(): Definition
    {
        return Definition::for('stub:publish', 'Copy packaged stubs into the project for editing')
            ->argument('name', false, 'A single stub, e.g. action. Omit to publish them all')
            ->option('force', 'Overwrite stubs the project has already published');
    }

    public function handle(Input $input, Output $output): int
    {
        $config = $this->config($input);

        $flavor = $config->flavor()->value;
        $name   = $input->argumentString('name');
        $force  = true === $input->option('force');

        foreach ($this->sources($flavor, $name) as $source) {
            $target = Stub::overridePath($config->root(), $flavor, basename($source, '.stub'));

            if (true === is_file($target) && false === $force) {
                $output->line(
                    sprintf('Skipped %s; it exists already, pass --force to overwrite', $target)
                );

                continue;
            }

            ArtifactWriter::write($target, (string) file_get_contents($source));

            $output->success(sprintf('Published %s', $target));
        }

        return 0;
    }

    /**
     * The packaged stubs this run will copy.
     *
     * Reads the packaged directory rather than going through Stub::resolve():
     * resolution prefers a project override, and publishing an already-published
     * stub over itself is not a copy anyone asked for.
     *
     * @return list<string>
     */
    private function sources(string $flavor, string $name): array
    {
        if ('' !== $name) {
            // A name is a name, not a path. Without this, `stub:publish
            // ../../elsewhere/thing` resolves and copies a file from outside the
            // package - harmless on a developer's own machine, but the failure
            // it produces otherwise explains nothing.
            if (0 === preg_match(self::NAME, $name)) {
                throw new Exception(sprintf("'%s' is not a stub name", $name));
            }

            $single = Stub::packagedPath(Paths::stubs(), $flavor, $name);

            if (false === is_file($single)) {
                throw new Exception(sprintf("stub '%s/%s' is not packaged", $flavor, $name));
            }

            return [$single];
        }

        // glob() sorts alphabetically unless told not to, so the listing is
        // stable without a sort of its own.
        $found = glob(Stub::packagedDirectory(Paths::stubs(), $flavor) . '/*.stub') ?: [];

        if ([] === $found) {
            throw new Exception(sprintf("no stubs are packaged for the '%s' flavor", $flavor));
        }

        return $found;
    }
}
