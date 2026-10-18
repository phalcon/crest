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

namespace Crest\Generator;

use Crest\Console\Exceptions\Exception;

use function basename;
use function file_get_contents;
use function glob;
use function is_file;
use function preg_match_all;
use function rtrim;
use function sort;
use function sprintf;
use function str_contains;
use function strtr;

/**
 * Loads a stub and substitutes placeholders. Plain string replacement,
 * deliberately not a template engine.
 *
 * The lookup checks four files and takes the first that exists: the project
 * flavor folder, the project shared folder, the packaged flavor folder, the
 * packaged shared folder. A copy that the project published always wins over
 * a packaged one; on one level, a flavor stub wins over a shared stub.
 *
 * The static path builders exist so that stub:publish writes exactly where
 * resolve() reads. Nothing outside this class assembles a stub path.
 */
final class Stub
{
    /**
     * The name prefix of the stubs that are not a whole file: a piece of
     * code that a generator puts into a file, or text that a command prints.
     */
    public const FRAGMENT_PREFIX = 'fragment-';

    /**
     * The name prefix of the stubs that `crest new` renders into a new
     * project. They are not artifact stubs, so stub:publish with no name
     * leaves them out.
     */
    public const PROJECT_PREFIX = 'project-';

    /**
     * The folder of the stubs that all flavors use, in the package and in the
     * project: a stub that is the same in each flavor, for example a crest
     * command.
     */
    public const SHARED = 'shared';

    /**
     * Where a project keeps the stubs it has taken over, relative to its root.
     *
     * Private: callers ask for a path rather than assembling one, so the layout
     * lives here alone. Two copies of the convention that drifted would put
     * published stubs somewhere resolution never looks - silent, and maddening
     * to diagnose.
     */
    private const OVERRIDE_DIRECTORY = 'resources/stubs';

    /**
     * A placeholder: a name between `{{ ` and ` }}`.
     */
    private const PLACEHOLDER = '/\{\{ \w+ \}\}/';

    private string $packagedRoot;

    private ?string $projectRoot;

    /**
     * Roots are stored as given. Normalizing a trailing slash is the path
     * builders' job, and doing it here as well would mean two places could be
     * changed independently while the tests still passed.
     */
    public function __construct(string $packagedRoot, ?string $projectRoot = null)
    {
        $this->packagedRoot = $packagedRoot;
        $this->projectRoot  = $projectRoot;
    }

    /**
     * Where a project's own copy of a stub lives - the path stub:publish writes
     * and resolve() prefers. Returned whether or not it exists.
     */
    public static function overridePath(string $projectRoot, string $flavor, string $name): string
    {
        return self::packagedPath(
            rtrim($projectRoot, '/') . '/' . self::OVERRIDE_DIRECTORY,
            $flavor,
            $name
        );
    }

    /**
     * The directory a package keeps a flavor's stubs in. The whole-directory
     * answer, for callers that enumerate rather than name one stub.
     */
    public static function packagedDirectory(string $packagedRoot, string $flavor): string
    {
        return rtrim($packagedRoot, '/') . '/' . $flavor;
    }

    /**
     * A stub's location as shipped in a package.
     */
    public static function packagedPath(string $packagedRoot, string $flavor, string $name): string
    {
        return self::packagedDirectory($packagedRoot, $flavor) . '/' . $name . '.stub';
    }

    /**
     * The folders of one level of the lookup, in their order: the flavor
     * folder, then the shared folder.
     *
     * @return list<string>
     */
    private static function folders(string $flavor): array
    {
        return [$flavor, self::SHARED];
    }

    /**
     * Whether the project or the package has the stub.
     */
    public function has(string $flavor, string $name): bool
    {
        return null !== $this->find($flavor, $name);
    }

    /**
     * Whether the stub that crest uses has the placeholder. A copy that a
     * project published before a new placeholder does not have it, and
     * render() drops a value that has no placeholder (#53).
     */
    public function hasPlaceholder(string $flavor, string $name, string $placeholder): bool
    {
        return str_contains((string) file_get_contents($this->resolve($flavor, $name)), '{{ ' . $placeholder . ' }}');
    }

    /**
     * The packaged folder that has the stub: the flavor folder, else the
     * shared folder. Null when neither has it.
     */
    public function packagedFolder(string $flavor, string $name): ?string
    {
        foreach (self::folders($flavor) as $folder) {
            if (true === is_file(self::packagedPath($this->packagedRoot, $folder, $name))) {
                return $folder;
            }
        }

        return null;
    }

    /**
     * Every packaged stub of the flavor, name => folder. On the same name, the
     * flavor folder wins. The flavor stubs come first, then the shared stubs,
     * each in byte order of the file name: glob() sorts with the collation of
     * the locale, so its order changes from machine to machine, and sort()
     * does not. The file name, not the name: `action-view.stub` comes before
     * `action.stub`, as stub:publish has always listed them.
     *
     * @return array<string, string>
     */
    public function packagedFolders(string $flavor): array
    {
        $folders = [];

        foreach (self::folders($flavor) as $folder) {
            $paths = glob(self::packagedDirectory($this->packagedRoot, $folder) . '/*.stub') ?: [];

            sort($paths);

            foreach ($paths as $path) {
                $folders[basename($path, '.stub')] ??= $folder;
            }
        }

        return $folders;
    }

    /**
     * Fails when a placeholder of the stub has no value. A published copy can
     * keep a placeholder that crest no longer sends, and the raw placeholder
     * must not go into the generated file.
     *
     * All the values go in with one strtr(), so crest never scans a value
     * that is already in the text: a value can have the text of a
     * placeholder, for example a column name of another table (#51).
     *
     * @param array<string, string> $replacements
     */
    public function render(string $flavor, string $name, array $replacements): string
    {
        $path     = $this->resolve($flavor, $name);
        $template = (string) file_get_contents($path);
        $pairs    = [];

        foreach ($replacements as $key => $value) {
            $pairs['{{ ' . $key . ' }}'] = $value;
        }

        preg_match_all(self::PLACEHOLDER, $template, $matches);

        foreach ($matches[0] as $placeholder) {
            if (false === isset($pairs[$placeholder])) {
                throw new Exception(sprintf('%s has no value for %s', $path, $placeholder));
            }
        }

        return strtr($template, $pairs);
    }

    public function resolve(string $flavor, string $name): string
    {
        return $this->find($flavor, $name)
            ?? throw new Exception(sprintf("stub '%s/%s' not found", $flavor, $name));
    }

    /**
     * The project flavor folder, the project shared folder, then the package
     * (packagedFolder()). Null when no folder has the stub.
     */
    private function find(string $flavor, string $name): ?string
    {
        if (null !== $this->projectRoot) {
            foreach (self::folders($flavor) as $folder) {
                $override = self::overridePath($this->projectRoot, $folder, $name);

                if (true === is_file($override)) {
                    return $override;
                }
            }
        }

        $folder = $this->packagedFolder($flavor, $name);

        return null === $folder ? null : self::packagedPath($this->packagedRoot, $folder, $name);
    }
}
