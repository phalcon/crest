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
use Crest\Project\Config;
use Crest\Project\Runtime;
use Crest\Project\Survey;

use function array_intersect;
use function explode;
use function in_array;
use function is_dir;
use function rtrim;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * The questions that `crest init` and `crest new` share (D07). Each one shows
 * a proposal as its default, so an empty answer, or a run without
 * interaction, accepts it.
 */
final class Questions
{
    /**
     * The answer that means no front controller.
     */
    private const NONE = 'none';

    /**
     * The runtime types, in the order of the question.
     */
    private const RUNTIMES = [Runtime::HOST, Runtime::DOCKER];

    /**
     * The front controller class. `none`, in any case, means no front
     * controller. Without a default, an empty answer also means none.
     */
    public static function bootstrap(Output $output, ?string $default): ?string
    {
        $class  = self::checkWith([ClassName::class, 'namespace']);
        $answer = $output->ask(
            'Front controller class (none for no front controller)',
            $default ?? '',
            static fn (string $answer): ?string => true === self::isNone($answer) ? null : $class($answer)
        );

        return true === self::isNone($answer) ? null : ClassName::namespace($answer);
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

    /**
     * The root namespace. With the root of a project (init), the answer must
     * be in the psr-4 map of its composer.json, so that the classes that
     * crest writes autoload.
     */
    public static function namespace(Output $output, string $default, ?string $root = null): string
    {
        $check = static function (string $answer) use ($root): void {
            $namespace = ClassName::namespace($answer);

            if (null !== $root) {
                Survey::folder($root, $namespace);
            }
        };

        return ClassName::namespace($output->ask('Root namespace', $default, self::checkWith($check)));
    }

    /**
     * The folder of one path key, relative to the root. The question says if
     * the proposal exists. init writes no `namespaces` key, so the answer for
     * a class folder must be in a psr-4 folder, and the namespace that it
     * gives must be usable: the first make:* writes classes with it. An
     * absolute or an empty folder is not in a psr-4 folder. A folder with no
     * classes (Config::NO_CLASS_PATHS, `views`) must only be a relative folder
     * in the project, with a name in each part. A leading `./` is removed.
     */
    public static function path(Output $output, string $root, string $key, string $default): string
    {
        $state  = true === is_dir($root . '/' . $default) ? 'exists' : 'does not exist yet';
        $folder = static function (string $answer) use ($root, $key, $default): string {
            $folder = rtrim($answer, '/');
            $folder = true === str_starts_with($folder, './') ? substr($folder, 2) : $folder;
            $parts  = explode('/', $folder);
            $up     = true === in_array('..', $parts, true);

            // A folder with no classes needs no psr-4 folder and no namespace.
            // Each part must be a name: an empty part is an empty answer, an
            // absolute folder or `//`.
            if (true === in_array($key, Config::NO_CLASS_PATHS, true)) {
                if ([] !== array_intersect($parts, ['', '.', '..'])) {
                    throw new Exception(
                        sprintf("'%s' must be a relative folder in the project, e.g. '%s'", $answer, $default)
                    );
                }

                return $folder;
            }

            $namespace = true === $up ? null : Survey::namespaceOf($root, $folder);

            if (null === $namespace) {
                throw new Exception(
                    sprintf(
                        "'%s' must be a relative folder in a psr-4 folder of composer.json, e.g. '%s'",
                        $answer,
                        $default
                    )
                );
            }

            try {
                ClassName::namespace($namespace);
            } catch (Exception) {
                throw new Exception(
                    sprintf(
                        "'%s' gives the namespace '%s', which is not usable; "
                        . "use folder names that are PHP names, e.g. '%s'",
                        $answer,
                        $namespace,
                        $default
                    )
                );
            }

            return $folder;
        };

        return $folder(
            $output->ask(sprintf('%s folder (%s)', $key, $state), $default, self::checkWith($folder))
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

    private static function isNone(string $answer): bool
    {
        return '' === $answer || self::NONE === strtolower($answer);
    }

    private static function service(string $service): void
    {
        if (false === Runtime::isService($service)) {
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
