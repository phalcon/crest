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
use Crest\Project\Survey;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;

final class SurveyTest extends TestCase
{
    use ScratchDirectory;

    /**
     * The services of vokuro-adr: app, mysql, mailpit.
     */
    private const VOKURO_COMPOSE = "services:\n"
        . "  app:\n"
        . "    build:\n"
        . "      context: .\n"
        . "    volumes:\n"
        . "      - .:/srv\n"
        . "  mysql:\n"
        . "    image: mysql:8.0\n"
        . "  mailpit:\n"
        . "    image: axllent/mailpit\n";

    protected function setUp(): void
    {
        $this->makeScratchDirectory('survey', 'src');
        $this->writeComposerJson(['App\\' => 'src/']);
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function composeFiles(): iterable
    {
        yield 'compose.yaml' => ['compose.yaml'];
        yield 'compose.yml' => ['compose.yml'];
        yield 'docker-compose.yaml' => ['docker-compose.yaml'];
        yield 'docker-compose.yml' => ['docker-compose.yml'];
    }

    public function testACommentAfterTheServicesKeyIsRead(): void
    {
        file_put_contents($this->root . '/docker-compose.yml', "services: # the project\n  web:\n");

        $this->assertSame('web', Survey::settings($this->root)->runtime->service);
    }

    public function testACommentInTheBlockIsNotAService(): void
    {
        file_put_contents($this->root . '/docker-compose.yml', "services:\n  # the web: service\n  app:\n");

        $this->assertSame('app', Survey::settings($this->root)->runtime->service);
    }

    public function testAComposeFileWithoutAnAppServiceUsesTheFirstService(): void
    {
        file_put_contents(
            $this->root . '/docker-compose.yml',
            "services:\n  web:\n    image: php\n  db:\n    image: mysql\n"
        );

        $this->assertSame('web', Survey::settings($this->root)->runtime->service);
    }

    public function testAComposeFileWithoutServicesUsesApp(): void
    {
        file_put_contents($this->root . '/docker-compose.yml', "volumes:\n  data:\n");

        $runtime = Survey::settings($this->root)->runtime;

        $this->assertTrue($runtime->isDocker());
        $this->assertSame('app', $runtime->service);
    }

    public function testAFourSpaceIndentIsRead(): void
    {
        file_put_contents($this->root . '/docker-compose.yml', "services:\n    web:\n        image: php\n");

        $this->assertSame('web', Survey::settings($this->root)->runtime->service);
    }

    public function testAKeyThatEndsInServicesIsNotTheBlock(): void
    {
        file_put_contents($this->root . '/docker-compose.yml', "x-services:\n  app:\nservices:\n  web:\n");

        $this->assertSame('web', Survey::settings($this->root)->runtime->service);
    }

    public function testAppWinsWhenItIsNotTheFirstService(): void
    {
        file_put_contents(
            $this->root . '/docker-compose.yml',
            "services:\n  db:\n    image: mysql\n  app:\n    build: .\n"
        );

        $this->assertSame('app', Survey::settings($this->root)->runtime->service);
    }

    public function testATopLevelKeyAfterTheServicesEndsTheBlock(): void
    {
        file_put_contents(
            $this->root . '/docker-compose.yml',
            "services:\n  web:\n    image: php\n# a comment\nvolumes:\n  app:\n"
        );

        $this->assertSame('web', Survey::settings($this->root)->runtime->service);
    }

    public function testAVersionLineBeforeTheServicesIsRead(): void
    {
        file_put_contents($this->root . '/docker-compose.yml', "version: '3.8'\nservices:\n  web:\n");

        $this->assertSame('web', Survey::settings($this->root)->runtime->service);
    }

    public function testAVokuroLikeProject(): void
    {
        $this->writeComposerJson(['Vokuro\\' => 'src/']);
        file_put_contents($this->root . '/src/AppFront.php', "<?php\n");
        file_put_contents($this->root . '/docker-compose.yml', self::VOKURO_COMPOSE);

        $settings = Survey::settings($this->root);

        $this->assertSame('Vokuro', $settings->namespace);
        $this->assertSame('Vokuro\\AppFront', $settings->bootstrap);
        $this->assertSame('src/Action', $settings->paths['action']);
        $this->assertTrue($settings->runtime->isDocker());
        $this->assertSame('app', $settings->runtime->service);
    }

    /**
     * @dataProvider composeFiles
     */
    public function testEveryComposeFileNameIsFound(string $name): void
    {
        file_put_contents($this->root . '/' . $name, "services:\n  app:\n");

        $this->assertTrue(Survey::settings($this->root)->runtime->isDocker());
    }

    public function testLinesBeforeTheServicesBlockAreNotServices(): void
    {
        file_put_contents($this->root . '/docker-compose.yml', "# header\n  app:\nservices:\n  web:\n");

        $this->assertSame('web', Survey::settings($this->root)->runtime->service);
    }

    public function testNoComposeFileIsTheHost(): void
    {
        $this->assertFalse(Survey::settings($this->root)->runtime->isDocker());
    }

    public function testNoComposerJsonStops(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/^no usable psr-4 autoload entry found in composer\.json$/');

        Survey::settings($this->root . '/src');
    }

    public function testNoFrontControllerFileGivesNoBootstrap(): void
    {
        $this->assertNull(Survey::settings($this->root)->bootstrap);
    }

    public function testNoUsablePsr4EntryNamesEveryMissingDirectory(): void
    {
        $this->writeComposerJson(['Ghost\\' => 'missing/', 'Other\\' => 'lib']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'no usable psr-4 autoload entry found in composer.json; '
            . "these psr-4 directories do not exist: 'missing', 'lib'"
        );

        Survey::settings($this->root);
    }

    public function testSurroundingSlashesAreIgnored(): void
    {
        file_put_contents($this->root . '/composer.json', '{"autoload":{"psr-4":{"App\\\\":"/src/"}}}');

        $settings = Survey::settings($this->root);

        $this->assertSame('App', $settings->namespace);
        $this->assertSame('src/Action', $settings->paths['action']);
    }

    public function testTheFirstPsr4EntryWithAFolderWins(): void
    {
        $this->writeComposerJson(['Ghost\\' => 'missing/', 'App\\' => 'src/']);

        $this->assertSame('App', Survey::settings($this->root)->namespace);
    }

    public function testTheNestedKeysOfAServiceAreNotServices(): void
    {
        // `app` here is a key of the web service, not a service.
        file_put_contents(
            $this->root . '/docker-compose.yml',
            "services:\n  web:\n    environment:\n      app: x\n"
        );

        $this->assertSame('web', Survey::settings($this->root)->runtime->service);
    }

    public function testThePathsFollowTheNamespaceFolder(): void
    {
        mkdir($this->root . '/app');
        $this->writeComposerJson(['App\\' => 'app/']);

        $this->assertSame(
            [
                'action'     => 'app/Action',
                'command'    => 'app/Command',
                'middleware' => 'app/Middleware',
                'provider'   => 'app/Provider',
                'responder'  => 'app/Responder',
            ],
            Survey::settings($this->root)->paths
        );
    }
}
