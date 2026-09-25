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

namespace Crest\Tests\Support;

use Crest\Console\Command\Command;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;
use function str_replace;

/**
 * The tests that every Crest\Command\Make\NamedArtifactCommand must pass.
 *
 * A subclass sets the constants for its command and keeps only the tests that
 * are specific to that command. PHPUnit reports each test with the name of the
 * subclass, so a failure still identifies the command.
 */
abstract class NamedArtifactCommandTestCase extends TestCase
{
    use GeneratesInAScratchProject;

    /** @var class-string<Command> */
    protected const COMMAND = Command::class;

    /** @var string */
    protected const DECLARATION = '';

    /** @var string */
    protected const DIRECTORY = '';

    /** @var string */
    protected const NAME = '';

    /** @var string */
    protected const SUFFIX = '';

    protected function setUp(): void
    {
        $this->startScratchProject(str_replace(':', '-', static::NAME), static::DIRECTORY);
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    public function testAnUnusableNameIsReported(): void
    {
        $status = $this->runCommand(['Admin/Sample']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "'Admin/Sample' is not a usable class name",
            $this->readStderr()
        );
    }

    public function testCreatedPathIsReported(): void
    {
        $this->runCommand(['Sample']);

        $this->assertStringContainsString(
            'Created ' . $this->file('Sample' . static::SUFFIX),
            $this->readStdout()
        );
    }

    public function testDefinitionNamesItself(): void
    {
        $class = static::COMMAND;

        $this->assertSame(static::NAME, (new $class())->define()->getName());
    }

    public function testForceOverwritesAnExistingFile(): void
    {
        $file = $this->file('Sample' . static::SUFFIX);

        $this->runCommand(['Sample']);
        file_put_contents($file, 'stale');

        $status = $this->runCommand(['Sample', '--force']);

        $this->assertSame(0, $status);
        $this->assertStringNotContainsString('stale', (string) file_get_contents($file));
    }

    public function testNameArgumentIsRequired(): void
    {
        $status = $this->runCommand([]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("missing required argument 'name'", $this->readStderr());
    }

    public function testRefusesToOverwriteWithoutForce(): void
    {
        $this->runCommand(['Sample']);

        $status = $this->runCommand(['Sample']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('already exists', $this->readStderr());
    }

    public function testTheDirectoryIsCreatedWhenItIsAbsent(): void
    {
        // The configured path is only a default. A project that never had this
        // artifact does not have the directory.
        $this->safeDeleteDirectory($this->root . '/' . static::DIRECTORY);

        $status = $this->runCommand(['Sample']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->file('Sample' . static::SUFFIX));
    }

    public function testTheImportIsAliasedSoTheNameCanNeverCollide(): void
    {
        // The name that is only the suffix is the pathological case: the class
        // gets the same name as the contract or base class that the stub
        // imports. Without the alias, the generated file does not compile.
        $status = $this->runCommand([static::SUFFIX]);

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            static::DECLARATION,
            (string) file_get_contents($this->file(static::SUFFIX))
        );
    }

    public function testTheSuffixIsNotDoubledWhenTheUserSuppliesIt(): void
    {
        $status = $this->runCommand(['Sample' . static::SUFFIX]);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->file('Sample' . static::SUFFIX));
        $this->assertFileDoesNotExist($this->file('Sample' . static::SUFFIX . static::SUFFIX));
    }

    /**
     * @param list<string> $arguments
     */
    protected function runCommand(array $arguments): int
    {
        return $this->runProjectCommand(static::NAME, static::COMMAND, $arguments);
    }

    private function file(string $class): string
    {
        return $this->root . '/' . static::DIRECTORY . '/' . $class . '.php';
    }
}
