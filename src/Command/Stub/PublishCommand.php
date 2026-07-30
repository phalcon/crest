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
            $target = $config->root() . '/' . Stub::OVERRIDE_DIRECTORY . '/'
                . Stub::relativePath($flavor, basename($source, '.stub'));

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
        $directory = Paths::stubs() . '/' . $flavor;

        if ('' !== $name) {
            $single = $directory . '/' . $name . '.stub';

            if (false === is_file($single)) {
                throw new Exception(sprintf("stub '%s/%s' is not packaged", $flavor, $name));
            }

            return [$single];
        }

        // glob() sorts alphabetically unless told not to, so the listing is
        // stable without a sort of its own.
        $found = glob($directory . '/*.stub') ?: [];

        if ([] === $found) {
            throw new Exception(sprintf("no stubs are packaged for the '%s' flavor", $flavor));
        }

        return $found;
    }
}
