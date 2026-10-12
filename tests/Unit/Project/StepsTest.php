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

namespace Crest\Tests\Unit\Project;

use Crest\Console\Exceptions\Exception;
use Crest\Project\Steps;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

final class StepsTest extends TestCase
{
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('steps');
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function badEntries(): iterable
    {
        yield 'a string' => ['composer install'];
        yield 'an empty list' => [[]];
        yield 'a word that is not a string' => [['composer', 1]];
        yield 'an empty word' => [['composer', '']];
        yield 'string keys' => [['program' => 'composer']];
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function valuesThatAreNotLists(): iterable
    {
        yield 'a string' => ['composer install'];
        yield 'string keys' => [['install' => ['composer', 'install']]];
    }

    /**
     * @dataProvider badEntries
     */
    public function testABadEntryIsRefusedWithItsNumber(mixed $entry): void
    {
        // The number counts from 1, as a user counts the lines of the list.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'new' entry 2 in crest.php must be a list of strings, e.g. ['crest', 'up']");

        Steps::fromConfig([['composer', 'install'], $entry]);
    }

    public function testAListOfCommandsComesBackUnchanged(): void
    {
        $steps = [['composer', 'install'], ['crest', 'up'], ['php', '-r', 'echo 1;']];

        $this->assertSame($steps, Steps::fromConfig($steps));
    }

    public function testAnEmptyListIsNoSteps(): void
    {
        $this->assertSame([], Steps::fromConfig([]));
    }

    /**
     * @dataProvider valuesThatAreNotLists
     */
    public function testAValueThatIsNotAListIsRefused(mixed $declared): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("'new' in crest.php must be a list of commands, e.g. [['composer', 'install']]");

        Steps::fromConfig($declared);
    }

    public function testFromFileReadsOnlyTheNewKey(): void
    {
        // A newer crest can write a flavor that this crest does not know.
        $this->writeCrestPhp("['flavor' => 'future', 'new' => [['composer', 'install']]]");

        $this->assertSame([['composer', 'install']], Steps::fromFile($this->root . '/crest.php'));
    }

    public function testFromFileWithoutTheKeyIsNoSteps(): void
    {
        $this->writeCrestPhp();

        $this->assertSame([], Steps::fromFile($this->root . '/crest.php'));
    }

    public function testNoKeyIsNoSteps(): void
    {
        $this->assertSame([], Steps::fromConfig(null));
    }
}
