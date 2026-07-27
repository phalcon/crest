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

use RuntimeException;

use function fclose;
use function fopen;
use function rewind;
use function stream_get_contents;

/**
 * Captures a component's two output streams in memory so tests can assert on
 * what was written.
 *
 * The setup and teardown are explicit methods rather than setUp()/tearDown()
 * so this composes with ScratchDirectory in the tests that need both; two
 * traits each declaring setUp() is a fatal conflict.
 *
 * @mixin \PHPUnit\Framework\TestCase
 */
trait CapturesOutput
{
    /** @var resource */
    protected $stderr;

    /** @var resource */
    protected $stdout;

    protected function captureStreams(): void
    {
        $stdout = fopen('php://memory', 'rw');
        $stderr = fopen('php://memory', 'rw');

        // fopen() is resource|false; the properties are resource. Narrowing
        // here keeps every consumer free of the false case.
        if (false === $stdout || false === $stderr) {
            throw new RuntimeException('could not open an in-memory stream');
        }

        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    protected function closeStreams(): void
    {
        fclose($this->stdout);
        fclose($this->stderr);
    }

    protected function readStderr(): string
    {
        return $this->readStream($this->stderr);
    }

    protected function readStdout(): string
    {
        return $this->readStream($this->stdout);
    }

    /**
     * @param resource $handle
     */
    private function readStream($handle): string
    {
        rewind($handle);

        return (string) stream_get_contents($handle);
    }
}
