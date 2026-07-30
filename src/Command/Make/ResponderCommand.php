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

namespace Crest\Command\Make;

use Crest\Command\ProjectCommand;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\Stub;
use Crest\Paths;

use function sprintf;

/**
 * Generates an ADR Responder - the one layer that speaks HTTP, turning a domain
 * payload into a response.
 *
 * The generated class implements the contract directly rather than extending
 * AbstractFormattedResponder: that base composes a formatter chain, which is
 * the right answer for content negotiation and the wrong one to hand someone
 * who asked for a responder to fill in.
 *
 * Boots nothing - it reads config and writes a file, so it keeps working on a
 * project that does not currently run.
 */
final class ResponderCommand extends ProjectCommand
{
    private const KEY    = 'responder';
    private const SUFFIX = 'Responder';

    public function define(): Definition
    {
        return Definition::for('make:responder', 'Create an ADR responder')
            ->argument('name', true, 'Responder name, e.g. Album')
            // No declared default: resolveOptions() supplies false for a flag
            // without consulting one, so passing it would state something that
            // is never read.
            ->option('force', 'Overwrite an existing responder');
    }

    public function handle(Input $input, Output $output): int
    {
        $config    = $this->config($input);
        $placement = $this->placement($config, $input->argumentString('name'), self::KEY, self::SUFFIX);

        $writer = new ArtifactWriter(
            new Stub(Paths::stubs(), $config->root()),
            $config->flavor()->value
        );

        $writer->render(
            $placement->file,
            self::KEY,
            [
                'namespace' => $placement->namespace,
                'class'     => $placement->class,
            ],
            true === $input->option('force')
        );

        $output->success(sprintf('Created %s', $placement->file));

        return 0;
    }
}
