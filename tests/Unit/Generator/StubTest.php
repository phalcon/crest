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

namespace Crest\Tests\Unit\Generator;

use Crest\Console\Exceptions\Exception;
use Crest\Generator\Stub;
use Crest\Paths;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;

final class StubTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('stubs', 'packaged/adr', 'project/resources/stubs/adr');
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    public function testPackagedStubIsUsedWhenNoProjectOverrideExists(): void
    {
        file_put_contents($this->root . '/packaged/adr/action.stub', 'packaged {{ class }}');

        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertSame('packaged GetHealth', $stub->render('adr', 'action', ['class' => 'GetHealth']));
    }

    public function testProjectStubOverridesThePackagedOne(): void
    {
        file_put_contents($this->root . '/packaged/adr/action.stub', 'packaged');
        file_put_contents($this->root . '/project/resources/stubs/adr/action.stub', 'project {{ class }}');

        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertSame('project GetHealth', $stub->render('adr', 'action', ['class' => 'GetHealth']));
    }

    public function testShippedActionStubsExistAndAreValidPhp(): void
    {
        $stub = new Stub(Paths::stubs());

        foreach (['action', 'action-view'] as $name) {
            $rendered = $stub->render('adr', $name, [
                'namespace'  => 'App\Action\Health',
                'class'      => 'GetHealth',
                'attributes' => '',
                'params'     => '',
                'template'   => 'health/index',
            ]);

            $this->assertStringContainsString('declare(strict_types=1);', $rendered);
            $this->assertStringContainsString('namespace App\Action\Health;', $rendered);
            $this->assertStringContainsString('final class GetHealth implements Action', $rendered);
            $this->assertStringNotContainsString('readonly class', $rendered);
            $this->assertStringNotContainsString('{{', $rendered);
        }
    }

    public function testTrailingSlashesOnEitherRootAreIgnored(): void
    {
        file_put_contents($this->root . '/packaged/adr/action.stub', 'packaged');
        file_put_contents($this->root . '/project/resources/stubs/adr/action.stub', 'project');

        $stub = new Stub($this->root . '/packaged/', $this->root . '/project/');

        // Asserted on the resolved path, not the contents: a doubled separator
        // still opens the right file, so only the path itself shows the rtrim
        // is doing its job.
        $this->assertSame(
            $this->root . '/project/resources/stubs/adr/action.stub',
            $stub->resolve('adr', 'action')
        );
    }

    public function testPackagedRootIsUsedWhenNoProjectRootIsGivenAtAll(): void
    {
        file_put_contents($this->root . '/packaged/adr/action.stub', 'packaged {{ class }}');

        $stub = new Stub($this->root . '/packaged');

        $this->assertSame('packaged X', $stub->render('adr', 'action', ['class' => 'X']));
    }

    public function testPackagedRootIsAlsoStrippedOfATrailingSlash(): void
    {
        file_put_contents($this->root . '/packaged/adr/action.stub', 'packaged');

        $stub = new Stub($this->root . '/packaged/');

        $this->assertSame(
            $this->root . '/packaged/adr/action.stub',
            $stub->resolve('adr', 'action')
        );
    }

    public function testResolveReturnsTheWinningPath(): void
    {
        file_put_contents($this->root . '/packaged/adr/action.stub', 'packaged');
        file_put_contents($this->root . '/project/resources/stubs/adr/action.stub', 'project');

        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertSame(
            $this->root . '/project/resources/stubs/adr/action.stub',
            $stub->resolve('adr', 'action')
        );
    }

    public function testUnreplacedPlaceholdersAreLeftAlone(): void
    {
        file_put_contents($this->root . '/packaged/adr/action.stub', '{{ a }}|{{ b }}');

        $stub = new Stub($this->root . '/packaged');

        $this->assertSame('X|{{ b }}', $stub->render('adr', 'action', ['a' => 'X']));
    }

    public function testUnknownStubThrows(): void
    {
        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("stub 'adr/nope' not found");

        $stub->resolve('adr', 'nope');
    }
}
