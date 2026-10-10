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

use Crest\ADR\ActionResolver;
use Crest\ADR\Convention;
use Crest\ADR\PhalconRouterResolver;
use Crest\ADR\Target;
use Crest\Command\ProjectCommand;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Generator\Stub;

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

    private readonly ActionResolver $resolver;

    /**
     * Defaulted, so the kernel's `new $class()` still works and nothing outside
     * has to know which resolver this command wants.
     *
     * Injectable for the same reason route:list is: the class name a route
     * produces is the framework's rule, and a test can only prove crest asked
     * for it - rather than derived it - by watching what it does with an answer
     * no local rule would give.
     */
    public function __construct(?ActionResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new PhalconRouterResolver();
    }

    public function define(): Definition
    {
        return Definition::for('make:action', 'Create an ADR action for a route')
            ->argument('method', true, 'HTTP method, e.g. GET')
            ->argument('path', true, 'Route path, e.g. /company/{id}')
            ->option('responder=s', 'Responder style: json or view', 'json')
            ->option('stub=s', 'Render a named stub instead of the responder default')
            ->option('template=s', 'Template the view responder renders; defaults to <path>/index')
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

        // Both choose a stub. Honoring one and dropping the other silently is
        // how someone spends an afternoon wondering why their stub is ignored.
        if ('' !== $named && true === $input->hasOption('responder')) {
            throw new Exception(
                '--stub and --responder both name a stub to render; pass one or the other'
            );
        }

        $convention = new Convention($config->namespaceFor('action'), $this->resolver);
        $target     = $convention->target(
            $input->argumentString('method'),
            $input->argumentString('path')
        );

        $file = $config->path('action') . '/' . $target->relativePath;

        $stub     = $this->stub($config);
        $flavor   = $config->flavor()->value;
        $writer   = $this->writer($config);
        $template = $this->template($target, $input->optionString('template'));

        $writer->render(
            $file,
            '' !== $named ? $named : self::RESPONDERS[$responder],
            [
                'namespace'  => $target->namespace,
                'class'      => $target->class,
                'attributes' => $this->attributeBlock($stub, $flavor, $target),
                'params'     => $this->paramsBlock($stub, $flavor, $target),
                'template'   => $template,
            ],
            true === $input->option('force')
        );

        $output->success(sprintf('Created %s', $file));
        $output->line(sprintf('Answers %s %s', $target->method, $target->path));

        // Only for the packaged view stub. A --stub the project supplied may or
        // may not render a template, and crest does not know which, so it says
        // nothing rather than guessing.
        if ('view' === $responder) {
            $output->write(
                $stub->render($flavor, Stub::FRAGMENT_PREFIX . 'guidance-action-view', ['template' => $template])
            );
        }

        return 0;
    }

    /**
     * Placeholder segments become request attributes; pre-writing the reads
     * saves the user from looking up the accessor. One
     * fragment-action-attribute for each attribute, then a blank line before
     * the body of the stub.
     */
    private function attributeBlock(Stub $stub, string $flavor, Target $target): string
    {
        if ([] === $target->attributes) {
            return '';
        }

        return $this->eachAttribute($stub, $flavor, 'action-attribute', $target) . "\n";
    }

    /**
     * One fragment for each attribute, in path order.
     */
    private function eachAttribute(Stub $stub, string $flavor, string $fragment, Target $target): string
    {
        $text = '';

        foreach ($target->attributes as $name) {
            $text .= $stub->render($flavor, Stub::FRAGMENT_PREFIX . $fragment, ['name' => $name]);
        }

        return $text;
    }

    /**
     * The params() declaration for an Action's trailing attributes.
     *
     * Routing does not read this - the convention places arguments after the
     * static path regardless. It exists so the attributes arrive constrained,
     * cast and converted rather than as raw strings, which is why the emitted
     * block is a starting point the user is expected to tighten. The shape is
     * the framework's: fragment-action-params and fragment-action-param hold
     * it.
     */
    private function paramsBlock(Stub $stub, string $flavor, Target $target): string
    {
        if ([] === $target->attributes) {
            return '';
        }

        return $stub->render(
            $flavor,
            Stub::FRAGMENT_PREFIX . 'action-params',
            ['entries' => $this->eachAttribute($stub, $flavor, 'action-param', $target)]
        );
    }

    /**
     * The template name the view responder renders.
     *
     * Derived from the route unless the caller names one. The derivation is
     * crest's own convention and not the framework's - withTemplate() accepts
     * any string, and Renderer::render() defines neither directory nor
     * extension - so --template exists to replace a guess rather than leave
     * someone renaming the file afterwards.
     */
    private function template(Target $target, string $named): string
    {
        if ('' !== $named) {
            return $named;
        }

        $path = trim(str_replace('{', '', str_replace('}', '', $target->path)), '/');

        return ('' === $path ? 'index' : $path) . '/index';
    }
}
