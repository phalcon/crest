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

namespace Crest\Tests\Unit;

use Crest\Command\Make\NamedArtifactCommand;
use Crest\Command\Project;
use Crest\Command\ProjectCommand;
use Crest\Console\Command\Command;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;
use Crest\Project\ProjectContext;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

use function sort;
use function str_contains;

/**
 * The plugin API: what a package that adds commands may use. A new public
 * method fails these tests until its docblock says @internal, or the list
 * here grows on purpose.
 */
final class PluginApiTest extends TestCase
{
    public function testTheBaseClassesOfCrestAreInternal(): void
    {
        foreach ([ProjectCommand::class, NamedArtifactCommand::class] as $class) {
            $this->assertStringContainsString('@internal', $this->docblock($class), $class);
        }
    }

    public function testTheClassesOfTheApi(): void
    {
        foreach (
            [
                Command::class,
                Definition::class,
                Exception::class,
                Input::class,
                Output::class,
                Project::class,
                ProjectContext::class,
            ] as $class
        ) {
            $this->assertStringContainsString('@api', $this->docblock($class), $class);
        }
    }

    public function testTheMethodsOfDefinition(): void
    {
        $this->assertSame(
            ['argument', 'for', 'getArguments', 'getDescription', 'getName', 'getOptions', 'option'],
            $this->published(Definition::class)
        );
    }

    public function testTheMethodsOfOutput(): void
    {
        $this->assertSame(
            ['ask', 'choice', 'error', 'line', 'success', 'table', 'write'],
            $this->published(Output::class)
        );
    }

    public function testTheMethodsOfProject(): void
    {
        $this->assertSame(['context'], $this->published(Project::class));
    }

    /**
     * @param class-string $class
     */
    private function docblock(string $class): string
    {
        return (string) (new ReflectionClass($class))->getDocComment();
    }

    /**
     * The public methods of a class without @internal, sorted. The
     * constructor is not part of the API.
     *
     * @param class-string $class
     *
     * @return list<string>
     */
    private function published(string $class): array
    {
        $names = [];

        foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ('__construct' === $method->getName() || str_contains((string) $method->getDocComment(), '@internal')) {
                continue;
            }

            $names[] = $method->getName();
        }

        sort($names);

        return $names;
    }
}
