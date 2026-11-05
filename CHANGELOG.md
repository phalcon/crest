# Changelog

All notable changes are documented here. The format is based on [Keep a Changelog][keep_a_changelog] and this project adheres to [Semantic Versioning][semantic_versioning].

## [Unreleased]

### Added

- Added `route:list`: lists routes with their method and Action class. [#1](https://github.com/phalcon/crest/issues/1)
- Added `config:show`: shows the resolved configuration, each value marked declared or default. [#1](https://github.com/phalcon/crest/issues/1)
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
- Added `new`: creates an ADR project from stubs. Options: `--namespace`, `--php`, `--phalcon`. [#8](https://github.com/phalcon/crest/issues/8)
- Added `up`, `down` and `install`: `docker compose up -d`, `docker compose down` and `composer install` in the project container. [#8](https://github.com/phalcon/crest/issues/8)
- Added `Crest\Process\Runner` and `ShellRunner`: run external programs. A missing program or directory is a crest error. [#8](https://github.com/phalcon/crest/issues/8)
- Added `Crest\Generator\ClassName::namespace()`: validates a namespace. [#8](https://github.com/phalcon/crest/issues/8)
- Added `Crest\Command\Make\NamedArtifactCommand`: base of `make:command`, `make:controller`, `make:middleware`, `make:module`, `make:provider` and `make:responder`.
- Added `serve` (alias `server`): starts PHP's built-in web server for the project. [#10](https://github.com/phalcon/crest/issues/10)
- Added a package hint for unknown commands, for example `unknown command 'migration:run'; provided by phalcon/migrations`, and `Crest\Console\Registry::withProviders()`. [#19](https://github.com/phalcon/crest/issues/19)
- Added the hand-off: in a project, a global crest passes each project command to the crest of the project. [#27](https://github.com/phalcon/crest/issues/27)
- Added `Crest\Project\Locator::project()`: the nearest directory with a `composer.json`. [#27](https://github.com/phalcon/crest/issues/27)
- Added `init`: writes `crest.php` for an existing project. It proposes the values that it finds (namespace, front controller, runtime) and asks for each one. [#28](https://github.com/phalcon/crest/issues/28)
- Added the global option `--no-interaction` (`-n`): ask no questions and use the default answers. [#28](https://github.com/phalcon/crest/issues/28)
- Added the `runtime` key to `crest.php`: `host`, or `docker` with a compose service. [#28](https://github.com/phalcon/crest/issues/28)
- Added questions to `new` for the values that no option gives (namespace, PHP, Phalcon, runtime). [#28](https://github.com/phalcon/crest/issues/28)
- Added the options `--runtime=host|docker` and `--service` to `new`: they answer the runtime questions. [#28](https://github.com/phalcon/crest/issues/28)
- Added the launcher `./crest` to the projects that `new` creates: it runs crest with docker only. [#29](https://github.com/phalcon/crest/issues/29)
- Added `resources/setup.sh`: one command creates, starts and installs a project with docker only. [#30](https://github.com/phalcon/crest/issues/30)
- Added the `new` key to `crest.php`: the commands that `new` runs in the project after it writes the files. [#32](https://github.com/phalcon/crest/issues/32)
- Added `Crest\Project\Steps`: reads and checks the `new` key. [#32](https://github.com/phalcon/crest/issues/32)
- Added `Crest\Console\Output::alias()` and `Crest\Console\Kernel::alias()`: in a container, crest shows the host paths of the project, from `CREST_HOST_ROOT`. [#42](https://github.com/phalcon/crest/issues/42)
- Added the path questions to `init`: a folder for each path. [#42](https://github.com/phalcon/crest/issues/42)
- Added the `bootstrap`, `runtime` and `service` rows to `config:show`. [#42](https://github.com/phalcon/crest/issues/42)
- Added `Crest\Project\Vendor`: the vendor and bin folders of a project, by the rules of composer. [#34](https://github.com/phalcon/crest/issues/34)
- Added `--help` to `resources/setup.sh`: it shows the options of `new`. [#42](https://github.com/phalcon/crest/issues/42)
- Added flavors to the project commands: `Crest\Command\ProjectCommand::flavors()` declares them, and `supports()` is the rule. A command for a different flavor stops with an error that names the flavor. [#44](https://github.com/phalcon/crest/issues/44)
- Added `Crest\Console\Registry::withDefault()` and `defaultName()`: the command that the kernel runs when the user names none. Crest runs `list`. [#44](https://github.com/phalcon/crest/issues/44)
- Added an optional filter to `Crest\Console\Registry::descriptions()`: it keeps only the commands that pass it. [#44](https://github.com/phalcon/crest/issues/44)
- Added `Crest\Project\Config::find()`: the configuration of the project, or null when there is no `crest.php`. [#44](https://github.com/phalcon/crest/issues/44)
- Added `resources/stubs/shared/` (`Crest\Generator\Stub::SHARED`): the stubs that all flavors use. [#45](https://github.com/phalcon/crest/issues/45)
- Added `Crest\Generator\Stub::packagedFolder()` and `packagedFolders()`: the packaged folder of a stub, and of each stub of a flavor. [#45](https://github.com/phalcon/crest/issues/45)
- Added `make:controller` for MVC projects: writes a controller that extends `CrestControllerBase`, a base class in `src/Controllers/Base/` that crest owns. [#46](https://github.com/phalcon/crest/issues/46)
- Added the `controller` default path (`src/Controllers`) for MVC projects, and the MVC stubs `controller`, `controller-base` and `fragment-guidance-controller`. [#46](https://github.com/phalcon/crest/issues/46)
- Added `Crest\Generator\ArtifactWriter::renderOwned()`, `renderOnce()` and `MARKER`, and `Crest\Generator\Outcome`: the write rules for a generated base class and for a class that the developer owns. [#46](https://github.com/phalcon/crest/issues/46)
- Added `new --flavor=adr|mvc`: `crest new my-app --flavor=mvc` writes an MVC project that runs. `new` asks for the flavor first. [#47](https://github.com/phalcon/crest/issues/47)
- Added `new --template-engine`: the view engine of an MVC project. It accepts `phtml`, the default. [#47](https://github.com/phalcon/crest/issues/47)
- Added `stub:publish --flavor`: the flavor of a project stub before the project exists, `adr` by default. [#47](https://github.com/phalcon/crest/issues/47)
- Added the MVC default paths `provider` (`src/Providers`) and `views` (`themes/default`), and the MVC project stubs `project-front`, `project-readme`, `project-provider-dispatcher`, `project-phtml-provider`, `project-phtml-layout` and `project-phtml-index`. [#47](https://github.com/phalcon/crest/issues/47)
- Added `Crest\Generator\Project`: `Skeleton` (the files, the values and the first artifacts of the project of a flavor), `AdrSkeleton`, `MvcSkeleton`, `Skeletons`, `Answers` and `TemplateEngines`, and `Crest\Generator\ControllerBase`. [#47](https://github.com/phalcon/crest/issues/47)
- Added the flavor question of `init`, and `init --flavor`: `init` proposes `adr` or `mvc` from the default folders that the project has. [#48](https://github.com/phalcon/crest/issues/48)
- Added `Crest\Project\Config::NO_CLASS_PATHS` (`views`), the path keys of folders with no classes, and `Crest\Project\Survey::flavor()`. [#48](https://github.com/phalcon/crest/issues/48)
- Added `make:model` for MVC projects: it reads a table through the `db` service of the project, and writes a model in `src/Models/` and its base in `src/Models/Base/`, which crest owns. [#50](https://github.com/phalcon/crest/issues/50)
- Added the `model` default path (`src/Models`) for MVC projects, and the MVC stubs `model`, `model-base` and `fragment-model-property`. [#50](https://github.com/phalcon/crest/issues/50)
- Added `Crest\Generator\ClassName::fromTable()` and `RESERVED`, `Crest\Generator\ModelProperties` and `Crest\Project\Database`. `ClassName::PATTERN` is public. [#50](https://github.com/phalcon/crest/issues/50)
- Added the relations to `make:model`: the base gets a `belongsTo()` for each foreign key of the table and a `hasMany()` for each foreign key that points to it, each with an alias in a `RelationAlias` attribute. [#51](https://github.com/phalcon/crest/issues/51)
- Added the MVC stubs `fragment-model-belongs-to` and `fragment-model-has-many`, the attribute `Crest\Attribute\RelationAlias`, and `Crest\Generator\ModelRelations`, `Crest\Generator\RelationAliases` and `Crest\Generator\ClassName::studly()`. [#51](https://github.com/phalcon/crest/issues/51)
- Added the database tests on SQLite, MySQL 8.0, MariaDB 10.6 and PostgreSQL 18: `composer test-db` runs the four suites, and `composer test-db-<driver>` runs one. [#65](https://github.com/phalcon/crest/issues/65)
- Added `make:model --all`: it writes a model for each table of the database. `--exclude=a,b` skips tables. [#52](https://github.com/phalcon/crest/issues/52)
- Added `Crest\Generator\ForeignKeys` and `Crest\Generator\ModelTables`. [#52](https://github.com/phalcon/crest/issues/52)
- Added names to `make:model`: the `models` key of `crest.php`, with `classes` and `camelCase`, and `--property="column=name,..."` for one table. [#53](https://github.com/phalcon/crest/issues/53)
- Added `Crest\Attribute\PropertyName`, `Crest\Generator\BaseAttributes`, `Crest\Generator\ModelNames`, `Crest\Generator\PropertyNames`, `Crest\Generator\Sentence`, `Crest\Project\Config::models()`, `Crest\Generator\Stub::hasPlaceholder()`, `Crest\Generator\ForeignKeys::related()` and `using()`, and the MVC stub `fragment-model-column-map`. [#53](https://github.com/phalcon/crest/issues/53)
- Added `new --database=none|mariadb|mysql|pgsql|sqlite` and its question: the project gets the database service or file, the connection values, and a `DbProvider` that registers the `db` service. [#54](https://github.com/phalcon/crest/issues/54)
- Added `Crest\Generator\Project\DatabaseChoice`, the project stubs `project-provider-db` and `project-provider-db-sqlite` (ADR and MVC), and the shared project stubs `project-compose-mysql`, `project-compose-mariadb`, `project-compose-pgsql`, `project-env-database` and `project-env-sqlite`. [#54](https://github.com/phalcon/crest/issues/54)
- Added `phalcon/phql` to the `composer.json` of an MVC project with Phalcon v6 and a database. [#54](https://github.com/phalcon/crest/issues/54)
- Added the `models` key to the `crest.php` that `new` and `init` write for an MVC project, and `new --camel-case`. [#54](https://github.com/phalcon/crest/issues/54)
- Added the `models` key to `config:show` in an MVC project: a `camelCase` row and a `TABLE CLASS` table. Added `Crest\Generator\ModelNames::camelCase()`. [#71](https://github.com/phalcon/crest/issues/71)
- Added a `Note` line to `make:model` for a table whose class comes from `models.classes`: it names the old files and the related tables to run again. [#71](https://github.com/phalcon/crest/issues/71)
- Added `make:module` for MVC projects: it writes `Module.php` and the folders `Controllers`, `Models` and `views` in the `module` path (`src/Modules`), and prints how to register the module and its routes. [#55](https://github.com/phalcon/crest/issues/55)
- Added the `module` default path, the MVC stubs `module`, `fragment-guidance-module` and `fragment-guidance-controller-module`, `Crest\Generator\ModuleLocation` and `Crest\Generator\ArtifactWriter::directory()`. [#55](https://github.com/phalcon/crest/issues/55)
- Added `--module=<name>` to `make:controller` and `make:model`: they write into the module, with its namespace. [#55](https://github.com/phalcon/crest/issues/55)
- Added `make:scaffold` for MVC projects: it writes a model, a form, a controller and the views `index`, `new` and `edit` for one table. `--module` writes into a module. [#56](https://github.com/phalcon/crest/issues/56)
- Added the `form` default path (`src/Forms`), the MVC stubs `scaffold-form-base`, `scaffold-form`, `scaffold-controller`, `scaffold-view-index`, `scaffold-view-new`, `scaffold-view-edit`, `fragment-guidance-scaffold` and `fragment-guidance-scaffold-module`, `Crest\Generator\FormElements`, `Crest\Generator\ControllerRoute`, `ModelCommand::readTable()` and `ModelCommand::writeTable()`. [#56](https://github.com/phalcon/crest/issues/56)

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
- `stub:publish` without a name skips the `project-*` and `fragment-*` stubs. Publish them by name. [#8](https://github.com/phalcon/crest/issues/8)
- Rendering a stub fails when a placeholder has no value. The error names the stub. [#8](https://github.com/phalcon/crest/issues/8)
- Changed `crest.php` to required: the project root is its folder. Without it, crest says `run 'crest init'`. [#28](https://github.com/phalcon/crest/issues/28)
- Changed `config:show`: a value that `crest.php` does not state has the origin `default`. [#28](https://github.com/phalcon/crest/issues/28)
- Changed the hand-off: a project with `crest.php` that does not require crest gets the `composer require --dev phalcon/crest` hint. [#28](https://github.com/phalcon/crest/issues/28)
- Changed `new`: `crest.php` states every path and the runtime. [#28](https://github.com/phalcon/crest/issues/28)
- Changed `install`: it uses the service of the `runtime` key. [#28](https://github.com/phalcon/crest/issues/28)
- Changed the placeholders of the `project-config` stub. A copy that was published before this change stops `new` and `init`; publish it again. [#28](https://github.com/phalcon/crest/issues/28)
- Reserved the global option `--no-interaction` (`-n`). A contributed command that declares either one fails when it runs. [#28](https://github.com/phalcon/crest/issues/28)
- Changed `new`: it takes an optional `Runner`. [#32](https://github.com/phalcon/crest/issues/32)
- Changed the compose file of `new`: `init: true`, so `docker compose down` stops the container at once. For an existing project, add `init: true` to the service. [#42](https://github.com/phalcon/crest/issues/42)
- Changed the hand-off and `serve`: they use `vendor-dir` and `bin-dir` of `composer.json`, and `COMPOSER_VENDOR_DIR` and `COMPOSER_BIN_DIR`. [#34](https://github.com/phalcon/crest/issues/34)
- Changed `init`: the namespace must be in the psr-4 map of `composer.json`, and the other proposals follow it. [#42](https://github.com/phalcon/crest/issues/42)
- Changed the `runtime` key: the host runtime keeps a stated `service`, and `crest install` uses it. A `service` that is not a service name is an error. [#42](https://github.com/phalcon/crest/issues/42)
- Changed `./crest`: one `crest=` line names the crest of the project, and the crest commands get `CREST_HOST_ROOT`. [#42](https://github.com/phalcon/crest/issues/42)
- Changed `resources/setup.sh`: it refuses a system folder as the working folder, writes `APP_PORT` into the project `.env`, supports rootless docker, and prints the commands to continue after a failed `up` or `install`. [#42](https://github.com/phalcon/crest/issues/42)
- Changed the listing: in a project, `crest` and `crest list` show only the commands for the flavor of the project. [#44](https://github.com/phalcon/crest/issues/44)
- Changed `make:command`: it runs in each flavor, and the `command` default path is in each flavor. `command.stub` and `fragment-guidance-command.stub` moved to `resources/stubs/shared/`. [#45](https://github.com/phalcon/crest/issues/45)
- Changed `stub:publish`: it also publishes the shared stubs, into `resources/stubs/shared/`. [#45](https://github.com/phalcon/crest/issues/45)
- Moved the project stubs that are the same in both flavors to `resources/stubs/shared/`: `project-compose`, `project-composer`, `project-config`, `project-dockerfile`, `project-env`, `project-gitignore`, `project-htrouter`, `project-index` and `project-launcher`. [#47](https://github.com/phalcon/crest/issues/47)
- Changed the `project-config` stub: `'flavor' => '{{ flavor }}'`. `Crest\Project\Settings` has the flavor as its first value. [#47](https://github.com/phalcon/crest/issues/47)
- Changed the error of the `new` key: it names the published `project-config` that `new` used. [#47](https://github.com/phalcon/crest/issues/47)
- Changed `new`: it stops when the written `crest.php` does not have the chosen flavor. [#47](https://github.com/phalcon/crest/issues/47)
- Moved `Crest\Command\Make\ControllerCommand::BASE` and `BASE_FOLDER` to `Crest\Generator\ControllerBase::NAME` and `FOLDER`. [#47](https://github.com/phalcon/crest/issues/47)
- Changed `init`: it asks for the flavor after the namespace, so a script that sends answers to `init` needs one more line. For an MVC project, it asks for the MVC paths. [#48](https://github.com/phalcon/crest/issues/48)
- Changed `Crest\Project\Survey::paths()` and `settings()`: `paths()` takes the root, the folder and the flavor; `settings()` takes the root and the flavors to compare. [#48](https://github.com/phalcon/crest/issues/48)
- Changed `make:provider`: it also runs in MVC projects, with the MVC stubs `provider` and `fragment-guidance-provider`. [#49](https://github.com/phalcon/crest/issues/49)
- Changed `init`: for an MVC project, it also asks for the `model` folder, so a script that sends answers to `init` needs one more line. [#50](https://github.com/phalcon/crest/issues/50)
- Changed the requirements: crest requires `ext-tokenizer` (PHP turns it on by default). `make:model` reads the `RelationAlias` attributes of a base with it. [#51](https://github.com/phalcon/crest/issues/51)
- Changed `Crest\Generator\Stub::render()`: it puts in all the values in one pass, so a value with the text of a placeholder goes into the file as it is. [#51](https://github.com/phalcon/crest/issues/51)
- Changed `Crest\Generator\ModelRelations::of()`: its first argument is the `Crest\Generator\ForeignKeys` of the run, not the database adapter. [#52](https://github.com/phalcon/crest/issues/52)
- Changed the MVC stubs `model-base` (the placeholder `{{ columnMap }}`) and `fragment-model-property` (the placeholder `{{ property }}`): publish them again. [#53](https://github.com/phalcon/crest/issues/53)
- Moved `Crest\Generator\RelationAliases::read()` to `Crest\Generator\BaseAttributes::read()`. [#53](https://github.com/phalcon/crest/issues/53)
- Changed `Crest\Generator\ModelProperties::of()`: it no longer checks the names. [#53](https://github.com/phalcon/crest/issues/53)
- Changed `Crest\Generator\ModelTables::of()` and `Crest\Generator\ModelRelations::of()`: they take a `Crest\Generator\ModelNames`, and `ModelRelations::of()` takes the property names of the table, not its columns. [#53](https://github.com/phalcon/crest/issues/53)
- Changed the error of `make:model` for a column that is not a property name: it gives the `--property` to use. [#53](https://github.com/phalcon/crest/issues/53)
- Changed the lines of `make:model` for an alias with the name of a property of the table: they say `has the name of a property`, not `has the name of a column`. [#53](https://github.com/phalcon/crest/issues/53)
- Changed the project stubs `project-compose`, `project-dockerfile`, `project-env`, `project-gitignore`, `project-composer`, `project-config` and `project-front` (ADR and MVC): they have new placeholders. For a project with a database or `--camel-case`, publish a copy again. [#54](https://github.com/phalcon/crest/issues/54)
- Changed `new`: in a terminal, it asks for the database, so a script that sends answers to `new` needs one more line. `Crest\Generator\Project\Answers` has a new last argument `database`, and `Crest\Project\Settings` a new last argument `camelCase`. [#54](https://github.com/phalcon/crest/issues/54)
- Changed `init`: for an MVC project, it also asks for the `module` folder, so a script that sends answers to `init` needs one more line. `new` writes the `module` path into the `crest.php` of an MVC project. [#55](https://github.com/phalcon/crest/issues/55)
- Changed `init`: for an MVC project, it also asks for the `form` folder, so a script that sends answers to `init` needs one more line. `new` writes the `form` path into the `crest.php` of an MVC project. [#56](https://github.com/phalcon/crest/issues/56)
- Changed `new`: an MVC project gets `UrlProvider`, which sets the base URI to `/`. Added the stub `project-provider-url`. A published copy of `project-front` needs the `use` line of `UrlProvider` and the line `new UrlProvider(),`. [#56](https://github.com/phalcon/crest/issues/56)

### Fixed

- `make:middleware`, `make:provider` and `make:responder` generate valid code when the name is the suffix (`make:middleware Middleware`). [#5](https://github.com/phalcon/crest/issues/5)
- Generators exit 1 with `could not create <directory>` when the target cannot be created. [#5](https://github.com/phalcon/crest/issues/5)
- `stub:publish` rejects a name that is a path. [#5](https://github.com/phalcon/crest/issues/5)
- `ClassName::suffixed()` accepts non-Latin class names. [#5](https://github.com/phalcon/crest/issues/5)
- `container:list` and `event:list` type against Phalcon's published contracts (`Phalcon\Contracts\Container\Service\Collection`, `Enumerable`), not `Container` and `Manager`. [#1](https://github.com/phalcon/crest/issues/1)
- `route:list` gets the HTTP method from the router, not from the class name. [#1](https://github.com/phalcon/crest/issues/1)
- `bin/crest` loads the autoloader of its install, not the one in a dev checkout. A global crest from a symlinked path repository passes project commands to the crest of the project. [#33](https://github.com/phalcon/crest/issues/33)
- The error `no answer; input ended` starts on its own line. [#42](https://github.com/phalcon/crest/issues/42)
- The `Run make:model ... too` line of `make:model` no longer names a table that gets no class. [#71](https://github.com/phalcon/crest/issues/71)

### Removed

- Removed the shadowed-action warning from `make:action`.
- Removed the `phalcon/cli-options-parser` requirement.
- Removed the inference of the namespace and paths from `composer.json` when there is no `crest.php`. [#28](https://github.com/phalcon/crest/issues/28)
- Removed the error `no stubs are packaged for the '<flavor>' flavor` of `stub:publish`: each flavor has the shared stubs. [#45](https://github.com/phalcon/crest/issues/45)

[keep_a_changelog]: https://keepachangelog.com/en/1.0.0/
[semantic_versioning]: https://semver.org/spec/v2.0.0.html
[Unreleased]: https://github.com/phalcon/crest/commits/master
