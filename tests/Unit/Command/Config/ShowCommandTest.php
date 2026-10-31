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

namespace Crest\Tests\Unit\Command\Config;

use Crest\Command\Config\ShowCommand;
use Crest\Commands;
use Crest\Console\Kernel;
use Crest\Console\Registry;
use Crest\Tests\Support\CapturesOutput;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function preg_replace;
use function unlink;

use const PHP_EOL;

final class ShowCommandTest extends TestCase
{
    use CapturesOutput;
    use ScratchDirectory;

    protected function setUp(): void
    {
        $this->makeScratchDirectory('config-show', 'src/Action');
        $this->writeComposerJson(['App\\' => 'src/']);
        $this->writeCrestPhp();
        $this->captureStreams();
    }

    protected function tearDown(): void
    {
        $this->closeStreams();
        $this->removeScratchDirectory();
    }

    /**
     * An ADR or CLI project shows no models, also with a wrong key.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function otherFlavors(): iterable
    {
        yield 'adr' => ['adr', "['camelCase' => true, 'classes' => ['users' => 'User']]"];
        yield 'adr, a wrong key' => ['adr', "['camelCase' => 'yes']"];
        yield 'cli' => ['cli', "['camelCase' => true, 'classes' => ['users' => 'User']]"];
        yield 'cli, a wrong key' => ['cli', "['camelCase' => 'yes']"];
    }

    /**
     * One case for each message of ModelNames::of(): the models key as PHP
     * code, and the message.
     *
     * @return iterable<string, array{string, string}>
     */
    public static function wrongModelsKeys(): iterable
    {
        yield 'not an array' => ["'yes'", 'crest.php: models must be an array'];
        yield 'an unknown key' => [
            "['camelcase' => true]",
            "crest.php: models has an unknown key 'camelcase'; the keys are classes and camelCase",
        ];
        yield 'classes that are not an array' => [
            "['classes' => 'users']",
            'crest.php: models.classes must be an array of table => class',
        ];
        yield 'camelCase that is not a boolean' => [
            "['camelCase' => 'yes']",
            'crest.php: models.camelCase must be true or false',
        ];
        yield 'a class that is not a string' => [
            "['classes' => ['users' => 1]]",
            "crest.php: models.classes: the class of 'users' must be a string",
        ];
        yield 'a class that is not a name' => [
            "['classes' => ['user-profiles' => 'User Profile']]",
            "crest.php: models.classes: 'user-profiles' gives 'User Profile', which is not a usable class name",
        ];
        yield 'a reserved class' => [
            "['classes' => ['lists' => 'LIST']]",
            "crest.php: models.classes: 'lists' gives the class LIST, but PHP reserves that name",
        ];
        yield 'two tables, one class' => [
            "['classes' => ['users' => 'User', 'accounts' => 'User']]",
            'crest.php: models.classes: the tables accounts and users give the same class User',
        ];
        yield 'two tables, one class in two cases' => [
            "['classes' => ['users' => 'User', 'members' => 'USER']]",
            'crest.php: models.classes: the tables members and users give the classes USER and User, '
            . 'which PHP sees as the same class',
        ];
    }

    public function testADefaultPathIsNotReportedAsDeclared(): void
    {
        // Declaring `views` leaves `action` on its default. Marking the whole
        // block declared would say the project asked for something it did not.
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['views' => 'templates']];\n"
        );

        $this->runCommand();

        // Normalized: the column width now follows the longest default key, so
        // asserting the padding here would pin something this test is not about.
        $output = $this->normalized();

        $this->assertStringContainsString('action ' . $this->root . '/src/Action default', $output);
        $this->assertStringContainsString('views ' . $this->root . '/templates declared', $output);
    }

    /**
     * @dataProvider otherFlavors
     */
    public function testAnAdrOrCliProjectShowsNoModels(string $flavor, string $models): void
    {
        $this->writeModels($models, $flavor);

        $status = $this->runCommand();
        $output = $this->readStdout();

        $this->assertSame(0, $status);
        $this->assertDoesNotMatchRegularExpression('/^(camelCase|models|TABLE) /m', $output);
        $this->assertSame('', $this->readStderr());
    }

    public function testAnEmptyClassesKeyGivesNoClassTable(): void
    {
        $this->writeModels("['camelCase' => false, 'classes' => []]");

        $this->runCommand();

        $output = $this->normalized();

        $this->assertStringContainsString(
            'service app default' . PHP_EOL . 'camelCase false declared' . PHP_EOL,
            $output
        );
        // Nothing comes after the paths.
        $this->assertStringEndsWith('views ' . $this->root . '/themes/default default' . PHP_EOL, $output);
    }

    public function testAnEmptyCrestPhpShowsTheDefaults(): void
    {
        $status = $this->runCommand();

        $output = $this->readStdout();

        $this->assertSame(0, $status);
        $this->assertStringContainsString('Source: ' . $this->root . '/crest.php', $output);
        $this->assertStringContainsString('adr', $output);
        $this->assertStringContainsString('App', $output);
        $this->assertStringContainsString($this->root, $output);
    }

    public function testANullCamelCaseIsTheDefault(): void
    {
        $this->writeModels("['camelCase' => null]");

        $this->runCommand();

        $this->assertStringContainsString(
            'service app default' . PHP_EOL . 'camelCase false default' . PHP_EOL,
            $this->normalized()
        );
    }

    /**
     * @dataProvider wrongModelsKeys
     */
    public function testAWrongModelsKeyGivesAModelsRow(string $models, string $message): void
    {
        // make:model stops on the key; config:show shows the error and goes on.
        $this->writeModels($models);

        $status = $this->runCommand();
        $output = $this->normalized();

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'service app default' . PHP_EOL
            . 'models ' . $message . ' declared' . PHP_EOL
            . PHP_EOL
            . 'PATH LOCATION ORIGIN' . PHP_EOL,
            $output
        );
        $this->assertDoesNotMatchRegularExpression('/^(camelCase|TABLE) /m', $output);
        $this->assertSame('', $this->readStderr());
    }

    public function testDeclaredPathsAreListed(): void
    {
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['paths' => ['views' => 'templates']];\n"
        );

        $this->runCommand();

        $output = $this->readStdout();

        // The declared key and the surviving default both appear, resolved to
        // absolute locations.
        $this->assertStringContainsString($this->root . '/templates', $output);
        $this->assertStringContainsString($this->root . '/src/Action', $output);
    }

    public function testDeclaredValuesAreDistinguishedFromDefaults(): void
    {
        // namespace is declared; flavor and the paths are not, so they keep
        // their defaults and must read as default.
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['namespace' => 'Shop'];\n"
        );

        $this->runCommand();

        $output = $this->readStdout();

        $this->assertStringContainsString('declared', $output);
        $this->assertStringContainsString('default', $output);
    }

    public function testDefinitionNamesItselfConfigShow(): void
    {
        $this->assertSame('config:show', (new ShowCommand())->define()->getName());
    }

    public function testEveryValueIsMarkedDefaultWhenCrestPhpStatesNothing(): void
    {
        $this->runCommand();

        $output = $this->readStdout();

        $this->assertStringContainsString('default', $output);
        $this->assertStringNotContainsString('declared', $output);
    }

    public function testTheConfigFileIsNamedWhenOneWasUsed(): void
    {
        file_put_contents($this->root . '/crest.php', "<?php\n\nreturn [];\n");

        $this->runCommand();

        $this->assertStringContainsString($this->root . '/crest.php', $this->readStdout());
    }

    public function testTheFrontControllerAndTheRuntimeAreShown(): void
    {
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['bootstrap' => 'App\\\\AppFront', 'runtime' => ['type' => 'docker']];\n"
        );

        $this->runCommand();

        $output = $this->normalized();

        $this->assertStringContainsString('bootstrap App\\AppFront declared' . PHP_EOL, $output);
        $this->assertStringContainsString('runtime docker declared' . PHP_EOL, $output);
        $this->assertStringContainsString('service app default' . PHP_EOL, $output);
    }

    public function testTheModelsKeyIsShownForAnMvcProject(): void
    {
        // The lines are out of order, and PHP makes the tables 9 and 10
        // integer keys. The rows sort as strings, so 10 comes before 9.
        $this->writeModels(
            "['camelCase' => true, 'classes' => ['users' => 'User', '9' => 'Nine', "
            . "'user-profiles' => 'UserProfile', '10' => 'Ten']]"
        );

        $status = $this->runCommand();

        $expected = 'Source: ' . $this->root . '/crest.php' . PHP_EOL
            . PHP_EOL
            . 'ITEM VALUE ORIGIN' . PHP_EOL
            . 'root ' . $this->root . ' crest.php' . PHP_EOL
            . 'flavor mvc declared' . PHP_EOL
            . 'namespace App default' . PHP_EOL
            . 'bootstrap none default' . PHP_EOL
            . 'runtime host default' . PHP_EOL
            . 'service app default' . PHP_EOL
            . 'camelCase true declared' . PHP_EOL
            . PHP_EOL
            . 'PATH LOCATION ORIGIN' . PHP_EOL
            . 'command ' . $this->root . '/src/Command default' . PHP_EOL
            . 'controller ' . $this->root . '/src/Controllers default' . PHP_EOL
            . 'model ' . $this->root . '/src/Models default' . PHP_EOL
            . 'provider ' . $this->root . '/src/Providers default' . PHP_EOL
            . 'views ' . $this->root . '/themes/default default' . PHP_EOL
            . PHP_EOL
            . 'TABLE CLASS' . PHP_EOL
            . '10 Ten' . PHP_EOL
            . '9 Nine' . PHP_EOL
            . 'user-profiles UserProfile' . PHP_EOL
            . 'users User' . PHP_EOL;

        $this->assertSame(0, $status);
        $this->assertSame($expected, $this->normalized());
    }

    public function testTheWholeReportIsRenderedForADeclaredProject(): void
    {
        // Paths are declared out of alphabetical order, so the listing only
        // reads correctly because it is sorted rather than merged-and-printed.
        //
        // The flavor is mvc, which has the shared default path (`command`) and
        // its own (`controller`, `provider`), so they appear between the two
        // declared keys - `action` belongs to ADR alone.
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['flavor' => 'mvc', 'namespace' => 'Shop', "
            . "'paths' => ['views' => 'templates', 'admin' => 'backend']];\n"
        );

        $this->runCommand();

        $expected = 'Source: ' . $this->root . '/crest.php' . PHP_EOL
            . PHP_EOL
            . 'ITEM VALUE ORIGIN' . PHP_EOL
            . 'root ' . $this->root . ' crest.php' . PHP_EOL
            . 'flavor mvc declared' . PHP_EOL
            . 'namespace Shop declared' . PHP_EOL
            . 'bootstrap none default' . PHP_EOL
            . 'runtime host default' . PHP_EOL
            . 'service app default' . PHP_EOL
            . 'camelCase false default' . PHP_EOL
            . PHP_EOL
            . 'PATH LOCATION ORIGIN' . PHP_EOL
            . 'admin ' . $this->root . '/backend declared' . PHP_EOL
            . 'command ' . $this->root . '/src/Command default' . PHP_EOL
            . 'controller ' . $this->root . '/src/Controllers default' . PHP_EOL
            . 'model ' . $this->root . '/src/Models default' . PHP_EOL
            . 'provider ' . $this->root . '/src/Providers default' . PHP_EOL
            . 'views ' . $this->root . '/templates declared' . PHP_EOL;

        $this->assertSame($expected, $this->normalized());
    }

    public function testTheWholeReportIsRenderedForAnEmptyCrestPhp(): void
    {
        $this->runCommand();

        $expected = 'Source: ' . $this->root . '/crest.php' . PHP_EOL
            . PHP_EOL
            . 'ITEM VALUE ORIGIN' . PHP_EOL
            . 'root ' . $this->root . ' crest.php' . PHP_EOL
            . 'flavor adr default' . PHP_EOL
            . 'namespace App default' . PHP_EOL
            . 'bootstrap none default' . PHP_EOL
            . 'runtime host default' . PHP_EOL
            . 'service app default' . PHP_EOL
            . PHP_EOL
            . 'PATH LOCATION ORIGIN' . PHP_EOL
            . 'action ' . $this->root . '/src/Action default' . PHP_EOL
            . 'command ' . $this->root . '/src/Command default' . PHP_EOL
            . 'middleware ' . $this->root . '/src/Middleware default' . PHP_EOL
            . 'provider ' . $this->root . '/src/Provider default' . PHP_EOL
            . 'responder ' . $this->root . '/src/Responder default' . PHP_EOL;

        $this->assertSame($expected, $this->normalized());
    }

    public function testWithoutCrestPhpItStopsWithTheInitHint(): void
    {
        unlink($this->root . '/crest.php');

        $this->assertSame(1, $this->runCommand());
        $this->assertSame("crest: no crest.php found; run 'crest init'" . PHP_EOL, $this->readStderr());
    }

    /**
     * The report is column-aligned, and the scratch directory name varies per
     * run, so the padding does too. Collapsing runs of spaces lets the content
     * be asserted exactly without asserting the width.
     */
    private function normalized(): string
    {
        return (string) preg_replace('/ {2,}/', ' ', $this->readStdout());
    }

    private function runCommand(): int
    {
        $registry = (new Registry())->add('config:show', ShowCommand::class);
        $kernel   = new Kernel(
            Commands::NAME,
            $registry,
            Commands::PACKAGE,
            $this->stdout,
            $this->stderr,
            false
        );

        return $kernel->handle(['crest', 'config:show', '--directory', $this->root]);
    }

    /**
     * A crest.php with the flavor and the models key, given as PHP code.
     */
    private function writeModels(string $models, string $flavor = 'mvc'): void
    {
        file_put_contents(
            $this->root . '/crest.php',
            "<?php\n\nreturn ['flavor' => '" . $flavor . "', 'models' => " . $models . "];\n"
        );
    }
}
