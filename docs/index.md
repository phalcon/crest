# Crest

Command line application for Phalcon.

See the [README](../README.md) for installation, usage and configuration.

## Commands

Aliases are shown in brackets. Run `crest` with no arguments, or `crest list`,
for the same listing from the tool itself.

| Command | Description |
|---|---|
| `about` (`info`, `i`) | environment and version report |
| `config:show` | the project configuration crest resolved, and where each value came from |
| `container:list` | services registered in the project container |
| `down` | stop and remove the project containers |
| `event:list` | listeners attached to the project events manager |
| `init` | write `crest.php` for an existing project |
| `install` | install composer dependencies in the project container |
| `list` (`commands`, `enumerate`) | the available commands |
| `make:action` | create an ADR action for a route |
| `make:command` | create a crest command |
| `make:middleware` | create an ADR middleware |
| `make:provider` | create a service provider |
| `make:responder` | create an ADR responder |
| `new` | create an ADR project |
| `route:list` | every route the application answers |
| `serve` (`server`) | start PHP's built-in web server for the project |
| `stub:publish` | copy packaged stubs into the project for editing |
| `up` | start the project containers |

Only the `adr` flavor has generators. A `cli` or `mvc` project can still run
`about`, `config:show` and `list`.

## Creating a project

`new` writes an ADR project that runs:

    crest new my-app

| Option | Purpose |
|---|---|
| `--namespace=<name>` | root namespace for the generated code; defaults to `App` |
| `--php=<major.minor>` | PHP version for `composer.json` and the Dockerfile; defaults to `8.4`, and must be 8.1 or later |
| `--phalcon=v5\|v6` | `v5` requires the C extension, 5.18 or later; `v6` the `phalcon/phalcon` package; defaults to `v5` |
| `--runtime=host\|docker` | where the project commands run (the `runtime` key of `crest.php`); defaults to `docker` |
| `--service=<name>` | the docker compose service, for `docker`; defaults to `app` |
| `--force` | write into a directory that is not empty, and overwrite files with the same names |

In a terminal, `new` asks for each value that no option gives: the namespace, the PHP
version, the Phalcon version, and the runtime (`docker` with the service `app`, or
`host`). An empty answer takes the default. `--no-interaction`, or a run without a
terminal (CI, a pipe), asks nothing and takes the defaults. The options stay, for CI.

The project goes into the working directory, or into `--directory` if you give
one. By default, `new` runs nothing: no composer, no docker, no network. It
prints the next steps:

    cd my-app
    ./crest up        docker compose up -d
    ./crest install   composer install in the app container

`./crest` is a launcher script that `new` writes into the project. It needs docker
only: PHP on the host is not necessary. `up`, `down` and `install` run docker compose on
the host. All other commands run `vendor/bin/crest` in the service of the project (`app`,
or the `--service` of `new`). Before `./crest install`, they stop with
`crest: run './crest install' first`. Without a terminal (CI, a pipe), the launcher
adds `-T` to `docker compose exec`. On Windows, run it in WSL2. With a global crest,
`crest up` and `crest install` do the same. With rootless docker, set `UID=0` and `GID=0`
in `.env`: rootless docker maps root in the container to your user.

The crest commands of the launcher get `CREST_HOST_ROOT`, the host folder of the
project, so crest shows host paths, not the paths in the container. The hand-off of a
global crest does the same. The launcher runs `vendor/bin/crest`. If you change
`vendor-dir` or `bin-dir` in `composer.json`, change the `crest=` line at the top of
`./crest`.

`crest down` stops and removes the containers. `up --build` rebuilds the image
first, and `down --volumes` also removes the named volumes.

Without docker, run `composer install`, then `crest serve`. It runs
`php -S 127.0.0.1:8080 -t public .htrouter.php` in the project root, with the
same router and document root as the container. The port is `--port`, else
`APP_PORT` from the environment, else `APP_PORT` in the project `.env`, else
8080. docker compose reads `APP_PORT` in the same order. `serve` reads `.env`
as docker compose does: the last `APP_PORT` line wins, and `export`,
`APP_PORT: <port>`, quotes and `#` comments are allowed. It does not expand
`${...}`. Such a value stops `serve` with an error. `serve` finds the root
from a subdirectory. A global crest passes it to the crest in `vendor/`, as
it does the other project commands. It stops before PHP starts if
`.htrouter.php` or `<vendor-dir>/autoload.php` is missing.

The server uses the PHP that runs crest. PHP options on the command line, for
example `-d extension=phalcon.so`, do not reach it. Put such settings in
`php.ini`.

Press Ctrl+C to stop the server. If the server continues to run, for example
after crest was stopped in a different way, stop its PHP process:

    pkill -f -- '-S 127.0.0.1:8080'

Use your port if it is not 8080.

`new`, `up`, `down` and `install` run before the project has a `vendor/`. Run
them with a crest outside the project, for example one that you install with
`composer global require phalcon/crest`. On a host with docker and no PHP, run
`up`, `down` and `install` with `./crest`.

On a host with docker and no PHP, one command creates a project, starts it and
installs it. See "With docker only" in the [README](../README.md#with-docker-only):

    curl -fsSL https://phalcon.io/crest-setup | sh -s -- my-app

The generated project requires `phalcon/crest` as a dev dependency. The
commands that work on the project, for example `make:action` and
`route:list`, need the autoloader and the Phalcon of the project. After
`crest install` or `composer install`, the project has its own crest in
`vendor/`. A global crest passes these commands to it, with the same
arguments, and returns its exit status:

    crest make:action GET /hello

In the container, there is no global crest. Run the crest of the project:

    docker compose exec app vendor/bin/crest make:action GET /hello

On a host without PHP, `./crest` does the same:

    ./crest make:action GET /hello

`new`, `init`, `up`, `down`, `install` and `--version` always run in the crest that you
type. crest finds the project by its `crest.php`: the file that `--config` names, or the
nearest `crest.php` above the working directory or `--directory`. `up`, `down` and
`install` run docker compose in that folder. Without `crest.php`, crest stops:

    crest: no crest.php found; run 'crest init'

If the project has no `<bin-dir>/crest` (by default `vendor/bin/crest`), crest stops
too:

    crest: /path/to/my-app has no vendor/bin/crest; run 'crest install' or 'composer install' first
    crest: /path/to/my-app does not require phalcon/crest; run 'composer require --dev phalcon/crest'

With `'runtime' => ['type' => 'docker', 'service' => 'app']` in `crest.php` (what `new`
writes), a global crest runs the project commands in the container:
`docker compose exec app vendor/bin/crest ...`. A v5 project then works on a host
without `ext-phalcon`. `serve` is the exception: it always runs on the host, because
PHP's built-in server must listen there. With docker, `crest up` serves the project.

The docker runtime has two conditions. The service must mount the project folder, with
`vendor/` in it: crest looks for `vendor/bin/crest` on the host first. And the
`working_dir` of the service must be the project root: `vendor/bin/crest` runs there. The
compose file of `new` does both. `install` runs composer in the service of the docker
runtime. With the host runtime, it uses the `service` of the key, else `app`.

crest reads `vendor-dir` and `bin-dir` as composer does: `COMPOSER_VENDOR_DIR` and
`COMPOSER_BIN_DIR`, then `config` in `composer.json`, then `vendor` and
`<vendor-dir>/bin`. The hand-off runs `<bin-dir>/crest`, and `serve` checks
`<vendor-dir>/autoload.php`. With the docker runtime, composer runs in the container, so
crest reads only `composer.json`, and the two folders must be relative. crest does not
expand `~` or `$VAR` in these values: use a relative or an absolute path. A custom
`vendor-dir` or `bin-dir` needs this version of crest both as the global crest and in the
project: an older crest looks only for `vendor/bin/crest`.

The files come from the `project-*` stubs. To change them, publish them by
name in the directory that the project goes into (the working directory, or
`--directory`), then edit the copies:

    crest stub:publish project-front

A project stub needs no `crest.php`, and it always uses the `adr` flavor. A
publish with no name leaves the project stubs out, because they do nothing
inside a project. It also leaves the fragment stubs (`fragment-*`) out: they
hold pieces of the framework API and the guidance text that the generators
print, and a copy does not get the changes of later releases. Publish a
fragment by name to change it.

You can change each project stub on its own. The names that the stubs and
crest must agree on come from placeholders: `{{ documentRoot }}`,
`{{ router }}`, `{{ portVariable }}`, `{{ port }}`, `{{ front }}`,
`{{ source }}` and `{{ autoloader }}`. Keep these placeholders where the
packaged copy has them. Then `serve`, `init` and the generated files agree.
`project-front`, `project-config` and `project-index` must also agree with
each other on the namespace. `new` decides where each file goes: a stub
cannot move its file. A published copy must use only the placeholders of the
packaged copy. If a placeholder has no value, `new` stops before it writes a
file.

### Commands after `new`

`new` can run commands after it writes the files, for example `crest up` and
`crest install`. Publish the `project-config` stub in the folder that the projects go
into, and add the key `new` to the copy:

    crest stub:publish project-config

    'new' => [
        ['crest', 'up'],
        ['crest', 'install'],
    ],

With the host runtime, use `['composer', 'install']` instead. With the docker runtime,
composer runs in the container: the host often has no Phalcon, or an older PHP, and then
`composer install` on the host stops.

Each entry is one command, as a list of words. There is no shell: pipes, `&&` and
variables do not work. When the first word is `crest`, the crest that runs `new` also runs
the command. Use `crest`, not `./crest`: crest looks for a relative path from the folder
where you type the command, not from the project. `new` runs the commands in order, in
the project folder, and prints `Running: <command>` before each one. At the end, it prints
`Ran 2 commands from the 'new' key of crest.php`.

The first command that fails stops the list, and `new` returns its exit status:

    crest: 'composer install' failed with exit status 2; the files in my-app/ stay

A program that is not in the `PATH` also stops the list, with exit status 1:

    crest: 'composer' was not found; install it or add it to the PATH

`new` reads the key from the `crest.php` that it writes. Thus it checks the key after it
writes the files. If the key is not a list of commands, or if PHP cannot parse the file,
`new` stops before the first command, and the files stay. The error names the published
stub. Change the stub, not the `crest.php` of the project, then run `new` again with
`--force`:

    crest: my-app/crest.php: 'new' entry 2 in crest.php must be a list of strings, e.g. ['crest', 'up']; the files in my-app/ stay; fix /path/to/resources/stubs/adr/project-config.stub and run new again with --force

The key stays in the `crest.php` of the project. No other command reads it, and
`config:show` does not show it. `init` uses the same stub: it writes the key, but runs
nothing. Without the key, `new` runs nothing.

The one-line setup (see "With docker only" in the README) runs `new` in a container
without docker. There, `crest up` fails. Do not use the key with it.

## Adding crest to an existing project

    composer require --dev phalcon/crest
    crest init

`init` writes `crest.php` in the nearest folder with a `composer.json`. It asks these
questions, in this order, each with a proposal:

1. The namespace. The proposal is the namespace of the first psr-4 entry whose folder
   exists. The answer must be in the psr-4 map (`App\Api` under `"App\\": "src/"` is),
   because the generators get their namespaces from it. The other proposals follow the
   answer: its folder is the psr-4 folder of the longest prefix, for example `src/Api`.
2. The front controller: `<namespace>\AppFront` when `AppFront.php` is in that folder.
   Answer `none` for no front controller.
3. A folder for each path (`action`, `command`, `middleware`, `provider`, `responder`),
   in that folder. The question says if the folder exists. An answer must be a relative
   folder in a psr-4 folder, and its folder names must give a usable namespace:
   `src/my-actions` gives `App\my-actions`, which PHP cannot use. A leading `./` is
   removed.
4. The runtime: docker when the project has `compose.yaml`, `compose.yml`,
   `docker-compose.yaml` or `docker-compose.yml`, with the service `app`, or the first
   service. For docker, the service.

Press Enter to accept a proposal. `--no-interaction` accepts all of them. `--force`
overwrites an existing `crest.php`. If the project does not require crest, `init` prints
the `composer require` line.

`init` needs a psr-4 entry in `composer.json`, because the generators get their
namespaces from it. Without one, `init` stops and tells you to add one, for example
`"App\\": "app/"`, and to run `composer dump-autoload`.

## Commands that boot the project

`container:list` and `event:list` read state that exists only once the
application has registered it, so they start your front controller. Name it in
`crest.php`:

```php
return [
    'bootstrap' => App\Front\ApiFront::class,
];
```

The class is constructed with the project root - take it if you need it - and
has to declare `boot()`. There is no base class and no interface; `boot()` is
the whole contract:

```php
use Phalcon\Container\Container;

final class ApiFront
{
    public function __construct(private readonly string $root)
    {
    }

    public function boot(): Container
    {
        $container = new Container();

        // register your services

        return $container;
    }
}
```

`boot()` must return an object, and what that object has to satisfy depends on
the command:

| Command | Requires the returned object to |
|---|---|
| `container:list` | implement `Phalcon\Contracts\Container\Service\Collection` and `Enumerable` |
| `event:list` | implement `Collection`, and hold a registered `Phalcon\Events\Manager` |

`Phalcon\Container\Container` implements both contracts, so returning one is
enough for `container:list`.

For `event:list`, "registered" means a definition or an existing instance. A
manager the container would merely autowire does not count - crest reports that
the bootstrap registers none, rather than listing zero listeners off a fresh
instance it created itself.

Everything else - the generators, `about`, `config:show` and `route:list` -
reads the filesystem and keeps working on a project that does not currently run.

## Generating actions

`make:action` takes an HTTP method and a route path:

    crest make:action GET /company/all
    crest make:action GET /company/{id}

The class name comes from the framework's routing convention, so the file lands
where the router will look for it. Placeholders must come last: `/album/{id}/edit`
is rejected, with `/album/edit/{id}` suggested instead.

| Option | Purpose |
|---|---|
| `--responder=json\|view` | which packaged shape to render; defaults to `json` |
| `--stub=<name>` | render a named stub instead; cannot be combined with `--responder` |
| `--template=<name>` | template the view responder renders; defaults to `<path>/index` |
| `--force` | overwrite an existing action |

A view action names a template but does not create one. `Renderer::render()`
takes a name rather than a path, so the directory and the extension belong to
your renderer, and crest prints the name instead of guessing at a file.

## Adding commands from a package

A package adds commands to crest in its `composer.json`:

```json
"extra": {
    "crest": {
        "commands": {
            "demo:greet": "Vendor\\Demo\\GreetCommand"
        }
    }
}
```

crest reads this block from each installed package, and from the root
project. `crest make:command` writes a command and prints its block.

A command extends `Crest\Console\Command\Command` and declares two methods:

```php
use Crest\Console\Command\Command;
use Crest\Console\Input;
use Crest\Console\Output;
use Crest\Console\Parsing\Definition;

final class GreetCommand extends Command
{
    public function define(): Definition
    {
        return Definition::for('demo:greet', 'Greet someone')
            ->argument('name', true, 'Who to greet')
            ->option('shout', 'Use capital letters');
    }

    public function handle(Input $input, Output $output): int
    {
        $output->line('Hello ' . $input->argumentString('name'));

        return 0;
    }
}
```

crest makes the command with `new $class()`, so the constructor must work
without arguments. Each command also gets the global options (`--config`,
`--directory`, `--trace`, `--help`, `--quiet`, `--no-interaction`).

These classes are the API for a package. Each has `@api` in its docblock:

| Class | Use |
|---|---|
| `Crest\Console\Command\Command` | the base class of a command |
| `Crest\Console\Parsing\Definition` | `for()`, `argument()` and `option()` declare the arguments and options |
| `Crest\Console\Input` | the values of the call |
| `Crest\Console\Output` | `line()`, `success()`, `error()`, `write()`, `table()`, `ask()` and `choice()` |
| `Crest\Console\Exceptions\Exception` | throw it to stop with one clean error line |
| `Crest\Command\Project` | `Project::context($input)` gives the project of the call |
| `Crest\Project\ProjectContext` | `root()`, `path($key)` and `namespaceFor($key)` |

`Project::context()` finds the project by the rule of the other commands:
the file that `--config` names, or the nearest `crest.php` above
`--directory` or the working directory. Without `crest.php`, the command
stops with the `crest init` hint.

A public element with `@internal` in its docblock is not part of the API,
and it can change in any release. Tools that read the tag, for example an
IDE or a static-analysis rule, can report its use from another package. Do
not extend `Crest\Command\ProjectCommand`: it is the base of the commands of
crest, and it changes with them.
