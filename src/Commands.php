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

namespace Crest;

use Crest\Command\AboutCommand;
use Crest\Command\Config\ShowCommand as ConfigShowCommand;
use Crest\Command\Container\ListCommand as ContainerListCommand;
use Crest\Command\DownCommand;
use Crest\Command\Event\ListCommand as EventListCommand;
use Crest\Command\InitCommand;
use Crest\Command\InstallCommand;
use Crest\Command\ListCommand;
use Crest\Command\Make\ActionCommand;
use Crest\Command\Make\CommandCommand;
use Crest\Command\Make\MiddlewareCommand;
use Crest\Command\Make\ProviderCommand;
use Crest\Command\Make\ResponderCommand;
use Crest\Command\NewCommand;
use Crest\Command\Route\ListCommand as RouteListCommand;
use Crest\Command\ServeCommand;
use Crest\Command\Stub\PublishCommand as StubPublishCommand;
use Crest\Command\UpCommand;
use Crest\Console\Registry;

/**
 * Crest's identity and command set. The console core is deliberately anonymous;
 * this class is what makes it crest.
 */
final class Commands
{
    /**
     * The host commands that run docker compose on the host. The launcher
     * that `crest new` writes (./crest) runs these on the host too, and
     * LauncherTest holds the launcher to this list.
     */
    public const COMPOSE = ['down', 'install', 'up'];

    /**
     * The commands that need no vendor/ of the project: `new` and `init`
     * write crest.php, and the COMPOSE commands run before composer
     * install. A global crest runs them itself, and passes the other
     * commands to the crest of the project.
     *
     * A closed set: the global crest and the crest of the project can be
     * different versions. A change here is a change of the hand-off
     * protocol (HandOff::PROTOCOL).
     */
    public const HOST = [...self::COMPOSE, 'init', 'new'];

    /**
     * Composer `extra` key packages use to contribute commands.
     */
    public const KEY = 'crest';

    /**
     * Tool name shown in errors, the banner and usage lines.
     */
    public const NAME = 'crest';

    /**
     * The commands that run on the host also with a docker runtime: `serve`
     * and its alias start PHP's built-in server, which must listen on the
     * host (A8). Part of the hand-off protocol, as HOST is.
     */
    public const ON_HOST = ['serve', 'server'];

    /**
     * Composer package name, used to resolve --version.
     */
    public const PACKAGE = 'phalcon/crest';

    /**
     * Command-name prefix => the package that provides those commands. When
     * the package is not installed, the error for such a command names it.
     * Strings only, so nothing breaks when a package changes its classes.
     */
    private const PROVIDERS = ['migration:' => 'phalcon/migrations'];

    /**
     * The registry the binary runs on: crest's own commands, plus anything
     * installed packages contribute through `extra.crest.commands`.
     */
    public static function registry(): Registry
    {
        return (new Registry())
            ->add('about', AboutCommand::class, 'info', 'i')
            ->add('config:show', ConfigShowCommand::class)
            ->add('container:list', ContainerListCommand::class)
            ->add('down', DownCommand::class)
            ->add('event:list', EventListCommand::class)
            ->add('init', InitCommand::class)
            ->add('install', InstallCommand::class)
            ->add('list', ListCommand::class, 'commands', 'enumerate')
            ->add('make:action', ActionCommand::class)
            ->add('make:command', CommandCommand::class)
            ->add('make:middleware', MiddlewareCommand::class)
            ->add('make:provider', ProviderCommand::class)
            ->add('make:responder', ResponderCommand::class)
            ->add('new', NewCommand::class)
            ->add('route:list', RouteListCommand::class)
            ->add('serve', ServeCommand::class, 'server')
            ->add('stub:publish', StubPublishCommand::class)
            ->add('up', UpCommand::class)
            ->withDiscovery(self::KEY)
            ->withProviders(self::PROVIDERS);
    }
}
