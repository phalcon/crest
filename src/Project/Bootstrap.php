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

namespace Crest\Project;

use Crest\Console\Exceptions\Exception;
use Throwable;

use function class_exists;
use function is_object;
use function method_exists;
use function sprintf;

/**
 * Runs the project's own wiring and hands back its container.
 *
 * Routes and configuration can be read off the filesystem; services and
 * listeners cannot. They exist only once the application has registered them,
 * so a command that lists them has to boot the project.
 *
 * The project names its front controller in crest.php:
 *
 *   'bootstrap' => App\Front\ApiFront::class
 *
 * The front is already the single definition of how the application wires
 * itself, so naming it is the whole contract. Crest deliberately does not
 * accept a separate bootstrap file: that would mean restating the wiring
 * somewhere else, and a restatement nothing checks eventually disagrees - at
 * which point crest would report a container the application never runs with,
 * which is worse than reporting nothing.
 *
 * The requirement is a `boot()` returning a container, not a particular parent
 * class, so a project fronting its application its own way needs three lines
 * rather than an exemption.
 */
final class Bootstrap
{
    public static function container(Config $config): object
    {
        $class = $config->bootstrap();

        if (null === $class) {
            throw new Exception(
                'this command needs the project container; name the front controller '
                . "in crest.php, e.g. 'bootstrap' => App\\Front\\ApiFront::class"
            );
        }

        if (false === class_exists($class)) {
            throw new Exception(sprintf('front controller %s was not found', $class));
        }

        if (false === method_exists($class, 'boot')) {
            throw new Exception(
                sprintf(
                    '%s has no boot(); without one it cannot be started without also '
                    . 'serving a request',
                    $class
                )
            );
        }

        $front = new $class($config->root());

        // A boot that prints is a defect in the project, and it shows up in the
        // middle of the report where the developer will see it. Buffering it
        // away would hide someone else's bug and teach nobody anything.
        try {
            $container = $front->boot();
        } catch (Throwable $exception) {
            // Reported in crest's terms rather than as a bare stack trace. This
            // is not suppression: the message and the previous exception both
            // survive, and --trace prints the whole thing.
            throw new Exception(
                'the project failed to boot: ' . $exception->getMessage(),
                0,
                $exception
            );
        }

        if (false === is_object($container)) {
            throw new Exception(sprintf('%s::boot() did not return a container', $class));
        }

        return $container;
    }
}
