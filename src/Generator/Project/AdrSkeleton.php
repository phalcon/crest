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
use Crest\Project\Config;
use Crest\Project\Flavor;

/**
 * The ADR project of `new`: a seed action answers GET /. ADR has no views,
 * so it has no engines.
 */
final class AdrSkeleton implements Skeleton
{
    /**
     * The class of the seed action, which answers GET /. This is a copy of the
     * framework routing rule, because there is no vendor/ to ask yet.
     * GeneratedProjectTest sends GET / through the generated application to
     * make sure that the copy is correct.
     */
    private const SEED = 'Get';

    public function engines(): TemplateEngines
    {
        return new TemplateEngines($this->flavor(), []);
    }

    public function entry(): string
    {
        return $this->actionPath() . '/' . self::SEED . '.php';
    }

    public function files(Answers $answers): array
    {
        return [];
    }

    public function flavor(): Flavor
    {
        return Flavor::ADR;
    }

    /**
     * The seed action uses the usual action stub. Convention cannot name it,
     * because Convention asks the router, and there is no vendor/ yet.
     */
    public function seed(Stub $stub, Answers $answers): array
    {
        return [
            $this->entry() => $stub->render(
                $this->flavor()->value,
                'action',
                [
                    'attributes' => '',
                    'class'      => self::SEED,
                    'namespace'  => $this->actionNamespace($answers),
                    'params'     => '',
                ]
            ),
        ];
    }

    public function values(Answers $answers): array
    {
        return [
            'actionNamespace' => $this->actionNamespace($answers),
            'actionPath'      => $this->actionPath(),
            'seed'            => self::SEED,
        ];
    }

    private function actionNamespace(Answers $answers): string
    {
        return $answers->namespace . '\\Action';
    }

    /**
     * Where the actions go, relative to the project root. The seed action and
     * the front controller use it.
     */
    private function actionPath(): string
    {
        return Config::defaultPaths(Flavor::ADR)['action'];
    }
}
