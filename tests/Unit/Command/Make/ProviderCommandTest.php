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
use Crest\Tests\Support\GeneratesInAScratchProject;
use PHPUnit\Framework\TestCase;

use function file_get_contents;
use function file_put_contents;

use const PHP_EOL;

final class ProviderCommandTest extends TestCase
{
    use GeneratesInAScratchProject;

    protected function setUp(): void
    {
        $this->startScratchProject('make-provider', 'src/Provider');
    }

    protected function tearDown(): void
    {
        $this->endScratchProject();
    }

    public function testDefinitionNamesItselfMakeProvider(): void
    {
        $this->assertSame('make:provider', (new ProviderCommand())->define()->getName());
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

    public function testTheContractIsAliasedSoTheNameCanNeverCollide(): void
    {
        // `make:provider Provider` is the pathological case: the suffix is
        // already there, so the class is named Provider - and without the alias
        // the stub would emit `implements Provider` beside `use ...\Provider;`,
        // which does not compile. Collection is left unaliased: no artifact
        // suffix can produce that name.
        $status = $this->runCommand(['Provider']);

        $contents = (string) file_get_contents($this->root . '/src/Provider/Provider.php');

        $this->assertSame(0, $status);
        $this->assertStringContainsString(
            'final class Provider implements ProviderContract',
            $contents
        );
    }

    public function testTheSuffixIsNotDoubledWhenTheUserSuppliesIt(): void
    {
        $status = $this->runCommand(['CacheProvider']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/src/Provider/CacheProvider.php');
        $this->assertFileDoesNotExist($this->root . '/src/Provider/CacheProviderProvider.php');
    }

    public function testTheProviderDirectoryIsCreatedWhenItIsAbsent(): void
    {
        $this->safeDeleteDirectory($this->root . '/src/Provider');

        $status = $this->runCommand(['Cache']);

        $this->assertSame(0, $status);
        $this->assertFileExists($this->root . '/src/Provider/CacheProvider.php');
    }

    public function testTheRegistrationSnippetIsPrintedWithTheParentCall(): void
    {
        // The whole hint is the deliverable, asserted as one block: the blank
        // lines make it paste-able, and the parent:: line is what keeps the ADR
        // services registered. Losing either is a silent failure downstream.
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

    public function testRefusesToOverwriteWithoutForce(): void
    {
        $this->runCommand(['Cache']);

        $status = $this->runCommand(['Cache']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString('already exists', $this->readStderr());
    }

    public function testForceOverwritesAnExistingProvider(): void
    {
        $this->runCommand(['Cache']);
        file_put_contents($this->root . '/src/Provider/CacheProvider.php', 'stale');

        $status = $this->runCommand(['Cache', '--force']);

        $this->assertSame(0, $status);
        $this->assertStringNotContainsString(
            'stale',
            (string) file_get_contents($this->root . '/src/Provider/CacheProvider.php')
        );
    }

    public function testNameArgumentIsRequired(): void
    {
        $status = $this->runCommand([]);

        $this->assertSame(1, $status);
        $this->assertStringContainsString("missing required argument 'name'", $this->readStderr());
    }

    public function testAnUnusableNameIsReported(): void
    {
        $status = $this->runCommand(['Admin/Cache']);

        $this->assertSame(1, $status);
        $this->assertStringContainsString(
            "'Admin/Cache' is not a usable class name",
            $this->readStderr()
        );
    }

    public function testCreatedPathIsReported(): void
    {
        $this->runCommand(['Cache']);

        $this->assertStringContainsString(
            'Created ' . $this->root . '/src/Provider/CacheProvider.php',
            $this->readStdout()
        );
    }

    /**
     * @param list<string> $arguments
     */
    private function runCommand(array $arguments): int
    {
        return $this->runProjectCommand('make:provider', ProviderCommand::class, $arguments);
    }
}
