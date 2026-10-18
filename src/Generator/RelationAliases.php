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
use Crest\Attribute\RelationAlias;
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
use function strtr;
use function substr;
use function token_get_all;
use function var_export;

use const T_ATTRIBUTE;
use const T_COMMENT;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DOC_COMMENT;
use const T_STRING;
use const T_WHITESPACE;
use const TOKEN_PARSE;

/**
 * The RelationAlias attributes of a model base (#51). Each attribute names a
 * foreign key and its alias:
 *
 *     #[\Crest\Attribute\RelationAlias('belongsTo invoices(inv_cst_id) -> customers(cst_id)', alias: 'InvCst')]
 *
 * The developer can change the alias. make:model reads the attributes before
 * it writes the base again, and keeps the aliases. crest reads the file with
 * the PHP tokenizer and runs nothing, so a formatter can change the layout of
 * an attribute and the read still works.
 *
 * @phpstan-import-type Relation from ModelRelations
 * @phpstan-type Token array{int, string, int}|string
 */
final class RelationAliases
{
    /**
     * The tokens that have no meaning for the read.
     */
    private const IGNORED = [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT];

    /**
     * The name of the attribute: RelationAlias after the last `\`, in any
     * case, as PHP finds a class.
     */
    private const NAME = '/(?:^|\\\\)RelationAlias\z/i';

    /**
     * The error for an attribute without two plain strings. It says what a
     * plain string is.
     */
    private const NEEDS = '%s, line %d: a RelationAlias attribute needs two plain strings '
        . '(single quotes, or double quotes with no backslash), the key and the alias';

    private const NOTE = " *\n"
        . " * You can change the alias in a RelationAlias attribute: crest keeps it when\n"
        . " * it writes this file again.\n";

    /**
     * The arguments, in the order of the constructor of RelationAlias.
     */
    private const SLOTS = ['key', 'alias'];

    /**
     * One attribute line for each relation, in the order of the relations:
     * the key, quoted with var_export(), and the alias as a named argument.
     * '' for no relations. Each line ends with a newline, because the stub
     * has no newline after the placeholder.
     *
     * @param list<Relation> $relations
     */
    public static function attributes(array $relations): string
    {
        $text = '';

        foreach ($relations as $relation) {
            $text .= sprintf(
                "#[\\%s(%s, alias: '%s')]\n",
                RelationAlias::class,
                var_export($relation['key'], true),
                $relation['alias']
            );
        }

        return $text;
    }

    /**
     * The paragraph for the docblock of the base that tells the developer
     * about the attributes. '' for no relations, so a base with no relations
     * keeps the docblock of #50.
     *
     * @param list<Relation> $relations
     */
    public static function note(array $relations): string
    {
        return [] === $relations ? '' : self::NOTE;
    }

    /**
     * The alias and the line of each RelationAlias attribute in a base, by
     * key. The line goes into the errors of ModelRelations. A file that does
     * not exist, or has no ArtifactWriter::MARKER, gives no aliases: a file
     * without the marker belongs to the developer (#46).
     *
     * @return array<string, array{alias: string, line: int}>
     */
    public static function read(string $file): array
    {
        if (false === is_file($file)) {
            return [];
        }

        // Suppressed for the reason given at ArtifactWriter::write(): the
        // exception is the one line that tells what went wrong.
        $contents = @file_get_contents($file);

        if (false === $contents) {
            throw new Exception(sprintf('could not read %s', $file));
        }

        if (false === str_contains($contents, ArtifactWriter::MARKER)) {
            return [];
        }

        $tokens  = self::tokens($file, $contents);
        $aliases = [];

        foreach ($tokens as $at => $token) {
            if (false === is_array($token) || T_ATTRIBUTE !== $token[0]) {
                continue;
            }

            foreach (self::group($file, $tokens, $at + 1) as [$key, $alias, $line]) {
                if (true === isset($aliases[$key])) {
                    throw new Exception(sprintf(
                        "%s, line %d: two RelationAlias attributes for '%s'",
                        $file,
                        $line,
                        $key
                    ));
                }

                $aliases[$key] = ['alias' => $alias, 'line' => $line];
            }
        }

        return $aliases;
    }

    /**
     * The key, the alias and the line of one RelationAlias attribute. Each
     * argument is a plain string, positional or named (`key:`, `alias:`), and
     * a `,` can come after each one.
     *
     * @param list<Token> $tokens The tokens between the parentheses
     *
     * @return array{string, string, int}
     */
    private static function arguments(string $file, array $tokens, int $line): array
    {
        $values = [];
        $at     = 0;
        $count  = count($tokens);

        while ($at < $count) {
            // A positional argument fills the next slot.
            $slot  = self::SLOTS[count($values)] ?? null;
            $token = $tokens[$at];

            // A named argument: a name, then `:`.
            if (true === is_array($token) && T_STRING === $token[0] && ':' === ($tokens[$at + 1] ?? null)) {
                $slot = $token[1];
                $at  += 2;
            }

            $text = self::text($tokens[$at] ?? '');

            if (null === $text || false === in_array($slot, self::SLOTS, true) || true === isset($values[$slot])) {
                throw new Exception(sprintf(self::NEEDS, $file, $line));
            }

            $values[$slot] = $text;
            $at++;

            if (',' === ($tokens[$at] ?? null)) {
                $at++;
            }
        }

        if (false === isset($values['key'], $values['alias'])) {
            throw new Exception(sprintf(self::NEEDS, $file, $line));
        }

        if (1 !== preg_match(ClassName::PATTERN, $values['alias'])) {
            throw new Exception(sprintf(
                "%s, line %d: the alias '%s' is not a valid name",
                $file,
                $line,
                $values['alias']
            ));
        }

        return [$values['key'], $values['alias'], $line];
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
     * The key, the alias and the line of each RelationAlias attribute in one
     * group, from the token after `#[`. TOKEN_PARSE has checked the syntax:
     * each attribute is a name with an optional list of arguments, and a `,`
     * or the `]` of the group comes after it.
     *
     * @param list<Token> $tokens
     *
     * @return list<array{string, string, int}>
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

            if (true === is_array($name) && 1 === preg_match(self::NAME, $name[1])) {
                $found[] = self::arguments($file, $arguments, $name[2]);
            }

            // A `,` comes between two attributes, and can come after the last.
            if (',' === $tokens[$at]) {
                $at++;
            }
        }

        return $found;
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
}
