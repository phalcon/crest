# Changelog

All notable changes are documented here. The format is based on [Keep a Changelog][keep_a_changelog] and this project adheres to [Semantic Versioning][semantic_versioning].

## [Unreleased]

### Added

- Added `route:list`, listing every route the application answers with its method and Action class. Reads the Action classes rather than a route table, since ADR has none. [#1](https://github.com/phalcon/crest/issues/1)
- Added `config:show`, showing the resolved project configuration and marking each value as declared or inferred. [#1](https://github.com/phalcon/crest/issues/1)
- Added `container:list`, listing the services registered in the project container with their class and whether they have been resolved. [#1](https://github.com/phalcon/crest/issues/1)
- Added `event:list`, listing the listeners attached to the project events manager. [#1](https://github.com/phalcon/crest/issues/1)
- Added `list` (aliases `commands`, `enumerate`), listing the available commands. [#1](https://github.com/phalcon/crest/issues/1)
- Added the `bootstrap` key to `crest.php`, naming the project front controller so commands that need a running application can boot one: `'bootstrap' => App\Front\AppFront::class`. Requires a `boot()` returning a container. [#1](https://github.com/phalcon/crest/issues/1)
- Added `Crest\Console\Input::argumentString()`, `optionString()` and `optionStringOrNull()`, narrowing the common string case so commands do not each repeat a type guard.

### Changed

- `make:action` now writes a `params()` declaration for routes with attributes, so they arrive constrained and cast rather than as raw strings.
- `make:action` now rejects a static segment after a placeholder and suggests the supported spelling: `/album/{id}/edit` is reported as `/album/edit/{id}`. Arguments trail the static path, so the first form has no class name that describes it.
- Renamed `Crest\Adr` to `Crest\ADR`, and `Flavor::Adr`, `Flavor::Cli` and `Flavor::Mvc` to `Flavor::ADR`, `Flavor::CLI` and `Flavor::MVC`, matching `Phalcon\ADR`. Backed values are unchanged.
- Renamed `Crest\ADR\CandidateSource` to `ActionResolver` and `PhalconRouterCandidates` to `PhalconRouterResolver`. One path now names exactly one Action, so there are no candidates to choose between.
- Dependencies now resolve against the PHP 8.1 floor via `config.platform`, so the lock matches the declared minimum.

### Removed

- Removed the shadowed-action warning from `make:action`. One path names exactly one Action, so nothing can be shadowed.

[keep_a_changelog]: https://keepachangelog.com/en/1.0.0/
[semantic_versioning]: https://semver.org/spec/v2.0.0.html
[Unreleased]: https://github.com/phalcon/crest/commits/master
