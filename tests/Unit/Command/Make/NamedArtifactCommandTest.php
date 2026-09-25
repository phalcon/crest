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

namespace Crest\Tests\Unit\Command\Make;

use Crest\Command\Make\NamedArtifactCommand;
use Crest\Console\Parsing\Definition;
use PHPUnit\Framework\TestCase;

final class NamedArtifactCommandTest extends TestCase
{
    public function testTheBaseDeclaresTheForceOption(): void
    {
        $option = $this->definition()->findOption('force');

        $this->assertNotNull($option);
        $this->assertSame('Overwrite an existing widget', $option->description);
    }

    public function testTheBaseDeclaresTheNameArgument(): void
    {
        $arguments = $this->definition()->getArguments();

        $this->assertCount(1, $arguments);
        $this->assertSame('name', $arguments[0]->name);
        $this->assertTrue($arguments[0]->required);
        $this->assertSame('Widget name, e.g. Blue', $arguments[0]->description);
    }

    public function testTheCommandNameComesFromTheKey(): void
    {
        $definition = $this->definition();

        $this->assertSame('make:widget', $definition->getName());
        $this->assertSame('Create a widget', $definition->getDescription());
    }

    /**
     * A generator that gives only what the base cannot know. handle() reads
     * the `name` argument and the --force option, so the base must declare
     * them.
     */
    private function definition(): Definition
    {
        $command = new class () extends NamedArtifactCommand {
            protected function description(): string
            {
                return 'Create a widget';
            }

            protected function example(): string
            {
                return 'Blue';
            }

            protected function key(): string
            {
                return 'widget';
            }

            protected function suffix(): string
            {
                return 'Widget';
            }
        };

        return $command->define();
    }
}
