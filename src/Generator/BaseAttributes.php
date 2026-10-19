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

use CompileError;
use Crest\Console\Exceptions\Exception;

use function array_slice;
use function count;
use function file_get_contents;
use function in_array;
use function is_array;
use function is_file;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function strtr;
use function substr;
use function token_get_all;

use const T_ATTRIBUTE;
use const T_CLASS;
use const T_COMMENT;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_CURLY_OPEN;
use const T_DOC_COMMENT;
use const T_DOUBLE_ARROW;
use const T_FUNCTION;
use const T_RETURN;
use const T_STRING;
use const T_VARIABLE;
use const T_WHITESPACE;
use const TOKEN_PARSE;

/**
 * What make:model reads from a model base before it writes the base again,
 * in one pass of the PHP tokenizer (#51, #53):
 *
 * - the RelationAlias attributes: the alias of each relation, by its key;
 * - the PropertyName attributes: the property name of a column;
 * - the pairs of columnMap(), and the properties of the class, for the
 *   Renamed lines.
 *
 * The developer can change an alias or a property name in its attribute,
 * and crest keeps it. crest reads the file with the tokenizer and runs
 * nothing, so a formatter can change the layout of an attribute and the read
 * still works. A file that does not exist, or that has no
 * ArtifactWriter::MARKER, gives nothing: a file without the marker belongs to
 * the developer (#46).
 *
 * @phpstan-import-type Stored from ModelRelations
 * @phpstan-type Token array{int, string, int}|string
 * @phpstan-type Names array<string, array{name: string, line: int}>
 */
final class BaseAttributes
{
    /**
     * The tokens that have no meaning for the read.
     */
    private const IGNORED = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    /**
     * The name of an attribute that crest reads: RelationAlias or
     * PropertyName after the last `\`, in any case, as PHP finds a class.
     */
    private const NAME = '/(?:^|\\\\)(RelationAlias|PropertyName)\z/i';

    /**
     * The error for an attribute without two plain strings. It says what a
     * plain string is.
     */
    private const NEEDS = '%s, line %d: a %s attribute needs two plain strings '
        . '(single quotes, or double quotes with no backslash), the %s and the %s';

    /**
     * The arguments of each attribute, in the order of its constructor.
     */
    private const SLOTS = [
        'PropertyName'  => ['column', 'name'],
        'RelationAlias' => ['key', 'alias'],
    ];

    /**
     * @param Stored                     $aliases    The alias and the line of each RelationAlias, by key
     * @param Names                      $properties The name and the line of each PropertyName, by column
     * @param array<string, string>|null $map        The pairs of columnMap(); null for no columnMap()
     * @param bool                       $readable   False for a columnMap() that crest cannot read
     * @param list<string>               $declared   The properties that the class declares
     */
    private function __construct(
        public readonly array $aliases,
        public readonly array $properties,
        private readonly ?array $map,
        private readonly bool $readable,
        private readonly array $declared,
    ) {
    }

    public static function read(string $file): self
    {
        if (false === is_file($file)) {
            return new self([], [], null, true, []);
        }

        // Suppressed for the reason given at ArtifactWriter::write(): the
        // exception is the one line that tells what went wrong.
        $contents = @file_get_contents($file);

        if (false === $contents) {
            throw new Exception(sprintf('could not read %s', $file));
        }

        if (false === str_contains($contents, ArtifactWriter::MARKER)) {
            return new self([], [], null, true, []);
        }

        $tokens     = self::tokens($file, $contents);
        $aliases    = [];
        $properties = [];

        foreach ($tokens as $at => $token) {
            if (false === is_array($token) || T_ATTRIBUTE !== $token[0]) {
                continue;
            }

            foreach (self::group($file, $tokens, $at + 1) as [$kind, $first, $second, $line]) {
                $found = 'RelationAlias' === $kind ? isset($aliases[$first]) : isset($properties[$first]);

                if (true === $found) {
                    throw new Exception(
                        sprintf("%s, line %d: two %s attributes for '%s'", $file, $line, $kind, $first)
                    );
                }

                if ('RelationAlias' === $kind) {
                    $aliases[$first] = ['alias' => $second, 'line' => $line];
                } else {
                    $properties[$first] = ['name' => $second, 'line' => $line];
                }
            }
        }

        [$map, $readable] = self::map($tokens);

        return new self($aliases, $properties, $map, $readable, self::declared($tokens));
    }

    /**
     * The key, the alias and the line of one RelationAlias attribute, or the
     * column, the name and the line of one PropertyName attribute. Each
     * argument is a plain string, positional or named, and a `,` can come
     * after each one. A property name is checked by ModelNames, with the
     * other names.
     *
     * @param 'PropertyName'|'RelationAlias' $kind
     * @param list<Token>                    $tokens The tokens between the parentheses
     *
     * @return array{string, string, string, int}
     */
    private static function arguments(string $file, string $kind, array $tokens, int $line): array
    {
        $slots  = self::SLOTS[$kind];
        $values = [];
        $at     = 0;
        $count  = count($tokens);

        while ($at < $count) {
            // A positional argument fills the next slot.
            $slot  = $slots[count($values)] ?? null;
            $token = $tokens[$at];

            // A named argument: a name, then `:`.
            if (true === is_array($token) && T_STRING === $token[0] && ':' === ($tokens[$at + 1] ?? null)) {
                $slot = $token[1];
                $at  += 2;
            }

            $text = self::text($tokens[$at] ?? '');

            if (null === $text || false === in_array($slot, $slots, true) || true === isset($values[$slot])) {
                throw new Exception(sprintf(self::NEEDS, $file, $line, $kind, $slots[0], $slots[1]));
            }

            $values[$slot] = $text;
            $at++;

            if (',' === ($tokens[$at] ?? null)) {
                $at++;
            }
        }

        if (false === isset($values[$slots[0]], $values[$slots[1]])) {
            throw new Exception(sprintf(self::NEEDS, $file, $line, $kind, $slots[0], $slots[1]));
        }

        if ('RelationAlias' === $kind && 1 !== preg_match(ClassName::PATTERN, $values[$slots[1]])) {
            throw new Exception(sprintf(
                "%s, line %d: the alias '%s' is not a valid name",
                $file,
                $line,
                $values[$slots[1]]
            ));
        }

        return [$kind, $values[$slots[0]], $values[$slots[1]], $line];
    }

    /**
     * The index of the `)` that closes the `(` at $at. TOKEN_PARSE has
     * checked that each `(` has its `)`.
     *
     * @param list<Token> $tokens
     */
    private static function closing(array $tokens, int $at): int
    {
        $depth = 1;

        while (0 < $depth) {
            $at++;
            $depth += match ($tokens[$at]) {
                '('     => 1,
                ')'     => -1,
                default => 0,
            };
        }

        return $at;
    }

    /**
     * The properties that the class declares: each variable in the body of
     * the class, outside the methods and their parameters. With no
     * columnMap(), each property has the name of its column (#50 to #52).
     * crest writes no `${...}` in a string, and PHP 8.2 deprecates it, so the
     * read does not count it as a brace.
     *
     * @param list<Token> $tokens
     *
     * @return list<string>
     */
    private static function declared(array $tokens): array
    {
        $declared = [];
        $inside   = false;
        $depth    = 0;
        $round    = 0;

        foreach ($tokens as $token) {
            if (false === $inside) {
                $inside = true === is_array($token) && T_CLASS === $token[0];

                continue;
            }

            if ('{' === $token || (true === is_array($token) && T_CURLY_OPEN === $token[0])) {
                $depth++;
            } elseif ('}' === $token) {
                $depth--;

                if (0 === $depth) {
                    break;
                }
            } elseif ('(' === $token) {
                $round++;
            } elseif (')' === $token) {
                $round--;
            } elseif (1 === $depth && 0 === $round && true === is_array($token) && T_VARIABLE === $token[0]) {
                $declared[] = substr($token[1], 1);
            }
        }

        return $declared;
    }

    /**
     * The key, the alias and the line of each RelationAlias attribute, and
     * the column, the name and the line of each PropertyName attribute, in
     * one group, from the token after `#[`. TOKEN_PARSE has checked the
     * syntax: each attribute is a name with an optional list of arguments,
     * and a `,` or the `]` of the group comes after it.
     *
     * @param list<Token> $tokens
     *
     * @return list<array{string, string, string, int}>
     */
    private static function group(string $file, array $tokens, int $at): array
    {
        $found = [];

        while (']' !== $tokens[$at]) {
            $name      = $tokens[$at];
            $arguments = [];
            $at++;

            if ('(' === $tokens[$at]) {
                $close     = self::closing($tokens, $at);
                $arguments = array_slice($tokens, $at + 1, $close - $at - 1);
                $at        = $close + 1;
            }

            if (true === is_array($name) && 1 === preg_match(self::NAME, $name[1], $match)) {
                $kind    = 'propertyname' === strtolower($match[1]) ? 'PropertyName' : 'RelationAlias';
                $found[] = self::arguments($file, $kind, $arguments, $name[2]);
            }

            // A `,` comes between two attributes, and can come after the last.
            if (',' === $tokens[$at]) {
                $at++;
            }
        }

        return $found;
    }

    /**
     * The pairs of columnMap(), and false when columnMap() exists but crest
     * cannot read it. crest reads only the form that crest writes: a body
     * with only `return [...];`, and a plain string on each side of each
     * `=>`. A published fragment can give another form. Null for a class with
     * no columnMap().
     *
     * @param list<Token> $tokens
     *
     * @return array{array<string, string>|null, bool}
     */
    private static function map(array $tokens): array
    {
        foreach ($tokens as $at => $token) {
            $next = $tokens[$at + 1] ?? null;

            if (
                true === is_array($token) && T_FUNCTION === $token[0]
                && true === is_array($next) && T_STRING === $next[0]
                // PHP ignores the case of a method name.
                && 'columnmap' === strtolower($next[1])
            ) {
                return self::pairs($tokens, $at + 2);
            }
        }

        return [null, true];
    }

    /**
     * The pairs of the body of columnMap(), from the token after its name.
     *
     * @param list<Token> $tokens
     *
     * @return array{array<string, string>|null, bool}
     */
    private static function pairs(array $tokens, int $at): array
    {
        $count = count($tokens);

        // The body starts at the first `{`. A `;` first: no body.
        while ($at < $count && '{' !== $tokens[$at] && ';' !== $tokens[$at]) {
            $at++;
        }

        $return = $tokens[$at + 1] ?? null;

        if (
            '{' !== ($tokens[$at] ?? null)
            || false === is_array($return) || T_RETURN !== $return[0]
            || '[' !== ($tokens[$at + 2] ?? null)
        ) {
            return [null, false];
        }

        $at   += 3;
        $pairs = [];

        while (']' !== ($tokens[$at] ?? ']')) {
            $column = self::text($tokens[$at] ?? '');
            $arrow  = $tokens[$at + 1] ?? null;
            $name   = self::text($tokens[$at + 2] ?? '');

            if (null === $column || null === $name || false === is_array($arrow) || T_DOUBLE_ARROW !== $arrow[0]) {
                return [null, false];
            }

            $pairs[$column] = $name;
            $at            += 3;

            if (',' === ($tokens[$at] ?? null)) {
                $at++;
            }
        }

        // The `]`, the `;`, and the `}` of the body: nothing more.
        if (
            ']' !== ($tokens[$at] ?? null)
            || ';' !== ($tokens[$at + 1] ?? null)
            || '}' !== ($tokens[$at + 2] ?? null)
        ) {
            return [null, false];
        }

        return [$pairs, true];
    }

    /**
     * The value of a plain string: single quotes, or double quotes with no
     * backslash. Null for each other token: a double-quoted string has many
     * escapes, and crest does not decode them.
     *
     * @param Token $token
     */
    private static function text(array | string $token): ?string
    {
        if (false === is_array($token) || T_CONSTANT_ENCAPSED_STRING !== $token[0]) {
            return null;
        }

        $inner = substr($token[1], 1, -1);

        if (true === str_starts_with($token[1], "'")) {
            return strtr($inner, ['\\\\' => '\\', "\\'" => "'"]);
        }

        if (true === str_starts_with($token[1], '"') && false === str_contains($inner, '\\')) {
            return $inner;
        }

        return null;
    }

    /**
     * The tokens of the file, with no spaces and no comments. TOKEN_PARSE
     * checks the syntax, so a base that is not valid PHP stops crest. The
     * parser also finds some compile errors (two access modifiers), and PHP
     * throws a CompileError for them. ParseError extends CompileError.
     *
     * @return list<Token>
     */
    private static function tokens(string $file, string $contents): array
    {
        try {
            $tokens = token_get_all($contents, TOKEN_PARSE);
        } catch (CompileError $error) {
            throw new Exception(sprintf(
                '%s is not valid PHP: %s on line %d',
                $file,
                $error->getMessage(),
                $error->getLine()
            ));
        }

        $kept = [];

        foreach ($tokens as $token) {
            if (false === is_array($token) || false === in_array($token[0], self::IGNORED, true)) {
                $kept[] = $token;
            }
        }

        return $kept;
    }

    /**
     * The columns whose property name changes in the next base: column =>
     * [old, new], in the order of $next. The old name comes from
     * columnMap(), or, with no columnMap(), it is the column, when the class
     * declares it. A column that the old base does not have is new, not
     * renamed. Null when the base has a columnMap() that crest cannot read.
     *
     * @param array<string, string> $next The column and its property in the next base
     *
     * @return array<string, array{string, string}>|null
     */
    public function renamed(array $next): ?array
    {
        if (false === $this->readable) {
            return null;
        }

        $renamed = [];

        foreach ($next as $column => $name) {
            $column = (string) $column;
            $old    = null === $this->map
                ? (true === in_array($column, $this->declared, true) ? $column : null)
                : $this->map[$column] ?? null;

            if (null !== $old && $old !== $name) {
                $renamed[$column] = [$old, $name];
            }
        }

        return $renamed;
    }
}
