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
| `event:list` | listeners attached to the project events manager |
| `list` (`commands`, `enumerate`) | the available commands |
| `make:action` | create an ADR action for a route |
| `make:command` | create a crest command |
| `make:middleware` | create an ADR middleware |
| `make:provider` | create a service provider |
| `make:responder` | create an ADR responder |
| `route:list` | every route the application answers |
| `stub:publish` | copy packaged stubs into the project for editing |

Only the `adr` flavor has generators. A `cli` or `mvc` project can still run
`about`, `config:show` and `list`.

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
