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

namespace Crest\Tests\Database\Command\Make;

use Crest\Generator\ArtifactWriter;
use Crest\Tests\Database\AbstractDatabaseTestCase;
use Crest\Tests\Support\Migrations\MessagesMigration;
use Crest\Tests\Support\Migrations\SplitUsersMigration;
use Crest\Tests\Support\Migrations\TwoFactorCodesMigration;
use Crest\Tests\Support\Migrations\UserProfilesDashMigration;
use Crest\Tests\Support\Migrations\UsersMigration;
use Crest\Tests\Support\Project\DatabaseFront;
use Crest\Tests\Support\RunsMakeModel;
use Phalcon\Di\Di;

use function file_get_contents;
use function file_put_contents;
use function str_replace;
use function unlink;

use const PHP_EOL;

/**
 * The Note line of make:model for the old files of a table whose class now
 * comes from models.classes (#71). The tests run on each driver:
 * DatabaseFront gives the database of the suite. A test starts with no
 * tables.
 *
 * @group mysql
 * @group pgsql
 * @group sqlite
 */
final class ModelCommandOldFilesTest extends AbstractDatabaseTestCase
{
    use RunsMakeModel;

    /**
     * The end of the line for a table with no keys, after "No relations for <table>".
     */
    private const NO_KEYS = ': the table has no foreign keys, and no foreign keys point to it. '
        . 'Relations come only from foreign keys.';

    private const USER = "['classes' => ['users' => 'User']]";

    protected function setUp(): void
    {
        parent::setUp();
        $this->startScratchProject('make-model-old-files');
    }

    protected function tearDown(): void
    {
        // A boot makes a DI, and the first DI of the process is the default.
        Di::reset();
        $this->endScratchProject();
        parent::tearDown();
    }

    public function testAllGivesTheNoteUnderTheHeadingOfTheTable(): void
    {
        // The run writes messages too, so the note does not name it.
        $this->createTables(UsersMigration::class, MessagesMigration::class);
        $this->writeThenMap(['--all'], self::USER);

        $status = $this->runCommand(['--all']);

        $this->assertSame(0, $status);
        $this->assertStringEndsWith(
            'Table users' . PHP_EOL
            . 'Created ' . $this->model('Base/UserBase.php') . PHP_EOL
            . 'Created ' . $this->model('User.php') . PHP_EOL
            . $this->note()
            . PHP_EOL
            . '2 tables: 1 model created, 1 kept, 0 failed.' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAllWithExcludeNamesTheExcludedTable(): void
    {
        $this->createTables(UsersMigration::class, MessagesMigration::class);
        $this->writeThenMap(['--all'], self::USER);

        $status = $this->runCommand(['--all', '--exclude=messages']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Table users' . PHP_EOL
            . 'Created ' . $this->model('Base/UserBase.php') . PHP_EOL
            . 'Created ' . $this->model('User.php') . PHP_EOL
            . $this->note(', then run make:model for messages')
            . PHP_EOL
            . '1 table: 1 model created, 0 kept, 0 failed.' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testASecondRunGivesTheNoteAgain(): void
    {
        $this->createTables(UsersMigration::class);
        $this->writeThenMap(['users'], self::USER);
        $this->runCommand(['users']);
        $this->freshStreams();

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Kept ' . $this->model('User.php') . ': it exists; pass --force to replace it' . PHP_EOL
            . $this->note(),
            $this->readStdout()
        );
    }

    public function testNoNoteForALineWithTheDefaultClass(): void
    {
        $this->createTables(UsersMigration::class);
        $this->writeThenMap(['users'], "['classes' => ['users' => 'Users']]");

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Kept ' . $this->model('Users.php') . ': it exists; pass --force to replace it' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testNoNoteForALineWithTheDefaultClassInAnotherCase(): void
    {
        // PHP sees USERS and Users as one class. A file system that ignores
        // case sees the old files as the new files.
        $this->createTables(UsersMigration::class);
        $this->writeThenMap(['users'], "['classes' => ['users' => 'USERS']]");

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Created ' . $this->model('Base/USERSBase.php') . PHP_EOL
            . 'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Created ' . $this->model('USERS.php') . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testNoNoteForATableThatGivesNoClass(): void
    {
        // user-profiles gives no default class, so it has no old files.
        $this->front(DatabaseFront::class, "['classes' => ['user-profiles' => 'UserProfile']]");
        $this->createTables(UserProfilesDashMigration::class);

        $status = $this->runCommand(['user-profiles']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Created ' . $this->model('Base/UserProfileBase.php') . PHP_EOL
            . 'No relations for user-profiles' . self::NO_KEYS . PHP_EOL
            . 'Created ' . $this->model('UserProfile.php') . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testNoNoteWhenAnotherLineGivesTheOldClass(): void
    {
        // UsersBase.php can be the base of members.
        $this->createTables(UsersMigration::class);
        $this->writeThenMap(['users'], "['classes' => ['users' => 'User', 'members' => 'Users']]");

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame($this->createdUser(), $this->readStdout());
    }

    public function testNoNoteWhenAnotherTableGivesTheOldClass(): void
    {
        // us_ers gives UsErs, so UsersBase.php can be its base.
        $this->createTables(UsersMigration::class, SplitUsersMigration::class);
        $this->writeThenMap(['users'], self::USER);

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame($this->createdUser(), $this->readStdout());
    }

    public function testNoNoteWhenTheOldBaseHasNoMarker(): void
    {
        // A base without the marker belongs to the developer (#46).
        $this->createTables(UsersMigration::class);
        $this->writeThenMap(['users'], self::USER);

        $base = $this->model('Base/UsersBase.php');
        file_put_contents(
            $base,
            str_replace(ArtifactWriter::MARKER, 'Written by hand.', (string) file_get_contents($base))
        );

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame($this->createdUser(), $this->readStdout());
    }

    public function testNoNoteWhenTheOldBaseIsGone(): void
    {
        $this->createTables(UsersMigration::class);
        $this->writeThenMap(['users'], self::USER);
        unlink($this->model('Base/UsersBase.php'));

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame($this->createdUser(), $this->readStdout());
    }

    public function testNoNoteWithoutALineForTheTable(): void
    {
        $this->createTables(UsersMigration::class);
        $this->writeThenMap(['users'], "['classes' => ['orders' => 'Order']]");

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Kept ' . $this->model('Users.php') . ': it exists; pass --force to replace it' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testTheNoteNamesBothOldFiles(): void
    {
        $this->createTables(UsersMigration::class);
        $this->writeThenMap(['users'], self::USER);

        $before = [
            (string) file_get_contents($this->model('Users.php')),
            (string) file_get_contents($this->model('Base/UsersBase.php')),
        ];

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame($this->createdUser() . $this->note(), $this->readStdout());
        // crest deletes nothing, and changes nothing in the old files.
        $this->assertSame(
            $before,
            [
                (string) file_get_contents($this->model('Users.php')),
                (string) file_get_contents($this->model('Base/UsersBase.php')),
            ]
        );
    }

    public function testTheNoteNamesOnlyTheBaseWhenTheOldModelIsGone(): void
    {
        $this->createTables(UsersMigration::class);
        $this->writeThenMap(['users'], self::USER);
        unlink($this->model('Users.php'));

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame(
            $this->createdUser()
            . 'Note: the old file of users stays: ' . $this->model('Base/UsersBase.php')
            . '; move your code to User, delete it' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testTheNoteNamesTheRelatedTables(): void
    {
        // The base of messages has relations with the class Users.
        $this->createTables(UsersMigration::class, MessagesMigration::class);
        $this->writeThenMap(['users'], self::USER);

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertStringEndsWith(
            'Created ' . $this->model('User.php') . PHP_EOL
            . $this->note(', then run make:model for messages'),
            $this->readStdout()
        );
    }

    public function testTheNoteSkipsARelatedTableThatGivesNoClass(): void
    {
        // 2fa_codes has no base, and make:model stops for it.
        $this->createTables(UsersMigration::class, TwoFactorCodesMigration::class);
        $this->writeThenMap(['users'], self::USER);

        $status = $this->runCommand(['users']);

        $this->assertSame(0, $status);
        $this->assertSame(
            'Created ' . $this->model('Base/UserBase.php') . PHP_EOL
            . 'Skipped the relation hasMany 2fa_codes(user_id) -> users(id): '
            . "'2fa_codes' gives no usable class name; expected a table name like 'users' or 'user_profiles'"
            . PHP_EOL
            . 'Created ' . $this->model('User.php') . PHP_EOL
            . $this->note(),
            $this->readStdout()
        );
    }

    /**
     * The lines of make:model users for the new class User, with no keys.
     */
    private function createdUser(): string
    {
        return 'Created ' . $this->model('Base/UserBase.php') . PHP_EOL
            . 'No relations for users' . self::NO_KEYS . PHP_EOL
            . 'Created ' . $this->model('User.php') . PHP_EOL;
    }

    /**
     * A file in the `model` path of the scratch project.
     */
    private function model(string $file): string
    {
        return $this->root . '/src/Models/' . $file;
    }

    /**
     * The Note line for both old files of users, and the end of the line.
     */
    private function note(string $then = ''): string
    {
        return 'Note: the old files of users stay: ' . $this->model('Users.php') . ' and '
            . $this->model('Base/UsersBase.php') . '; move your code to User, delete them' . $then . PHP_EOL;
    }

    /**
     * make:model with no models key, so the model and the base of each
     * default class exist. Then the models key of the test, and empty
     * streams for the next run.
     *
     * @param list<string> $arguments
     */
    private function writeThenMap(array $arguments, string $models): void
    {
        $this->front(DatabaseFront::class);
        $this->runCommand($arguments);
        $this->front(DatabaseFront::class, $models);
        $this->freshStreams();
    }
}
