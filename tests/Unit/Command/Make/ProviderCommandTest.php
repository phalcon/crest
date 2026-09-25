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

use function file_get_contents;

use const PHP_EOL;

final class ProviderCommandTest extends NamedArtifactCommandTestCase
{
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
