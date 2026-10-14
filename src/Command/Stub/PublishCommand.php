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

use Crest\Command\NewCommand;
use Crest\Command\ProjectCommand;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\Project\Skeletons;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Project\Flavor;

use function file_get_contents;
use function is_file;
use function preg_match;
use function sprintf;
use function str_starts_with;

/**
 * Copies packaged stubs into the project so they can be edited.
 *
 * The stub lookup has always worked - a file under the project's override
 * directory wins over the packaged one - but nothing told anybody it was
 * there, and guessing the layout is not a reasonable ask. This command is
 * what makes the mechanism discoverable.
 *
 * Renders nothing, so it is the one generator-adjacent command with no stub of
 * its own and no flavor-specific behavior beyond which directories it reads.
 *
 * A shared stub goes into the shared folder of the project. When the flavor
 * folder of the project has a copy with the same name, that copy wins, and the
 * command prints a note that names it: an edit of the shared copy has no
 * effect then.
 *
 * A project stub goes into the directory that `new` reads. That directory is
 * not a project yet, so --flavor gives the flavor of the stub, as it does for
 * `new`; the default is adr. In a project, the flavor comes from crest.php,
 * and --flavor is an error.
 */
final class PublishCommand extends ProjectCommand
{
    /**
     * A packaged stub name. Hyphens are in because `action-view` is one.
     */
    private const NAME = '/^[A-Za-z0-9_-]+\z/';

    public function define(): Definition
    {
        return Definition::for('stub:publish', 'Copy packaged stubs into the project for editing')
            ->argument('name', false, 'A single stub, e.g. action. Omit to publish them all')
            ->option('flavor=s', 'The flavor of a project stub, before the project exists. Default: adr')
            ->option('force', 'Overwrite stubs the project has already published');
    }

    public function handle(Input $input, Output $output): int
    {
        $name  = $input->argumentString('name');
        $force = true === $input->option('force');
        $given = $input->optionStringOrNull('flavor');

        // A project stub has an effect only where `new` reads it. That
        // directory is not a project, so --flavor gives the flavor, as it
        // does for `new`.
        if (true === str_starts_with($name, Stub::PROJECT_PREFIX)) {
            $root   = NewCommand::parent($input);
            $flavor = Skeletons::for($given ?? Flavor::ADR->value)->flavor()->value;
        } elseif (null !== $given) {
            throw new Exception(
                '--flavor is only for a project stub; in a project, the flavor comes from crest.php'
            );
        } else {
            $config = $this->config($input);
            $root   = $config->root();
            $flavor = $config->flavor()->value;
        }

        foreach ($this->sources($flavor, $name) as $stub => $folder) {
            $target = Stub::overridePath($root, $folder, $stub);

            if (true === is_file($target) && false === $force) {
                $output->line(
                    sprintf('Skipped %s; it exists already, pass --force to overwrite', $target)
                );
            } else {
                ArtifactWriter::write(
                    $target,
                    (string) file_get_contents(Stub::packagedPath(Paths::stubs(), $folder, $stub))
                );

                $output->success(sprintf('Published %s', $target));
            }

            // A copy in the flavor folder of the project wins over the shared
            // copy, so an edit of the shared copy has no effect.
            $winner = Stub::overridePath($root, $flavor, $stub);

            if (Stub::SHARED === $folder && true === is_file($winner)) {
                $output->line(sprintf('Note: crest uses %s, because the flavor folder comes first', $winner));
            }
        }

        return 0;
    }

    protected function flavors(): array
    {
        return Flavor::cases();
    }

    /**
     * The end of the error for a stub that the flavor does not have. A project
     * stub gets its flavor from --flavor, so the error names the flavor that
     * has the stub. The given flavor does not have it, so the first flavor
     * that has it is another one. An artifact stub gets its flavor from
     * crest.php, and gets no hint.
     */
    private function hint(Stub $packaged, string $name): string
    {
        if (false === str_starts_with($name, Stub::PROJECT_PREFIX)) {
            return '';
        }

        foreach (Skeletons::flavors() as $other) {
            if (null !== $packaged->packagedFolder($other, $name)) {
                return sprintf('; the %s flavor has it: pass --flavor=%s', $other, $other);
            }
        }

        return '';
    }

    /**
     * The packaged stubs this run will copy, name => packaged folder. A stub
     * goes into the project folder with the same name, where the lookup finds
     * it.
     *
     * Reads the package rather than going through Stub::resolve():
     * resolution prefers a project override, and publishing an already-published
     * stub over itself is not a copy anyone asked for.
     *
     * @return array<string, string>
     */
    private function sources(string $flavor, string $name): array
    {
        $packaged = new Stub(Paths::stubs());

        if ('' !== $name) {
            // A name is a name, not a path. Without this, `stub:publish
            // ../../elsewhere/thing` resolves and copies a file from outside the
            // package - harmless on a developer's own machine, but the failure
            // it produces otherwise explains nothing.
            if (0 === preg_match(self::NAME, $name)) {
                throw new Exception(sprintf("'%s' is not a stub name", $name));
            }

            $folder = $packaged->packagedFolder($flavor, $name)
                ?? throw new Exception(
                    sprintf("stub '%s/%s' is not packaged%s", $flavor, $name, $this->hint($packaged, $name))
                );

            return [$name => $folder];
        }

        $found = [];

        // The project stubs have an effect only in the directory that the
        // project goes into, not in a project. The fragment stubs hold pieces
        // of the framework API and guidance text, and a copy does not get the
        // changes of later releases. Thus a publish with no name leaves both
        // out. A publish by name still copies them.
        foreach ($packaged->packagedFolders($flavor) as $stub => $folder) {
            if (
                true === str_starts_with($stub, Stub::PROJECT_PREFIX)
                || true === str_starts_with($stub, Stub::FRAGMENT_PREFIX)
            ) {
                continue;
            }

            $found[$stub] = $folder;
        }

        return $found;
    }
}
