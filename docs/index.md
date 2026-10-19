# Crest

Command line application for Phalcon.

See the [README](../README.md) for installation, usage and configuration.

## Commands

Aliases are shown in brackets. Run `crest` with no arguments, or `crest list`,
for the same listing from the tool itself. In a project, the listing shows only
the commands for the flavor of the project.

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
| `make:controller` | create an MVC controller |
| `make:middleware` | create an ADR middleware |
| `make:model` | create an MVC model from a database table, or from each table with --all (relations come only from foreign keys) |
| `make:provider` | create a service provider |
| `make:responder` | create an ADR responder |
| `new` | create an ADR or MVC project |
| `route:list` | every route the application answers |
| `serve` (`server`) | start PHP's built-in web server for the project |
| `stub:publish` | copy packaged stubs into the project for editing |
| `up` | start the project containers |

`make:command` works in each flavor. `make:provider` works in ADR and MVC projects,
with a stub for each flavor. `make:controller` and `make:model` are for MVC
projects only. These commands are for ADR projects only: `make:action`,
`make:middleware`, `make:responder`, `route:list`, `container:list` and
`event:list`. In a project of a different flavor, they stop with an error:

    crest: make:responder does not support the 'mvc' flavor of this project; it supports: adr

## Creating a project

`new` writes an ADR or an MVC project that runs:

    crest new my-app
    crest new my-app --flavor=mvc

| Option | Purpose |
|---|---|
| `--flavor=adr\|mvc` | the flavor of the project; defaults to `adr` |
| `--template-engine=<engine>` | the view engine of an MVC project: `phtml`, the default and the only engine for now. An ADR project has no views, so `new` stops when you give it |
| `--namespace=<name>` | root namespace for the generated code; defaults to `App` |
| `--php=<major.minor>` | PHP version for `composer.json` and the Dockerfile; defaults to `8.4`, and must be 8.1 or later |
| `--phalcon=v5\|v6` | `v5` requires the C extension, 5.18 or later; `v6` the `phalcon/phalcon` package; defaults to `v5` |
| `--runtime=host\|docker` | where the project commands run (the `runtime` key of `crest.php`); defaults to `docker` |
| `--service=<name>` | the docker compose service, for `docker`; defaults to `app` |
| `--force` | write into a directory that is not empty, and overwrite files with the same names |

In a terminal, `new` asks for each value that no option gives: the flavor, the
namespace, the PHP version, the Phalcon version, and the runtime (`docker` with the
service `app`, or `host`). There is no question for the view engine while there is only
one. An empty answer takes the default. `--no-interaction`, or a run without a terminal
(CI, a pipe), asks nothing and takes the defaults. The options stay, for CI.

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

A project stub needs no `crest.php`. `--flavor` gives its flavor, as for `new`;
the default is `adr`:

    crest stub:publish project-front --flavor=mvc

The project stubs that are the same in both flavors are in
`resources/stubs/shared/`, and a publish puts them there. A copy in
`resources/stubs/<flavor>/` wins over the shared copy for that flavor. When the
flavor does not have the stub and another flavor does, the error names the
option to use, for example `pass --flavor=mvc`. A
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

In an MVC project, `{{ controllerNamespace }}`, `{{ controllerPath }}`,
`{{ providerNamespace }}`, `{{ providerPath }}` and `{{ viewsPath }}` come from
the default paths of the flavor. A stub that depends on the view engine has the
engine name in its name: `project-phtml-provider`, `project-phtml-layout` and
`project-phtml-index`.

### The MVC project

`crest new my-app --flavor=mvc` writes the layout of the Phalcon sample
applications (vokuro, invo):

| Path | Contents |
|---|---|
| `public/index.php` | the web entry point, as in an ADR project |
| `src/AppFront.php` | the front controller: `FactoryDefault`, the providers and `Phalcon\Mvc\Application` |
| `src/Providers/` | `DispatcherProvider` (the default namespace of the controllers) and `ViewProvider` (the views folder and the phtml engine) |
| `src/Controllers/` | `IndexController`, which answers `GET /` |
| `src/Controllers/Base/` | `CrestControllerBase`, the base that crest owns (see "Generated bases") |
| `themes/default/` | the layout `index.phtml` and the view `index/index.phtml` |

`crest.php` has `'flavor' => 'mvc'` and the paths `controller`, `model`,
`provider` and `views`. To add a service, run `crest make:provider Mailer`. It
writes `src/Providers/MailerProvider.php`, a provider that implements
`Phalcon\Di\ServiceProviderInterface`, and prints the line to add to
`AppFront::providers()`.

A URL with no controller stops in the dispatcher ("handler class cannot be
loaded"). With `display_errors` off, PHP answers 500. With it on, PHP shows the
error in the page, with status 200. Add a not-found page if your project needs
one, for example a dispatcher listener such as the `NotFoundPlugin` of invo.

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
stub that `new` used. Change the stub, not the `crest.php` of the project, then run `new`
again with `--force`:

    crest: my-app/crest.php: 'new' entry 2 in crest.php must be a list of strings, e.g. ['crest', 'up']; the files in my-app/ stay; fix /path/to/resources/stubs/shared/project-config.stub and run new again with --force

`new` also checks that the written `crest.php` has the flavor of the project. Keep
`'flavor' => '{{ flavor }}'` in a published `project-config`: a copy with a fixed
flavor stops `new` in the same way, before the first command.

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
2. The flavor: `adr` or `mvc`. For each flavor, `init` counts the default folders of
   that flavor (item 4) that the project has, and proposes the flavor with the most. A
   tie gives `adr`. A project with `src/Controllers`, `src/Providers` and
   `themes/vokuro` gets `mvc`. `--flavor` answers the question.
3. The front controller: `<namespace>\AppFront` when `AppFront.php` is in that folder.
   Answer `none` for no front controller.
4. A folder for each path of the flavor: `action`, `command`, `middleware`, `provider`
   and `responder` for ADR; `command`, `controller`, `provider` and `views` for MVC. The
   question says if the folder exists. A class folder is in the folder of the namespace.
   An answer must be a relative folder in a psr-4 folder, and its folder names must give
   a usable namespace: `src/my-actions` gives `App\my-actions`, which PHP cannot use. A
   leading `./` is removed.

   `views` holds no classes. It is a relative folder in the project, with no psr-4
   check. Each part of the answer must be a folder name, so `init` refuses an absolute
   folder, an empty part (`//`), `.` and `..`. The proposal is `themes/default`; when
   that folder does not exist and `themes/` has one folder only, `init` proposes that
   folder (`themes/vokuro` in vokuro).
5. The runtime: docker when the project has `compose.yaml`, `compose.yml`,
   `docker-compose.yaml` or `docker-compose.yml`, with the service `app`, or the first
   service. For docker, the service.

Press Enter to accept a proposal. `--no-interaction` accepts all of them. `--force`
overwrites an existing `crest.php`. If the project does not require crest, `init` prints
the `composer require` line.

`init` needs a psr-4 entry in `composer.json`, because the generators get their
namespaces from it. Without one, `init` stops and tells you to add one, for example
`"App\\": "app/"`, and to run `composer dump-autoload`.

## Commands that boot the project

`container:list`, `event:list` and `make:model` read state that exists only
once the application has registered it, so they start your front controller.
Name it in `crest.php`:

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
| `make:model` | implement `Phalcon\Di\DiInterface`, and hold a `db` service that is a `Phalcon\Db\Adapter\AdapterInterface` |

`Phalcon\Container\Container` implements both contracts, so returning one is
enough for `container:list`.

For `event:list`, "registered" means a definition or an existing instance. A
manager the container would merely autowire does not count - crest reports that
the bootstrap registers none, rather than listing zero listeners off a fresh
instance it created itself.

Everything else - the other generators, `about`, `config:show` and
`route:list` - reads the filesystem and keeps working on a project that does
not currently run.

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

## Generating controllers

In an MVC project, `make:controller` writes a controller and the base class that
it extends:

    crest make:controller Users

    Created src/Controllers/Base/CrestControllerBase.php
    Created src/Controllers/UsersController.php

`UsersController` extends `CrestControllerBase`, which extends
`Phalcon\Mvc\Controller`, and has an empty `indexAction()`. Crest then prints
what the dispatcher needs: its default namespace, the URL of `indexAction()`
with the default routes (`GET /users`), and the name of its view
(`users/index`). The views folder and the engine belong to your project.

A project from `crest new --flavor=mvc` already has `CrestControllerBase` and
`IndexController`, and its dispatcher has the default namespace.

The name must give a class that the dispatcher can find from a URL. Crest stops
on `users` (the URL `/users` looks for `UsersController`), on `User_Profiles`,
and on a name with the suffix two times, for example `UserControllerController`
or `Usercontroller`. `UserController` gives `UserController`.

### Generated bases

Crest owns `CrestControllerBase` in `src/Controllers/Base/`. Do not edit it: its
docblock starts with `Generated by crest.`, and crest writes the file again
when the stub of the base changes. You own the controllers, and crest writes
each one only once.

- To get the new base after an upgrade of crest, run `make:controller` again on
  an existing controller. Crest keeps the controller and writes the base:

      Updated src/Controllers/Base/CrestControllerBase.php
      Kept src/Controllers/UsersController.php: it exists; pass --force to replace it

- `--force` replaces the controller with a new one. It never replaces a base
  without the `Generated by crest.` line: crest keeps that file and prints a
  note. To get the crest base back, delete the file or add the
  `Generated by crest.` line to it.
- Put the code that your controllers share into your own class that extends
  `CrestControllerBase`. Publish `controller.stub`
  (`crest stub:publish controller`) and change its `use` line and its
  `extends`, so that new controllers extend your class.
- A published `controller-base.stub` wins over the packaged one, so the
  upgrades of crest no longer reach your base. Keep the `Generated by crest.`
  line in your copy, so that crest writes the base again when you change the
  stub.

## Generating models

In an MVC project, `make:model` reads a table from the database of the project,
and writes a model and the base class that it extends. Crest writes relations
only from FOREIGN KEY constraints (see "Relations"):

    crest make:model users

    Created src/Models/Base/UsersBase.php
    No relations for users: the table has no foreign keys, and no foreign keys point to it. Relations come only from foreign keys.
    Created src/Models/Users.php

Crest reads the table through the `db` service of your project, so it starts
your front controller: `crest.php` must name it in `bootstrap` (see "Commands
that boot the project"). A project from `crest new --flavor=mvc` names
`AppFront`, but it has no `db` service yet. Add a provider that registers one
(`crest make:provider Db`).

With the `phalcon/phalcon` package (Phalcon v6), a model also needs
`phalcon/phql`, because a model uses PHQL to find rows. The extension (Phalcon
v5) has PHQL. Install the package in your project:

    composer require phalcon/phql:1.0.x-dev

`Users` extends `UsersBase`, which extends `Phalcon\Mvc\Model`. The base has a
property for each column, in the order of the table, and an `initialize()` that
sets the table:

```php
public ?int $id = null;
public ?string $name = null;
```

| Column type | Property type |
|---|---|
| the integer types | `?int` |
| `FLOAT`, `REAL`, `DOUBLE` | `?float` |
| `BOOLEAN` on PostgreSQL | `?bool` |
| all others, also `DECIMAL`, the dates and text | `?string` |

The column type is the type that Phalcon reads from the database. MySQL and
MariaDB keep a `BOOLEAN` column as `TINYINT(1)`, so it gives `?int`. On SQLite,
Phalcon reads `DOUBLE` and `BOOLEAN` columns as `VARCHAR`, so they give
`?string`.

Each property is nullable, with the default `null`, because a new model has no
values yet. The class name comes from the table name: crest makes the first
letter of each part between `_` uppercase. `user_profiles` gives
`UserProfiles`. To give a table another class, or a column another property
name, see "Names".

When the table changes, run `make:model` again. Crest writes the base again,
and keeps your model:

    Updated src/Models/Base/UsersBase.php
    No relations for users: the table has no foreign keys, and no foreign keys point to it. Relations come only from foreign keys.
    Kept src/Models/Users.php: it exists; pass --force to replace it

The rules of "Generated bases" apply to the base of a model too: do not edit
it (you can change only the aliases of its relations and the names in its
`PropertyName` attributes, see "Relations" and "Names"), and `--force`
replaces the model, but never a base without the
`Generated by crest.` line. If you add `initialize()` to your model, call
`parent::initialize()` first.

Crest stops, and writes no file, when:

- the table name gives no class name (`user-profiles`), or a name that PHP
  reserves (`list`), and `models.classes` gives it no class (see "Names");
- the project has no `db` service, the service is not a Phalcon database
  adapter, or it cannot connect;
- the table does not exist;
- a column name is not a property name (`first name`), or it is the name of a
  property of `Phalcon\Mvc\Model` (`container`), and no `--property` or
  attribute gives it another name: the error gives the `--property` to use;
- the base is not valid PHP, or crest cannot use a `RelationAlias` or a
  `PropertyName` attribute in it (see "Relations" and "Names");
- the `models` key of `crest.php` is not correct, or `--property` names a
  column that the table does not have (see "Names");
- a published stub has no `{{ columnMap }}` or `{{ property }}`, and the base
  needs a `columnMap()` (see "Names").

`make:model` is for MVC projects. An ADR project uses repositories, not Active
Record models, so the command stops there.

### Names

Crest gives each model the class of its table name, and each property the
name of its column. Two settings in `crest.php` and one option change the
names:

```php
return [
    'flavor'    => 'mvc',
    'bootstrap' => App\AppFront::class,
    'models'    => [
        'classes'   => ['users' => 'User', 'user-profiles' => 'UserProfile'],
        'camelCase' => true,
    ],
];
```

**Classes.** `classes` gives a class to a table. Write a line only for each
table whose class you want to change: the other tables keep the class of their
name (`users` gives `Users`). The table name must be exact, with the same case.
A line also gives a class to a table whose name gives none (`user-profiles`), or
gives a name that PHP reserves (`list`), also with `--all`. With `--all`, a line
for a table that the database does not have gives a note:

    Note: models.classes names the table Users, which the database does not have; did you mean users?

When you add a line for a table that has a model, crest writes a new base and a
new model (`UserBase.php`, `User.php`), and keeps the old files. Move your code
to the new model, delete the old files, and run `make:model` for the tables that
have relations with the table. The new base does not get the `PropertyName`
attributes of the old base: give the names again with `--property`.

**camelCase.** With `'camelCase' => true`, each property gets the camelCase form
of its column: crest splits the column at each `_`, makes the first letter of
each part uppercase, and then makes the first letter of the name lowercase.
`first_name` gives `firstName`, and `name` stays `name`. The other letters do
not change, so a column in capitals gives an odd name (`USER_ID` gives `uSERID`):
give it a name with `--property`. The setting applies to each table, on each
run.

> **Warning:** when you change `camelCase` in a project that has models, the
> next run of `make:model` renames the properties of each base, and the code
> that uses the old names stops working. Crest prints a `Renamed` line for each
> property that changes.

**One property.** `--property` gives a name to a column of one table:

    crest make:model users --property="first name=firstName,last_name=lastName"

Separate the columns with commas, and give all the names to one `--property`:
if you give it two times, crest uses only the last one. A column whose name has
a comma, or a space at the start or the end, cannot get a name. `--property`
works only for one table, not with `--all`.

Crest keeps each name of `--property` in a `PropertyName` attribute on the
base:

```php
#[\Crest\Attribute\PropertyName('first name', name: 'firstName')]
abstract class UsersBase extends Model
```

On the next runs, you do not give `--property` again: crest reads the
attribute. You can change the name in the attribute, and crest uses it on the
next run. To use the name of `camelCase`, or the column name, again, delete the
attribute line and run `make:model` again. When the table no longer has the
column, crest drops the attribute:

    Dropped the property name firstName of column 'first name': the table has no such column

Crest reads the `PropertyName` attributes as it reads the `RelationAlias`
attributes (see "Relations"). Crest stops, and writes no file, when a
`PropertyName` attribute does not have two plain strings (the column and the
name), when two attributes have the same column, or when the name in an
attribute is not a usable property name. The message names the file and the
line.

The order of the names: `--property`, then the attribute, then `camelCase`, then
the column name.

**columnMap().** When a property does not have the name of its column, the
base gets a `columnMap()` with each column and its property. Phalcon uses it to
read and write the rows, but only when the setting `orm.column_renaming` is on,
as it is by default. The relations use the property names of the two tables:
with `camelCase`, the `belongsTo()` of `invoices` has `'customerId'`.

**When a name changes.** When a property gets another name (a change of
`camelCase`, a new `--property`, or a changed attribute), crest prints a line
for it. When a relation of another table uses the property, crest names that
table:

    Updated src/Models/Base/InvoicesBase.php
    Renamed $customer_id to $customerId: change the code that uses it
    Run make:model for customers too: its relations use the property names of invoices
    Kept src/Models/Invoices.php: it exists; pass --force to replace it

With `--all`, crest writes all the tables again, so it names only the tables of
`--exclude` and the tables that get no class (see "All the tables"). It does not
name a table that fails later. The summary lists each table that failed: run
`make:model` for it again after the fix.

**Published stubs.** `model-base` has the placeholder `{{ columnMap }}`, and
`fragment-model-property` has `{{ property }}`. A copy that you published before
does not have them. When a base needs a `columnMap()`, crest stops:

    crest: the published stub .../resources/stubs/mvc/model-base.stub has no {{ columnMap }}; publish it again with crest stub:publish model-base --force, and make your changes in it again

Crest writes the `columnMap()` with the stub `fragment-model-column-map`, which
has the placeholder `{{ pairs }}`. On the next run, crest reads the old property
names from this `columnMap()`. It reads only the form that it writes: a body
with only `return [...];`, and a plain string on each side of each `=>`. When
the base has another form (a published `fragment-model-column-map`, or a change
by hand), crest still writes the base, but it cannot find the renamed
properties. It prints no `Renamed` line, and prints:

    Note: crest cannot read columnMap() of src/Models/Base/CustomersBase.php, so it cannot list the renamed properties

Then find the code that uses the properties of that table yourself.

### All the tables

`make:model --all` writes a model and its base for each table of the database,
in the order of the table names. For each table, crest prints a `Table` line,
then the lines of `make:model <table>`. At the end, crest prints a summary:

    crest make:model --all

    Table customers
    Created src/Models/Base/CustomersBase.php
    Created src/Models/Customers.php

    Table invoices
    Created src/Models/Base/InvoicesBase.php
    Created src/Models/Invoices.php

    2 tables: 2 models created, 0 kept, 0 failed.

Each table works as with `make:model <table>`: another run writes the bases
again and keeps the models, and `--force` replaces the models. When the
database has no tables to write, crest prints `No tables to write.`, and the
exit status is 0.

`--all` writes no models for views, and no models for the internal tables of
SQLite (for example, `sqlite_sequence`). For a view, run `make:model <view>`.
This works on MySQL, MariaDB and PostgreSQL. On SQLite, `make:model <view>`
stops with the "not found" error, because Phalcon finds only tables there.

To skip tables, give their names to one `--exclude`, separated by commas:

    crest make:model --all --exclude=audit_log,phalcon_migrations

Give all the names to one `--exclude`. If you give `--exclude` two times,
crest uses only the last one.

Each name must be the exact name of a table, with the same case. A name that
the database does not have stops crest before it writes a file. When the name
differs from a table only in case, the error gives the name of the table:

    crest: --exclude: the database has no table Users; did you mean users?

For the name of a view or an internal SQLite table, crest prints a note, and
continues. A skipped table still gives its relations: with
`--exclude=invoices`, the base of `customers` has its `hasMany()` to
`Invoices`.

A table fails when its name gives no class name (`user-profiles`) or a name
that PHP reserves (`list`) (a line of `models.classes` gives such a table a
class), when a column is not a property name and has no name from an
attribute, or when crest cannot use its base (see the list above). A table
also fails when it
gives the same class as another table, also with a different case
(`UserProfiles` and `user_profiles`, or `USERS` and `users`): crest writes
none of these tables. Crest prints the reason, and continues with the next
table. At the end, crest lists the tables that failed, so that you can write
their models yourself:

    Table user-profiles
    Failed: 'user-profiles' gives no usable class name; expected a table name like 'users' or 'user_profiles'

    Table users
    Created src/Models/Base/UsersBase.php
    No relations for users: the table has no foreign keys, and no foreign keys point to it. Relations come only from foreign keys.
    Created src/Models/Users.php

    2 tables: 1 model created, 0 kept, 1 failed.
    Failed tables:
      user-profiles: 'user-profiles' gives no usable class name; expected a table name like 'users' or 'user_profiles'

The exit status is 1 when a table fails, and 0 when crest writes or keeps each
table. Crest prints all these lines to stdout, also the `Failed` lines, so a
log of the output (`> log.txt`) has them in order. An error that is not about
one table stops crest, and goes to stderr: for example, no `db` service, a
connection that breaks, or a stub that crest cannot render. The files of the
tables before the error stay, and the next run continues from them.

### Relations

Crest writes relations only from FOREIGN KEY constraints. It does not read the
names of the columns: a column `customer_id` with no FOREIGN KEY constraint
gives no relation. MySQL tables with the MyISAM engine do not keep foreign
keys, so they give no relations. When a table has no foreign keys, and no
foreign key of another table points to it, `make:model` prints:

    No relations for users: the table has no foreign keys, and no foreign keys point to it. Relations come only from foreign keys.

With no foreign keys, write the relations in your model, in `initialize()`
after `parent::initialize()`:

```php
public function initialize(): void
{
    parent::initialize();

    $this->belongsTo(
        'customer_id',
        Customers::class,
        'id',
        ['alias' => 'Customer']
    );
}
```

With foreign keys, the base gets a `belongsTo()` for each foreign key of the
table, and a `hasMany()` for each foreign key of another table that points to
the table. With these tables:

```sql
CREATE TABLE customers (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR(50) NOT NULL);
CREATE TABLE invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    customer_id INTEGER NOT NULL REFERENCES customers(id),
    total DECIMAL(10,2)
);
```

the base of `invoices` gets:

```php
$this->belongsTo(
    'customer_id',
    \App\Models\Customers::class,
    'id',
    ['alias' => 'Customer']
);
```

and the base of `customers` gets:

```php
$this->hasMany(
    'id',
    \App\Models\Invoices::class,
    'customer_id',
    ['alias' => 'Invoices']
);
```

Read a relation through its alias: `$invoice->Customer`, `$customer->Invoices`,
or `$invoice->getRelated('Customer')`. The related class is in the folder of
your models, also when you did not generate its model yet. Run `make:model`
for each table.

The aliases:

- A `belongsTo()` uses the key column without `_id`: `customer_id` gives
  `Customer`. If the column has no `_id`, or the key has more than one column,
  the alias is the related class (`Customers`).
- A `hasMany()` uses the class of the other table (`Invoices`). If that table
  has more than one key to this table, the alias adds `By` and the alias of the
  key: a table `messages` with `sender_id` and `recipient_id` gives
  `MessagesBySender` and `MessagesByRecipient`.

Each relation has a `RelationAlias` attribute on the base, with the key of the
relation and its alias:

```php
/**
 * Generated by crest. Do not edit this file: crest writes it again when you
 * run make:model invoices. Put your code into the model that extends it.
 *
 * You can change the alias in a RelationAlias attribute: crest keeps it when
 * it writes this file again.
 */
#[\Crest\Attribute\RelationAlias('belongsTo invoices(customer_id) -> customers(id)', alias: 'Customer')]
abstract class InvoicesBase extends Model
```

To change an alias, change the `alias:` argument, and run `make:model` for the
table again. Crest writes the base with your alias, and keeps it on each later
run. An alias in an attribute does not change when another key to the same
table comes, or when a later version of crest has other rules. Change only the
`alias:` argument: crest writes the rest of the base again. If you delete the
base, the aliases go back to the rules above.

When the next base has no relation for the key of an attribute, crest drops
the attribute and prints one line for it, so that you know that the alias is
gone. This happens when a key column gets a new name, when the key gets
another target, or when the table name has another case on another server:

    Dropped the alias Buyer of 'belongsTo invoices(buyer_id) -> customers(id)': the database has no such key

Crest reads the attributes with the PHP tokenizer, so a formatter can change
their layout: line breaks, comments, quotes, named arguments, or a `use` import
of `Crest\Attribute\RelationAlias`. Each argument must be a plain string: in
single quotes, or in double quotes with no backslash. A constant, or two
strings joined with `.`, is not a plain string. The attribute class does not
need to exist when your application runs, so an install without crest
(`composer install --no-dev`) works.

Crest stops, and writes no file, when the base is not valid PHP, when a
`RelationAlias` attribute does not have two plain strings (the key and the
alias), when an alias is not a valid name, when two attributes have the same
key, when an alias of an attribute has the name of a property of the table, or
when two aliases of attributes are the same (the case is ignored). The message
names the file and the line.

When another table gets a new key to a table, run `make:model` for that table
again, so that its base gets the new `hasMany()`. Or run `make:model --all`,
which writes the bases of all the tables again.

Crest skips a relation that it cannot write, and prints one line for it:

    Skipped the relation belongsTo invoices(customer_id) -> customers(id): the alias Customer has the name of a property

Crest skips a relation when:

- the key does not name the referenced columns (SQLite accepts
  `REFERENCES customers`);
- the related table gives no class name, or a name that PHP reserves;
- crest cannot read the base of the related table, for example because an
  attribute in it does not have two plain strings. The line gives the error;
- a key column of the related table has no usable property name, and no
  `--property` or attribute gives it one (see "Names");
- the alias has the name of a property of the table (the case is ignored);
- two or more relations have the same alias (the case is ignored). Crest skips
  all of them, but not a relation whose alias comes from an attribute.

Crest writes the other relations. When the reason is the alias (the name of a
column, or two relations with the same alias), you can give the skipped
relation an alias: add an attribute with the key from the line, and run
`make:model` again:

    #[\Crest\Attribute\RelationAlias('belongsTo invoices(customer_id) -> customers(id)', alias: 'Client')]

For the other reasons, an attribute does nothing: crest drops it, with a
`Dropped` line.

When crest cannot read the base of the related table, correct that base as the
line says, and run `make:model` again. When a key column of the related table
has no usable property name, give it a name in a run for the related table, and
then run `make:model` for this table again:

    Skipped the relation hasMany notes(it's_id) -> users(id): column 'it's_id' of table 'notes' has no usable property name

    crest make:model notes --property="it's_id=userId"
    crest make:model users

You can also add a skipped relation in your model, in `initialize()` after
`parent::initialize()`.

Composite keys work on MySQL and PostgreSQL: the fields are arrays, for example
`['order_id', 'line_no']`. SQLite gives one key for each column of a composite
key, so crest does not support SQLite composite keys.

An alias with the name of a `get` method of `Phalcon\Mvc\Model`, for example
`Messages` (`getMessages()`) or `Source` (`getSource()`), works as a property
(`$user->Messages`) and through `getRelated('Messages')`, but not as
`$user->getMessages()`: PHP calls the method of the model.

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
