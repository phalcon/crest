# Changelog

All notable changes are documented here. The format is based on [Keep a Changelog][keep_a_changelog] and this project adheres to [Semantic Versioning][semantic_versioning].

## [Unreleased]

### Added

- Added `route:list`: lists routes with their method and Action class. [#1](https://github.com/phalcon/crest/issues/1)
- Added `config:show`: shows the resolved configuration, each value marked declared or inferred. [#1](https://github.com/phalcon/crest/issues/1)
- Added `container:list`: lists container services with their class and resolved state. [#1](https://github.com/phalcon/crest/issues/1)
- Added `event:list`: lists the events manager listeners. [#1](https://github.com/phalcon/crest/issues/1)
- Added `list` (aliases `commands`, `enumerate`). [#1](https://github.com/phalcon/crest/issues/1)
- Added the `bootstrap` key to `crest.php`: the front controller class to boot, e.g. `App\Front\AppFront::class`. Its `boot()` must return a container. [#1](https://github.com/phalcon/crest/issues/1)
- Added `Crest\Console\Input::argumentString()`, `optionString()` and `optionStringOrNull()`.
- Added `make:command`: generates a crest command and prints its `extra.crest.commands` entry. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:middleware`: generates an ADR middleware and prints its middleware-map entry. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:provider`: generates a `Phalcon\Container` service provider and prints the `registerProviders()` override. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:responder`: generates an ADR responder that implements `Responder`. [#5](https://github.com/phalcon/crest/issues/5)
- Added `stub:publish`: copies packaged stubs to `resources/stubs/<flavor>/`. [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:action --stub`: renders a named stub instead of the `--responder` default. Using both is an error. [#5](https://github.com/phalcon/crest/issues/5)
- Added `command`, `middleware`, `provider` and `responder` to the default `paths`. [#5](https://github.com/phalcon/crest/issues/5)
- Added `Crest\Command\ProjectCommand`: base for commands that read a project; resolves `--directory` and `--config`. [#5](https://github.com/phalcon/crest/issues/5)
- Added `Crest\Generator\ClassName::suffixed()`: appends a suffix once (`Cors` and `CorsMiddleware` both give `CorsMiddleware`). [#5](https://github.com/phalcon/crest/issues/5)
- Added `make:action --template`: overrides the view template (default `<path>/index`). [#5](https://github.com/phalcon/crest/issues/5)
- Added `Crest\Command\ProjectCommand::writer()`: builds the stub writer for the `make:*` commands. [#5](https://github.com/phalcon/crest/issues/5)
- Added `Crest\ADR\ActionResolver::methodFor()`: gets an Action's HTTP method from the router. [#1](https://github.com/phalcon/crest/issues/1)
- Added `Crest\Console\Registry::descriptions()` and `Crest\Console\Output::commandTable()`: one listing for `crest` and `crest list`.
- Added `new`: creates an ADR project from stubs (front controller, `public/index.php`, `crest.php`, a `GET /` action, `composer.json`, docker files). By default, runs no composer, docker or network. Options: `--namespace`, `--php`, `--phalcon`. The project requires `phalcon/crest` in `require-dev`; project commands run as `vendor/bin/crest`. Writes nothing if a stub fails to render. [#8](https://github.com/phalcon/crest/issues/8)
- Added `up`, `down` and `install`: `docker compose up -d`, `docker compose down` and `composer install` in the `app` container. `--directory` names the project. [#8](https://github.com/phalcon/crest/issues/8)
- Added `Crest\Process\Runner` and `ShellRunner`: run external programs. A missing program or directory is a crest error. [#8](https://github.com/phalcon/crest/issues/8)
- Added `Crest\Generator\ClassName::namespace()`: validates a namespace. [#8](https://github.com/phalcon/crest/issues/8)
- Added `Crest\Command\Make\NamedArtifactCommand`: base of `make:command`, `make:middleware`, `make:provider` and `make:responder`.
- Added `serve` (alias `server`): runs `php -S 127.0.0.1:8080 -t public .htrouter.php` in the project root. Port: `--port`, then `APP_PORT` (environment or `.env`), then 8080. Fails if `.htrouter.php` or `vendor/autoload.php` is missing. [#10](https://github.com/phalcon/crest/issues/10)
- Added a package hint for unknown commands: `unknown command 'migration:run'; provided by phalcon/migrations`. No hint when a command with that prefix is registered. The map is set with `Crest\Console\Registry::withProviders()`. [#19](https://github.com/phalcon/crest/issues/19)
- Added the hand-off: in a project, a global crest passes each project command to the project's `vendor/bin/crest` and returns its exit status. `new`, `up`, `down`, `install` and `--version` stay in the global crest. A project that requires `phalcon/crest` without `vendor/bin/crest` gives an error. [#27](https://github.com/phalcon/crest/issues/27)
- Added `Crest\Project\Locator::project()`: the nearest directory with a `composer.json`. [#27](https://github.com/phalcon/crest/issues/27)
- Added `init`: writes `crest.php` for an existing project. It proposes the values that it finds (namespace, front controller, runtime) and asks for each one. [#28](https://github.com/phalcon/crest/issues/28)
- Added the global option `--no-interaction` (`-n`): ask no questions and use the default answers. [#28](https://github.com/phalcon/crest/issues/28)
- Added the `runtime` key to `crest.php`: `host`, or `docker` with a compose service. With docker, a global crest runs project commands with `docker compose exec`, except `serve`, which runs on the host. [#28](https://github.com/phalcon/crest/issues/28)
- Added questions to `new` for the values that no option gives (namespace, PHP, Phalcon, runtime). [#28](https://github.com/phalcon/crest/issues/28)
- Added the options `--runtime=host|docker` and `--service` to `new`: they answer the runtime questions. [#28](https://github.com/phalcon/crest/issues/28)
- Added the launcher `./crest` to the projects that `new` creates: it runs crest with docker only. `up`, `down` and `install` run docker compose on the host; all other commands run `vendor/bin/crest` in the compose service. [#29](https://github.com/phalcon/crest/issues/29)
- Added `resources/setup.sh`: one command creates, starts and installs a project with docker only: `curl -fsSL https://phalcon.io/crest-setup | sh -s -- my-app`. [#30](https://github.com/phalcon/crest/issues/30)
- Added the `new` key to `crest.php`: the commands that `new` runs in the project after it writes the files, in order, for example `[['crest', 'up'], ['crest', 'install']]`. Add it to a published `project-config` stub. The first command that fails stops the list. Without the key, `new` runs nothing. [#32](https://github.com/phalcon/crest/issues/32)
- Added `Crest\Project\Steps`: reads and checks the `new` key. [#32](https://github.com/phalcon/crest/issues/32)
- Added `Crest\Console\Output::alias()` and `Crest\Console\Kernel::alias()`: show a folder as another folder in the output. In a container, crest shows the host paths of the project: the hand-off and `./crest` give it `CREST_HOST_ROOT`. [#42](https://github.com/phalcon/crest/issues/42)
- Added the path questions to `init`: a folder for each path, with a note if it exists. An answer must be in a psr-4 folder, and its folder names must give a usable namespace. A leading `./` is removed. The answer `none` gives no front controller. [#42](https://github.com/phalcon/crest/issues/42)
- Added the `bootstrap`, `runtime` and `service` rows to `config:show`. [#42](https://github.com/phalcon/crest/issues/42)
- Added `Crest\Project\Vendor`: the vendor and bin folders of a project, by the rules of composer. [#34](https://github.com/phalcon/crest/issues/34)
- Added `--help` to `resources/setup.sh`: it shows the options of `new`. [#42](https://github.com/phalcon/crest/issues/42)
- Added flavors to the project commands: `Crest\Command\ProjectCommand::flavors()` declares them, and `supports()` is the rule. A command for a different flavor stops with an error that names the flavor. `make:action`, `make:command`, `make:middleware`, `make:provider`, `make:responder`, `route:list`, `container:list` and `event:list` are for ADR projects only. [#44](https://github.com/phalcon/crest/issues/44)
- Added `Crest\Console\Registry::withDefault()` and `defaultName()`: the command that the kernel runs when the user names none. Crest runs `list`. [#44](https://github.com/phalcon/crest/issues/44)
- Added an optional filter to `Crest\Console\Registry::descriptions()`: it keeps only the commands that pass it. [#44](https://github.com/phalcon/crest/issues/44)
- Added `Crest\Project\Config::find()`: the configuration of the project, or null when there is no `crest.php`. `list` reads the project with one walk up. [#44](https://github.com/phalcon/crest/issues/44)
- Added `resources/stubs/shared/` (`Crest\Generator\Stub::SHARED`): the stubs that all flavors use. Crest looks for a stub in the project (`resources/stubs/<flavor>/`, then `resources/stubs/shared/`), then in the package (the same order). [#45](https://github.com/phalcon/crest/issues/45)
- Added `Crest\Generator\Stub::packagedFolder()` and `packagedFolders()`: the packaged folder of a stub, and of each stub of a flavor. The flavor folder wins on the same name. [#45](https://github.com/phalcon/crest/issues/45)
- Added `make:controller` for MVC projects: writes a controller that extends `CrestControllerBase`, a base class in `src/Controllers/Base/` that crest owns. Crest writes the base again when its stub changes, and writes the controller only once: a second run keeps the controller and updates the base. The name must give a class that the dispatcher finds from a URL. [#46](https://github.com/phalcon/crest/issues/46)
- Added the `controller` default path (`src/Controllers`) for MVC projects, and the MVC stubs `controller`, `controller-base` and `fragment-guidance-controller`. [#46](https://github.com/phalcon/crest/issues/46)
- Added `Crest\Generator\ArtifactWriter::renderOwned()`, `renderOnce()` and `MARKER`, and `Crest\Generator\Outcome`: the write rules for a generated base class and for a class that the developer owns. [#46](https://github.com/phalcon/crest/issues/46)
- Added `new --flavor=adr|mvc`: `crest new my-app --flavor=mvc` writes an MVC project that runs: `AppFront` with `Phalcon\Mvc\Application`, a dispatcher provider and a view provider in `src/Providers/`, `IndexController` and its base in `src/Controllers/`, and the views in `themes/default/`. `new` asks for the flavor first. [#47](https://github.com/phalcon/crest/issues/47)
- Added `new --template-engine`: the view engine of an MVC project. It accepts `phtml`, the default. An ADR project has no views, so the option stops `new`. [#47](https://github.com/phalcon/crest/issues/47)
- Added `stub:publish --flavor`: the flavor of a project stub before the project exists, `adr` by default. When the flavor does not have the project stub and another flavor does, the error says which: `pass --flavor=mvc`. [#47](https://github.com/phalcon/crest/issues/47)
- Added the MVC default paths `provider` (`src/Providers`) and `views` (`themes/default`), and the MVC project stubs `project-front`, `project-readme`, `project-provider-dispatcher`, `project-phtml-provider`, `project-phtml-layout` and `project-phtml-index`. [#47](https://github.com/phalcon/crest/issues/47)
- Added `Crest\Generator\Project`: `Skeleton` (the files, the values and the first artifacts of the project of a flavor), `AdrSkeleton`, `MvcSkeleton`, `Skeletons`, `Answers` and `TemplateEngines`, and `Crest\Generator\ControllerBase`. [#47](https://github.com/phalcon/crest/issues/47)
- Added the flavor question of `init`, and `init --flavor`: `init` proposes `adr` or `mvc` from the default folders of each flavor that the project has, and asks for it after the namespace. A tie gives `adr`. [#48](https://github.com/phalcon/crest/issues/48)
- Added `Crest\Project\Config::NO_CLASS_PATHS` (`views`), the path keys of folders with no classes, and `Crest\Project\Survey::flavor()`. [#48](https://github.com/phalcon/crest/issues/48)
- Added `make:model` for MVC projects: it reads a table through the `db` service of the project (crest boots the front controller that `bootstrap` names), and writes a model in `src/Models/` and its base in `src/Models/Base/`, which crest owns. The base has a typed nullable property for each column and `initialize()` with `setSource()`. A second run writes the base again from the table and keeps the model. [#50](https://github.com/phalcon/crest/issues/50)
- Added the `model` default path (`src/Models`) for MVC projects, and the MVC stubs `model`, `model-base` and `fragment-model-property`. [#50](https://github.com/phalcon/crest/issues/50)
- Added `Crest\Generator\ClassName::fromTable()` and `RESERVED`, `Crest\Generator\ModelProperties` and `Crest\Project\Database`. `ClassName::PATTERN` is public. [#50](https://github.com/phalcon/crest/issues/50)
- Added the relations to `make:model`: the base gets a `belongsTo()` for each foreign key of the table and a `hasMany()` for each foreign key that points to it, each with an alias. Each relation has a `RelationAlias` attribute in the base, and an alias that the developer changes there stays. A relation that crest cannot write is skipped, with one line of output. An attribute that the next base does not have is dropped, with one line of output. A table with no foreign keys gives one line that says so. [#51](https://github.com/phalcon/crest/issues/51)
- Added the MVC stubs `fragment-model-belongs-to` and `fragment-model-has-many`, the attribute `Crest\Attribute\RelationAlias`, and `Crest\Generator\ModelRelations`, `Crest\Generator\RelationAliases` and `Crest\Generator\ClassName::studly()`. [#51](https://github.com/phalcon/crest/issues/51)
- Added the database tests: the tests of `make:model` run on SQLite, MySQL 8.0, MariaDB 10.6 and PostgreSQL 18, with Phalcon v5 and v6, in docker and in CI. `composer test-db` runs the four suites and `composer test-db-<driver>` runs one; `composer test` runs no database test. [#65](https://github.com/phalcon/crest/issues/65)

### Changed

- `make:action` writes a `params()` declaration for routes with attributes.
- `make:action` rejects a static segment after a placeholder: `/album/{id}/edit` must be `/album/edit/{id}`.
- Renamed `Crest\Adr` to `Crest\ADR`, and the `Flavor` cases to `ADR`, `CLI` and `MVC`. Backed values are unchanged.
- Renamed `Crest\ADR\CandidateSource` to `ActionResolver`, and `PhalconRouterCandidates` to `PhalconRouterResolver`.
- Dependencies resolve against PHP 8.1 (`config.platform`).
- Default `paths` are per flavor. `adr` and `mvc` ([#46](https://github.com/phalcon/crest/issues/46)) have defaults of their own; `command` is in each flavor ([#45](https://github.com/phalcon/crest/issues/45)). [#5](https://github.com/phalcon/crest/issues/5)
- `crest`, `crest list` and `crest --version` show a chevron before the name. No color when piped or with `NO_COLOR`. [#5](https://github.com/phalcon/crest/issues/5)
- `make:action --responder=view` prints the template name. [#5](https://github.com/phalcon/crest/issues/5)
- `route:list` and `make:action` take an optional `ActionResolver`. [#5](https://github.com/phalcon/crest/issues/5)
- `event:list` reads all listeners with one `getListenerMap()` call. [#1](https://github.com/phalcon/crest/issues/1)
- `phalcon/talon` moved from `^0.8` to `^1.0.0`.
- phpcs and php-cs-fixer use the shared Phalcon rules from `phalcon/code-quality`, added to `require-dev`.
- `stub:publish` without a name skips the `project-*` stubs. Publish them by name; they go to the working directory or `--directory`. [#8](https://github.com/phalcon/crest/issues/8)
- Rendering a stub fails when a placeholder has no value. The error names the stub. [#8](https://github.com/phalcon/crest/issues/8)
- The config inference error names the missing psr-4 directories. [#18](https://github.com/phalcon/crest/issues/18)
- Changed `crest.php` to required. The project root is the folder of the `--config` file, or of the nearest `crest.php`, for all commands, the hand-off, `serve`, `up`, `down` and `install`. Without it, crest says `run 'crest init'`. [#28](https://github.com/phalcon/crest/issues/28)
- Changed `config:show`: a value that `crest.php` does not state has the origin `default`. [#28](https://github.com/phalcon/crest/issues/28)
- Changed the hand-off: a project with `crest.php` that does not require crest gets the `composer require --dev phalcon/crest` hint. [#28](https://github.com/phalcon/crest/issues/28)
- Changed `new`: `crest.php` states every path and the runtime. [#28](https://github.com/phalcon/crest/issues/28)
- Changed `install`: it uses the service of the `runtime` key. [#28](https://github.com/phalcon/crest/issues/28)
- Changed the `project-config` stub: its placeholders are `namespace`, `bootstrap`, `paths` and `runtime`. `bootstrap` and `paths` give whole lines. A copy that was published before this change stops `new` and `init`; publish it again. [#28](https://github.com/phalcon/crest/issues/28)
- Reserved the global option `--no-interaction` (`-n`). A contributed command that declares either one fails when it runs. [#28](https://github.com/phalcon/crest/issues/28)
- Changed `new`: it takes an optional `Runner`. [#32](https://github.com/phalcon/crest/issues/32)
- Changed the compose file of `new`: `init: true`, so `docker compose down` stops the container at once. For an existing project, add `init: true` to the service. [#42](https://github.com/phalcon/crest/issues/42)
- Changed the hand-off and `serve`: they use `vendor-dir` and `bin-dir` of `composer.json`, and `COMPOSER_VENDOR_DIR` and `COMPOSER_BIN_DIR`. The hand-off runs `<bin-dir>/crest`. A custom `vendor-dir` or `bin-dir` needs this version both as the global crest and as the crest of the project: an older crest looks only for `vendor/bin/crest`. [#34](https://github.com/phalcon/crest/issues/34)
- Changed `init`: the namespace must be in the psr-4 map of `composer.json`, and the other proposals follow it. [#42](https://github.com/phalcon/crest/issues/42)
- Changed the `runtime` key: the host runtime keeps a stated `service`, and `crest install` uses it. A `service` that is not a service name is an error. [#42](https://github.com/phalcon/crest/issues/42)
- Changed `./crest`: one `crest=` line names the crest of the project, and the crest commands get `CREST_HOST_ROOT`. [#42](https://github.com/phalcon/crest/issues/42)
- Changed `resources/setup.sh`: the working folder has the same path in the one-off container, so the errors of `new` show host paths. A system folder, for example `/usr`, is refused as the working folder. `APP_PORT` goes into the project `.env`. With rootless docker, it writes `UID=0` and `GID=0`, and does no `chown`. After a failed `up` or `install`, it prints the commands to continue. [#42](https://github.com/phalcon/crest/issues/42)
- Changed the listing: in a project, `crest` and `crest list` show only the commands for the flavor of the project. `crest --help` shows the help of `list`. An unknown option with no command, and a `crest.php` that is not valid, stop the listing with an error. [#44](https://github.com/phalcon/crest/issues/44)
- Changed `make:command`: it runs in each flavor, and the `command` default path is in each flavor. `command.stub` and `fragment-guidance-command.stub` moved to `resources/stubs/shared/`. [#45](https://github.com/phalcon/crest/issues/45)
- Changed `stub:publish`: it also publishes the shared stubs, into `resources/stubs/shared/`. When the flavor folder of the project has a copy with the same name, that copy wins, and a note names it. [#45](https://github.com/phalcon/crest/issues/45)
- Changed the project stubs: `project-compose`, `project-composer`, `project-config`, `project-dockerfile`, `project-env`, `project-gitignore`, `project-htrouter`, `project-index` and `project-launcher` are the same in both flavors, and moved to `resources/stubs/shared/`. A copy that was published into `resources/stubs/adr/` still wins for ADR projects, and has no effect on MVC projects. To use one copy for both flavors, publish it again. [#47](https://github.com/phalcon/crest/issues/47)
- Changed the `project-config` stub: `'flavor' => '{{ flavor }}'`. `Crest\Project\Settings` has the flavor as its first value. [#47](https://github.com/phalcon/crest/issues/47)
- Changed the error of the `new` key: it names the published `project-config` that `new` used. [#47](https://github.com/phalcon/crest/issues/47)
- Changed `new`: it checks that the written `crest.php` has the chosen flavor. A published `project-config` with a fixed flavor stops it, with an error that names the stub. [#47](https://github.com/phalcon/crest/issues/47)
- Moved `Crest\Command\Make\ControllerCommand::BASE` and `BASE_FOLDER` to `Crest\Generator\ControllerBase::NAME` and `FOLDER`. [#47](https://github.com/phalcon/crest/issues/47)
- Changed `init`: it asks one more question, the flavor, after the namespace, so a script that sends answers to `init` needs one more line. For an MVC project, it asks for the MVC paths. `views` is a folder in the project, with no psr-4 check; its proposal is the only folder in `themes/` when `themes/default` does not exist. [#48](https://github.com/phalcon/crest/issues/48)
- Changed `Crest\Project\Survey::paths()` and `settings()`: `paths()` takes the root, the folder and the flavor; `settings()` takes the root and the flavors to compare. [#48](https://github.com/phalcon/crest/issues/48)
- Changed `make:provider`: it also runs in MVC projects. There, it writes a provider that implements `Phalcon\Di\ServiceProviderInterface` into the `provider` path (`src/Providers`), and prints the line to add to `providers()` of the front controller. New MVC stubs: `provider` and `fragment-guidance-provider`. [#49](https://github.com/phalcon/crest/issues/49)
- Changed `init`: for an MVC project, it also asks for the `model` folder, so a script that sends answers to `init` needs one more line. [#50](https://github.com/phalcon/crest/issues/50)
- Changed the requirements: crest requires `ext-tokenizer` (PHP turns it on by default). `make:model` reads the `RelationAlias` attributes of a base with it. [#51](https://github.com/phalcon/crest/issues/51)
- Changed `Crest\Generator\Stub::render()`: it puts in all the values in one pass, so a value with the text of a placeholder goes into the file as it is, and the check for a missing value reads only the stub. [#51](https://github.com/phalcon/crest/issues/51)

### Fixed

- `make:middleware`, `make:provider` and `make:responder` generate valid code when the name is the suffix (`make:middleware Middleware`). [#5](https://github.com/phalcon/crest/issues/5)
- Generators exit 1 with `could not create <directory>` when the target cannot be created. [#5](https://github.com/phalcon/crest/issues/5)
- `stub:publish` rejects a name that is a path. [#5](https://github.com/phalcon/crest/issues/5)
- `ClassName::suffixed()` accepts non-Latin class names. [#5](https://github.com/phalcon/crest/issues/5)
- `container:list` and `event:list` type against Phalcon's published contracts (`Phalcon\Contracts\Container\Service\Collection`, `Enumerable`), not `Container` and `Manager`. [#1](https://github.com/phalcon/crest/issues/1)
- `route:list` gets the HTTP method from the router, not from the class name. [#1](https://github.com/phalcon/crest/issues/1)
- `bin/crest` loads the autoloader of its install, not the one in a dev checkout. A global crest from a symlinked path repository passes project commands to the crest of the project. [#33](https://github.com/phalcon/crest/issues/33)
- The error `no answer; input ended` starts on its own line. [#42](https://github.com/phalcon/crest/issues/42)

### Removed

- Removed the shadowed-action warning from `make:action`.
- Removed the `phalcon/cli-options-parser` requirement.
- Removed the inference of the namespace and paths from `composer.json` when there is no `crest.php`. [#28](https://github.com/phalcon/crest/issues/28)
- Removed the error `no stubs are packaged for the '<flavor>' flavor` of `stub:publish`: each flavor has the shared stubs. [#45](https://github.com/phalcon/crest/issues/45)

[keep_a_changelog]: https://keepachangelog.com/en/1.0.0/
[semantic_versioning]: https://semver.org/spec/v2.0.0.html
[Unreleased]: https://github.com/phalcon/crest/commits/master
