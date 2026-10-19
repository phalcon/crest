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

use function array_keys;
use function file_put_contents;
use function setlocale;

use const LC_COLLATE;

final class StubTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory(
            'stubs',
            'packaged/adr',
            'packaged/shared',
            'project/resources/stubs/adr',
            'project/resources/stubs/shared'
        );
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    public function testAPackagedFlavorStubWinsOverAPackagedSharedStub(): void
    {
        file_put_contents($this->root . '/packaged/adr/command.stub', 'packaged flavor');
        file_put_contents($this->root . '/packaged/shared/command.stub', 'packaged shared');

        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertSame($this->root . '/packaged/adr/command.stub', $stub->resolve('adr', 'command'));
    }

    public function testAPackagedSharedStubIsTheLastLevel(): void
    {
        file_put_contents($this->root . '/packaged/shared/command.stub', 'packaged shared');

        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertSame($this->root . '/packaged/shared/command.stub', $stub->resolve('mvc', 'command'));
    }

    public function testAPlaceholderWithNoValueThrows(): void
    {
        // A published copy can keep a placeholder that crest no longer sends.
        // Without this, the raw placeholder goes into the generated file.
        file_put_contents($this->root . '/packaged/adr/action.stub', '{{ a }}|{{ b }}');

        $stub = new Stub($this->root . '/packaged');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($this->root . '/packaged/adr/action.stub has no value for {{ b }}');

        $stub->render('adr', 'action', ['a' => 'X']);
    }

    public function testAProjectFlavorStubWinsOverAProjectSharedStub(): void
    {
        file_put_contents($this->root . '/project/resources/stubs/adr/command.stub', 'project flavor');
        file_put_contents($this->root . '/project/resources/stubs/shared/command.stub', 'project shared');

        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertSame(
            $this->root . '/project/resources/stubs/adr/command.stub',
            $stub->resolve('adr', 'command')
        );
    }

    public function testAProjectSharedStubWinsOverAPackagedFlavorStub(): void
    {
        // A copy that the project published always wins over a packaged one,
        // also when a later crest packages a flavor stub with the same name.
        file_put_contents($this->root . '/project/resources/stubs/shared/command.stub', 'project shared');
        file_put_contents($this->root . '/packaged/adr/command.stub', 'packaged flavor');

        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertSame(
            $this->root . '/project/resources/stubs/shared/command.stub',
            $stub->resolve('adr', 'command')
        );
    }

    public function testAValueIsNotScannedForTheNextKeys(): void
    {
        // A value can have the text of a placeholder, for example a column
        // name of another table (#51). It goes into the file as it is.
        file_put_contents($this->root . '/packaged/adr/action.stub', '{{ a }}|{{ b }}');

        $stub = new Stub($this->root . '/packaged');

        $this->assertSame('{{ b }}|B', $stub->render('adr', 'action', ['a' => '{{ b }}', 'b' => 'B']));
    }

    public function testAValueWithAnUnknownPlaceholderDoesNotStop(): void
    {
        // The check for a missing value reads the stub, not the values.
        file_put_contents($this->root . '/packaged/adr/action.stub', '{{ a }}');

        $stub = new Stub($this->root . '/packaged');

        $this->assertSame('{{ x }}', $stub->render('adr', 'action', ['a' => '{{ x }}']));
    }

    public function testHasFindsAPackagedStub(): void
    {
        file_put_contents($this->root . '/packaged/adr/action.stub', 'packaged');

        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertTrue($stub->has('adr', 'action'));
    }

    public function testHasFindsAStubThatOnlyTheProjectHas(): void
    {
        file_put_contents($this->root . '/project/resources/stubs/adr/only-here.stub', 'project');

        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertTrue($stub->has('adr', 'only-here'));
    }

    public function testHasFindsAStubThatOnlyTheSharedFolderHas(): void
    {
        file_put_contents($this->root . '/packaged/shared/command.stub', 'packaged shared');

        $this->assertTrue((new Stub($this->root . '/packaged'))->has('mvc', 'command'));
    }

    public function testHasIsFalseForAStubThatNobodyHas(): void
    {
        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertFalse($stub->has('adr', 'nope'));
    }

    public function testHasPlaceholderNeedsTheWholePlaceholder(): void
    {
        // Each half of the placeholder, with its space, is not the
        // placeholder.
        file_put_contents($this->root . '/packaged/adr/action.stub', '{{columnMap }} {{ columnMap}}');

        $this->assertFalse((new Stub($this->root . '/packaged'))->hasPlaceholder('adr', 'action', 'columnMap'));
    }

    public function testHasPlaceholderReadsTheStubThatWins(): void
    {
        // The project copy wins, so only its placeholders count. A name
        // without the spaces of a placeholder is not a placeholder.
        file_put_contents($this->root . '/packaged/adr/action.stub', '{{ old }} {{ new }}');
        file_put_contents($this->root . '/project/resources/stubs/adr/action.stub', '{{ old }} {{new}} new');

        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->assertTrue($stub->hasPlaceholder('adr', 'action', 'old'));
        $this->assertFalse($stub->hasPlaceholder('adr', 'action', 'new'));
    }

    public function testPackagedFolderFallsBackToTheSharedFolder(): void
    {
        file_put_contents($this->root . '/packaged/shared/command.stub', 'shared');

        $this->assertSame(Stub::SHARED, (new Stub($this->root . '/packaged'))->packagedFolder('adr', 'command'));
    }

    public function testPackagedFolderIsNullWhenNoFolderHasTheStub(): void
    {
        $this->assertNull((new Stub($this->root . '/packaged'))->packagedFolder('adr', 'nope'));
    }

    public function testPackagedFolderPrefersTheFlavorFolder(): void
    {
        file_put_contents($this->root . '/packaged/adr/command.stub', 'flavor');
        file_put_contents($this->root . '/packaged/shared/command.stub', 'shared');

        $this->assertSame('adr', (new Stub($this->root . '/packaged'))->packagedFolder('adr', 'command'));
    }

    public function testPackagedFoldersAreInByteOrderOnEveryMachine(): void
    {
        // glob() sorts with the collation of the locale. Under en_US.UTF-8,
        // `action.stub` comes before `action-view.stub`; in byte order it is
        // the other way round. The shared names sort before the flavor names,
        // and still come after them.
        file_put_contents($this->root . '/packaged/adr/action.stub', '');
        file_put_contents($this->root . '/packaged/adr/action-view.stub', '');
        file_put_contents($this->root . '/packaged/shared/a.stub', '');
        file_put_contents($this->root . '/packaged/shared/a-b.stub', '');

        $previous = (string) setlocale(LC_COLLATE, '0');

        if (false === setlocale(LC_COLLATE, 'en_US.UTF-8', 'en_US.utf8')) {
            $this->markTestSkipped('the en_US.UTF-8 locale is not installed');
        }

        try {
            $folders = (new Stub($this->root . '/packaged'))->packagedFolders('adr');
        } finally {
            setlocale(LC_COLLATE, $previous);
        }

        $this->assertSame(['action-view', 'action', 'a-b', 'a'], array_keys($folders));
    }

    public function testPackagedFoldersKeepTheFlavorFolderOnTheSameName(): void
    {
        file_put_contents($this->root . '/packaged/adr/command.stub', '');
        file_put_contents($this->root . '/packaged/adr/middleware.stub', '');
        file_put_contents($this->root . '/packaged/shared/command.stub', '');
        file_put_contents($this->root . '/packaged/shared/guide.stub', '');

        $this->assertSame(
            ['command' => 'adr', 'middleware' => 'adr', 'guide' => Stub::SHARED],
            (new Stub($this->root . '/packaged'))->packagedFolders('adr')
        );
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

    public function testPackagedRootIsUsedWhenNoProjectRootIsGivenAtAll(): void
    {
        file_put_contents($this->root . '/packaged/adr/action.stub', 'packaged {{ class }}');

        $stub = new Stub($this->root . '/packaged');

        $this->assertSame('packaged X', $stub->render('adr', 'action', ['class' => 'X']));
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

    public function testUnknownStubThrows(): void
    {
        $stub = new Stub($this->root . '/packaged', $this->root . '/project');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("stub 'adr/nope' not found");

        $stub->resolve('adr', 'nope');
    }
}
