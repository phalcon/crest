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
                'template'   => 'health/index',
            ]);

            $this->assertStringContainsString('declare(strict_types=1);', $rendered);
            $this->assertStringContainsString('namespace App\Action\Health;', $rendered);
            $this->assertStringContainsString('final class GetHealth implements Action', $rendered);
            $this->assertStringNotContainsString('readonly class', $rendered);
            $this->assertStringNotContainsString('{{', $rendered);
        }
    }

    public function testUnknownStubThrows(): void
    {
        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("stub 'adr/nope' not found");

        $stub->resolve('adr', 'nope');
    }
}
