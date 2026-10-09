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

namespace Crest\Command;

use Crest\Commands;
use Crest\Console\Command\Command;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Project\Flavor;
use Crest\Project\Locator;
use Crest\Project\Manifest;
use Crest\Project\Questions;
use Crest\Project\Settings;
use Crest\Project\Survey;

use function is_file;
use function sprintf;

/**
 * Writes crest.php for a project that has none, for example a project that
 * moves from devtools (D07). It proposes the values that it finds in the
 * project, asks the questions, and writes the file through the stub of
 * `crest new`.
 *
 * A host command: the crest that the user typed runs it, because the project
 * has no crest.php yet, and maybe no vendor/.
 */
final class InitCommand extends Command
{
    public function define(): Definition
    {
        return Definition::for('init', 'Write crest.php for an existing project')
            ->option('force', 'Overwrite an existing crest.php');
    }

    public function handle(Input $input, Output $output): int
    {
        $root  = $this->root($input);
        $file  = $root . '/' . Locator::FILENAME;
        $force = true === $input->option('force');

        if (true === is_file($file) && false === $force) {
            throw new Exception(sprintf('%s exists; pass --force to overwrite', $file));
        }

        $proposal = Survey::settings($root);
        $settings = new Settings(
            Questions::namespace($output, $proposal->namespace),
            Questions::bootstrap($output, $proposal->bootstrap),
            $proposal->paths,
            Questions::runtime($output, $proposal->runtime)
        );

        ArtifactWriter::write(
            $file,
            (new Stub(Paths::stubs(), $root))->render(
                Flavor::ADR->value,
                Stub::PROJECT_PREFIX . 'config',
                $settings->replacements()
            )
        );

        $output->success(sprintf('Created %s', $file));

        if (false === Manifest::requires($root, Commands::PACKAGE)) {
            $output->line(sprintf('Next: composer require --dev %s', Commands::PACKAGE));
        }

        return 0;
    }

    /**
     * The nearest folder with a composer.json, from --directory or the
     * working directory. Locator::start() resolves the directory, so a
     * relative or missing directory cannot lead to another project.
     */
    private function root(Input $input): string
    {
        return Locator::project(Locator::start($input->optionStringOrNull('directory')))
            ?? throw new Exception('no composer.json found; crest init needs a composer project');
    }
}
