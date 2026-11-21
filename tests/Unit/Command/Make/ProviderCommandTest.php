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

use Crest\Command\Make\ProviderCommand;
use Crest\Tests\Support\NamedArtifactCommandTestCase;
use Crest\Tests\Support\Project\EmptyFront;
use Crest\Tests\Support\Project\InheritedProvidersFront;
use Crest\Tests\Support\Project\ProvidersFront;

use function file_get_contents;
use function file_put_contents;
use function spl_autoload_register;
use function spl_autoload_unregister;

use const PHP_EOL;

final class ProviderCommandTest extends NamedArtifactCommandTestCase
{
    /**
     * The crest.php of an ADR project whose front controller has no
     * registerProviders() of its own.
     *
     * @return iterable<string, array{string}>
     */
    public static function frontsWithNoOverride(): iterable
    {
        yield 'no front controller' => ["['flavor' => 'adr']"];
        yield 'a front that inherits registerProviders()' => [
            "['flavor' => 'adr', 'bootstrap' => \\" . InheritedProvidersFront::class . '::class]',
        ];
        yield 'a front with no registerProviders()' => [
            "['flavor' => 'adr', 'bootstrap' => \\" . EmptyFront::class . '::class]',
        ];
        yield 'a front that crest cannot load' => ["['flavor' => 'adr', 'bootstrap' => \\App\\Front\\Missing::class]"];
    }

    public function testACliProjectStopsWithBothFlavorsInTheError(): void
    {
        $this->writeCrestPhp("['flavor' => 'cli']");

        $status = $this->runCommand(['Mailer']);

        $this->assertSame(1, $status);
        $this->assertSame(
            "crest: make:provider does not support the 'cli' flavor of this project; "
            . 'it supports: adr, mvc' . PHP_EOL,
            $this->readStderr()
        );
        $this->assertFileDoesNotExist($this->root . '/src/Provider/MailerProvider.php');
        $this->assertFileDoesNotExist($this->root . '/src/Providers/MailerProvider.php');
    }

    public function testADeclaredProviderPathIsUsedInTheMvcGuidance(): void
    {
        // A project that keeps its classes in app/, as in
        // ControllerCommandTest. A stub or a fragment with a fixed namespace
        // fails here.
        $this->writeComposerJson(['Shop\\' => 'app/']);
        $this->writeCrestPhp("['flavor' => 'mvc', 'paths' => ['provider' => 'app/Services']]");

        $status = $this->runCommand(['Mailer']);

        $stdout = $this->readStdout();

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/app/Services/MailerProvider.php');
        $this->assertStringContainsString(
            'namespace Shop\Services;',
            (string) file_get_contents($this->root . '/app/Services/MailerProvider.php')
        );
        $this->assertStringContainsString('    new \Shop\Services\MailerProvider(),' . PHP_EOL, $stdout);
        $this->assertStringContainsString('$di->register(new \Shop\Services\MailerProvider()).' . PHP_EOL, $stdout);
    }

    public function testAFrontWhoseParentIsMissingGetsTheWholeOverride(): void
    {
        // The file of the front loads, but its parent class is not there.
        file_put_contents(
            $this->root . '/BrokenFront.php',
            "<?php\n\nnamespace Scratch;\n\nfinal class BrokenFront extends MissingFront\n{\n}\n"
        );

        $loader = function (string $class): void {
            if ('Scratch\\BrokenFront' === $class) {
                require $this->root . '/BrokenFront.php';
            }
        };

        spl_autoload_register($loader);
        $this->writeCrestPhp("['flavor' => 'adr', 'bootstrap' => \\Scratch\\BrokenFront::class]");

        try {
            $status = $this->runCommand(['Cache']);
        } finally {
            spl_autoload_unregister($loader);
        }

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            '    protected function registerProviders(Container $container): void' . PHP_EOL,
            $this->readStdout()
        );
    }

    public function testAFrontWithItsOwnOverrideGetsOnlyTheProvideLine(): void
    {
        // A second registerProviders() in the front is a fatal error
        // ("cannot redeclare"), so only the line to add is printed.
        $this->writeCrestPhp("['flavor' => 'adr', 'bootstrap' => \\" . ProvidersFront::class . '::class]');

        $this->runCommand(['Cache']);

        $expected = 'Created ' . $this->root . '/src/Provider/CacheProvider.php' . PHP_EOL
            . 'Nothing registers it yet. Add this line to registerProviders() in' . PHP_EOL
            . ProvidersFront::class . ', after the parent call:' . PHP_EOL
            . PHP_EOL
            . '        (new \App\Provider\CacheProvider())->provide($container);' . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }

    public function testAnMvcProjectGetsItsGuidanceWhateverItsFrontDeclares(): void
    {
        $this->writeCrestPhp("['flavor' => 'mvc', 'bootstrap' => \\" . ProvidersFront::class . '::class]');

        $this->runCommand(['Mailer']);

        $this->assertStringContainsString(
            'Nothing registers it yet. Add it to providers() in your front controller:',
            $this->readStdout()
        );
    }

    public function testAnMvcProjectGetsTheProvidersGuidance(): void
    {
        // Asserted whole: the first line is for the front controller of
        // crest new, the second for a project with another front controller.
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $this->runCommand(['Mailer']);

        $expected = 'Created ' . $this->root . '/src/Providers/MailerProvider.php' . PHP_EOL
            . 'Nothing registers it yet. Add it to providers() in your front controller:' . PHP_EOL
            . PHP_EOL
            . '    new \App\Providers\MailerProvider(),' . PHP_EOL
            . PHP_EOL
            . 'In a project with another front controller, register it with' . PHP_EOL
            . '$di->register(new \App\Providers\MailerProvider()).' . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }

    public function testAnMvcProjectGetsTheWholeDiProvider(): void
    {
        // Asserted whole, as the ADR provider is: a dropped use statement or a
        // method that does not agree with ServiceProviderInterface is a fatal
        // error in the project of the developer.
        $this->writeCrestPhp("['flavor' => 'mvc']");

        $status = $this->runCommand(['Mailer']);

        $expected = "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\Providers;\n"
            . "\n"
            . "use Phalcon\Di\DiInterface;\n"
            . "use Phalcon\Di\ServiceProviderInterface;\n"
            . "\n"
            . "final class MailerProvider implements ServiceProviderInterface\n"
            . "{\n"
            . "    public function register(DiInterface \$di): void\n"
            . "    {\n"
            . "        // Use a closure that is not static: the container binds each\n"
            . "        // closure to itself, and a static closure gives a warning there.\n"
            . "        //\n"
            . "        // \$di->setShared('thing', function (): Thing {\n"
            . "        //     return new Thing();\n"
            . "        // });\n"
            . "    }\n"
            . "}\n";

        $this->assertSame(0, $status);
        $this->assertSame(
            $expected,
            (string) file_get_contents($this->root . '/src/Providers/MailerProvider.php')
        );
    }

    /**
     * @dataProvider frontsWithNoOverride
     */
    public function testTheRegistrationSnippetIsPrintedWithTheParentCall(string $crestPhp): void
    {
        // The whole hint is the deliverable, asserted as one block: the blank
        // lines make it paste-able, and the parent:: line is what keeps the ADR
        // services registered. Losing either is a silent failure downstream.
        $this->writeCrestPhp($crestPhp);

        $this->runCommand(['Cache']);

        $expected = 'Created ' . $this->root . '/src/Provider/CacheProvider.php' . PHP_EOL
            . 'Nothing registers it yet. Call it from your front controller:' . PHP_EOL
            . PHP_EOL
            . '    protected function registerProviders(Container $container): void' . PHP_EOL
            . '    {' . PHP_EOL
            . '        parent::registerProviders($container);' . PHP_EOL
            . PHP_EOL
            . '        (new \App\Provider\CacheProvider())->provide($container);' . PHP_EOL
            . '    }' . PHP_EOL
            . PHP_EOL
            . 'Keep the parent call: it is what registers the ADR services.' . PHP_EOL;

        $this->assertSame($expected, $this->readStdout());
    }

    public function testTheWholeProviderIsRendered(): void
    {
        // Asserted whole rather than by substring: this is generated code nobody
        // reviews, so a dropped use statement or a mangled signature has to fail
        // here or it ships.
        $status = $this->runCommand(['Cache']);

        $expected = "<?php\n"
            . "\n"
            . "declare(strict_types=1);\n"
            . "\n"
            . "namespace App\Provider;\n"
            . "\n"
            . "use Phalcon\Contracts\Container\Service\Collection;\n"
            . "use Phalcon\Contracts\Container\Service\Provider as ProviderContract;\n"
            . "\n"
            . "final class CacheProvider implements ProviderContract\n"
            . "{\n"
            . "    public function provide(Collection \$services): void\n"
            . "    {\n"
            . "        // Concretes autowire, so only the seams need declaring:\n"
            . "        //\n"
            . "        // \$services->set(Thing::class, Thing::class);\n"
            . "        // \$services->bind(ThingInterface::class, Thing::class);\n"
            . "        // \$services->setAlias(ThingInterface::class, 'thing');\n"
            . "    }\n"
            . "}\n";

        $this->assertSame(0, $status);
        $this->assertSame(
            $expected,
            (string) file_get_contents($this->root . '/src/Provider/CacheProvider.php')
        );
    }

    protected function command(): string
    {
        return ProviderCommand::class;
    }

    protected function commandName(): string
    {
        return 'make:provider';
    }

    protected function declaration(): string
    {
        // Collection is left unaliased: no artifact suffix can produce that name.
        return 'final class Provider implements ProviderContract';
    }

    protected function directory(): string
    {
        return 'src/Provider';
    }

    protected function suffix(): string
    {
        return 'Provider';
    }
}
