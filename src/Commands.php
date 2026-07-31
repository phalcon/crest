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
use Crest\Command\Event\ListCommand as EventListCommand;
use Crest\Command\ListCommand;
use Crest\Command\Make\ActionCommand;
use Crest\Command\Make\CommandCommand;
use Crest\Command\Make\MiddlewareCommand;
use Crest\Command\Make\ProviderCommand;
use Crest\Command\Make\ResponderCommand;
use Crest\Command\Route\ListCommand as RouteListCommand;
use Crest\Command\Stub\PublishCommand as StubPublishCommand;
use Crest\Console\Registry;

/**
 * Crest's identity and command set. The console core is deliberately anonymous;
 * this class is what makes it crest.
 */
final class Commands
{
    /**
     * Composer `extra` key packages use to contribute commands.
     */
    public const KEY = 'crest';

    /**
     * Tool name shown in errors, the banner and usage lines.
     */
    public const NAME = 'crest';

    /**
     * Composer package name, used to resolve --version.
     */
    public const PACKAGE = 'phalcon/crest';

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
            ->add('event:list', EventListCommand::class)
            ->add('list', ListCommand::class, 'commands', 'enumerate')
            ->add('make:action', ActionCommand::class)
            ->add('make:command', CommandCommand::class)
            ->add('make:middleware', MiddlewareCommand::class)
            ->add('make:provider', ProviderCommand::class)
            ->add('make:responder', ResponderCommand::class)
            ->add('route:list', RouteListCommand::class)
            ->add('stub:publish', StubPublishCommand::class)
            ->withDiscovery(self::KEY);
    }
}
