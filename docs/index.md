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
| `--force` | write into a directory that is not empty, and overwrite files with the same names |

The project goes into the working directory, or into `--directory` if you give
one. `new` runs nothing: no composer, no docker, no network. It prints the
next steps:

    cd my-app
    crest up          docker compose up -d
    crest install     composer install in the app container

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
from a subdirectory, and it does not need the crest in `vendor/`, so the
global crest runs it. It stops before PHP starts if `.htrouter.php` or
`vendor/autoload.php` is missing.

The server uses the PHP that runs crest. PHP options on the command line, for
example `-d extension=phalcon.so`, do not reach it. Put such settings in
`php.ini`.

Press Ctrl+C to stop the server. If the server continues to run, for example
after crest was stopped in a different way, stop its PHP process:

    pkill -f -- '-S 127.0.0.1:8080'

Use your port if it is not 8080.

`new`, `up`, `down` and `install` run before the project has a `vendor/`. Run
them with a crest outside the project, for example one that you install with
`composer global require phalcon/crest`.

The generated project requires `phalcon/crest` as a dev dependency. The
commands that work on the project, for example `make:action` and
`route:list`, need the autoloader and the Phalcon of the project. After
`crest install` or `composer install`, run them with the crest in `vendor/`:

    vendor/bin/crest make:action GET /hello
    docker compose exec app vendor/bin/crest make:action GET /hello

The files come from the `project-*` stubs. To change them, publish them by
name in the directory that the project goes into (the working directory, or
`--directory`), then edit the copies:

    crest stub:publish project-front

A project stub needs no `crest.php`, and it always uses the `adr` flavor. A
publish with no name leaves the project stubs out, because they do nothing
inside a project.

You can change each project stub on its own, but `project-front`,
`project-config` and `project-index` must agree with each other. Also,
`project-dockerfile`, `project-compose` and `project-env` must agree with
`serve`: keep `-t public .htrouter.php` in the `CMD`, and keep `APP_PORT`,
with the default 8080, as the port variable. Do not change
the name of the front controller class `AppFront` or the paths of the
generated files, because `new` does not read them from the stubs. A published
copy must use only the placeholders of the packaged copy. If a placeholder has
no value, `new` stops before it writes a file.

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

    vendor/bin/crest make:action GET /company/all
    vendor/bin/crest make:action GET /company/{id}

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
