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

namespace Crest\Generator\Project;

use Crest\Generator\Stub;
use Crest\Project\Flavor;

/**
 * The project of one flavor for `new`: its view engines, its files, its
 * values and its first artifacts. NewCommand asks the questions, renders the
 * files that all flavors have, and writes. The project stubs that each flavor
 * has (project-front, project-readme) are in NewCommand::FILES: the stub
 * lookup finds the copy of the flavor.
 *
 * This is the adapter that the E03 rule "no adapter classes" does not cover:
 * that rule is about the make:* generators, where only the stub path
 * changes with the flavor.
 */
interface Skeleton
{
    /**
     * The view engines of the flavor.
     */
    public function engines(): TemplateEngines;

    /**
     * The file that answers GET /, relative to the project root.
     */
    public function entry(): string;

    /**
     * The project stubs of the flavor that NewCommand does not list: stub
     * name => path in the project.
     *
     * @return array<string, string>
     */
    public function files(Answers $answers): array;

    public function flavor(): Flavor;

    /**
     * The first artifacts of the project, from the stubs of the generators:
     * path => content.
     *
     * @return array<string, string>
     */
    public function seed(Stub $stub, Answers $answers): array;

    /**
     * The placeholders of the flavor.
     *
     * @return array<string, string>
     */
    public function values(Answers $answers): array;
}
