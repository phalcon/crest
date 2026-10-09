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

namespace Crest\Command;

use Closure;
use Crest\Console\Exceptions\Exception;
use Crest\Console\Output;
use Crest\Generator\ClassName;
use Crest\Project\Runtime;

use function in_array;
use function preg_match;
use function sprintf;
use function strtolower;

/**
 * The questions that `crest init` and `crest new` share (D07). Each one shows
 * a proposal as its default, so an empty answer, or a run without
 * interaction, accepts it.
 */
final class Questions
{
    /**
     * The runtime types, in the order of the question.
     */
    private const RUNTIMES = [Runtime::HOST, Runtime::DOCKER];

    /**
     * A docker compose service name.
     */
    private const SERVICE = '/^[A-Za-z0-9][\w.-]*\z/';

    /**
     * The front controller class. An empty answer when there is no default
     * means none.
     */
    public static function bootstrap(Output $output, ?string $default): ?string
    {
        $class  = self::checkWith([ClassName::class, 'namespace']);
        $answer = $output->ask(
            'Front controller class (empty for none)',
            $default ?? '',
            static fn (string $answer): ?string => '' === $answer ? null : $class($answer)
        );

        return '' === $answer ? null : ClassName::namespace($answer);
    }

    /**
     * A check for Output::ask() from a validator that throws: the message of
     * the exception, or null when the validator passes.
     *
     * @param callable(string): mixed $validate
     *
     * @return Closure(string): ?string
     */
    public static function checkWith(callable $validate): Closure
    {
        return static function (string $answer) use ($validate): ?string {
            try {
                $validate($answer);
            } catch (Exception $exception) {
                return $exception->getMessage();
            }

            return null;
        };
    }

    public static function namespace(Output $output, string $default): string
    {
        return ClassName::namespace(
            $output->ask('Root namespace', $default, self::checkWith([ClassName::class, 'namespace']))
        );
    }

    /**
     * The runtime, and the service when it is docker. A given type or service
     * (an option of `crest new`) answers its question. It gets the same check
     * as an answer.
     */
    public static function runtime(
        Output $output,
        Runtime $default,
        ?string $type = null,
        ?string $service = null,
    ): Runtime {
        $type = null === $type
            ? $output->choice('Runtime', self::RUNTIMES, $default->type)
            : self::type($type);

        if (Runtime::HOST === $type) {
            return Runtime::host();
        }

        if (null !== $service) {
            self::service($service);

            return Runtime::docker($service);
        }

        return Runtime::docker(
            $output->ask('Docker compose service', $default->service, self::checkWith(self::service(...)))
        );
    }

    private static function service(string $service): void
    {
        if (0 === preg_match(self::SERVICE, $service)) {
            throw new Exception(sprintf("'%s' is not a usable service name", $service));
        }
    }

    /**
     * A given runtime type, in any case, as the list spells it.
     */
    private static function type(string $type): string
    {
        $lower = strtolower($type);

        if (false === in_array($lower, self::RUNTIMES, true)) {
            throw new Exception(sprintf("unknown runtime '%s'; expected host or docker", $type));
        }

        return $lower;
    }
}
