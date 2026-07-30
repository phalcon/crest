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

use Crest\ADR\Convention;
use Crest\ADR\PhalconRouterResolver;
use Crest\ADR\Target;
use Crest\Command\ProjectCommand;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\Stub;
use Crest\Paths;

use function implode;
use function sprintf;
use function str_replace;
use function strtolower;
use function trim;

/**
 * Generates an ADR Action and places it where the convention router will find
 * it. Boots nothing - it reads config and writes a file, which is why it keeps
 * working on a project that does not currently run.
 *
 * `--responder` picks between the two packaged shapes; `--stub` names any stub
 * instead, resolved through the same two-level chain, so a project that has run
 * stub:publish can generate from its own edited copy. An empty `--stub=` counts
 * as absent, which is how optionString() reads every other option.
 */
final class ActionCommand extends ProjectCommand
{
    private const RESPONDERS = ['json' => 'action', 'view' => 'action-view'];

    public function define(): Definition
    {
        return Definition::for('make:action', 'Create an ADR action for a route')
            ->argument('method', true, 'HTTP method, e.g. GET')
            ->argument('path', true, 'Route path, e.g. /company/{id}')
            ->option('responder=s', 'Responder style: json or view', 'json')
            ->option('stub=s', 'Render a named stub instead of the responder default')
            // No declared default: resolveOptions() supplies false for a flag
            // without consulting one, so passing it would state something that
            // is never read.
            ->option('force', 'Overwrite an existing action');
    }

    public function handle(Input $input, Output $output): int
    {
        $config = $this->config($input);

        $responder = strtolower($input->optionString('responder'));

        if (false === isset(self::RESPONDERS[$responder])) {
            throw new Exception(
                sprintf("unknown responder '%s'; expected json or view", $responder)
            );
        }

        $named = $input->optionString('stub');

        // Both choose a stub. Honouring one and dropping the other silently is
        // how someone spends an afternoon wondering why their stub is ignored.
        if ('' !== $named && true === $input->hasOption('responder')) {
            throw new Exception(
                '--stub and --responder both name a stub to render; pass one or the other'
            );
        }

        $convention = new Convention(
            $config->namespaceFor('action'),
            new PhalconRouterResolver()
        );
        $target     = $convention->target(
            $input->argumentString('method'),
            $input->argumentString('path')
        );

        $file = $config->path('action') . '/' . $target->relativePath;

        $writer = new ArtifactWriter(
            new Stub(Paths::stubs(), $config->root()),
            $config->flavor()->value
        );

        $writer->render(
            $file,
            '' !== $named ? $named : self::RESPONDERS[$responder],
            [
                'namespace'  => $target->namespace,
                'class'      => $target->class,
                'attributes' => $this->attributeBlock($target),
                'params'     => $this->paramsBlock($target),
                'template'   => $this->template($target),
            ],
            true === $input->option('force')
        );

        $output->success(sprintf('Created %s', $file));
        $output->line(sprintf('Answers %s %s', $target->method, $target->path));

        return 0;
    }

    /**
     * Placeholder segments become request attributes; pre-writing the reads
     * saves the user from looking up the accessor.
     */
    private function attributeBlock(Target $target): string
    {
        if ([] === $target->attributes) {
            return '';
        }

        $lines = [];
        foreach ($target->attributes as $name) {
            $lines[] = sprintf("        \$%s = \$request->getAttributes()->get('%s');", $name, $name);
        }

        return implode("\n", $lines) . "\n\n";
    }

    /**
     * The params() declaration for an Action's trailing attributes.
     *
     * Routing does not read this - the convention places arguments after the
     * static path regardless. It exists so the attributes arrive constrained,
     * cast and converted rather than as raw strings, which is why the emitted
     * block is a starting point the user is expected to tighten.
     */
    private function paramsBlock(Target $target): string
    {
        if ([] === $target->attributes) {
            return '';
        }

        $lines = [];
        foreach ($target->attributes as $name) {
            $lines[] = sprintf("            '%s' => ['type' => 'string'],", $name);
        }

        return "\n"
            . "    /**\n"
            . "     * Trailing route attributes, in path order. Constrains, casts and\n"
            . "     * converts them after the route has matched.\n"
            . "     */\n"
            . "    public static function params(): array\n"
            . "    {\n"
            . "        return [\n"
            . implode("\n", $lines) . "\n"
            . "        ];\n"
            . "    }\n";
    }

    private function template(Target $target): string
    {
        $path = trim(str_replace('{', '', str_replace('}', '', $target->path)), '/');

        return ('' === $path ? 'index' : $path) . '/index';
    }
}
