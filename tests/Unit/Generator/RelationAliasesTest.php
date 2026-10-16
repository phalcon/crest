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

namespace Crest\Tests\Unit\Generator;

use Crest\Console\Exceptions\Exception;
use Crest\Generator\ArtifactWriter;
use Crest\Generator\ModelRelations;
use Crest\Generator\RelationAliases;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_put_contents;
use function is_readable;
use function preg_quote;

/**
 * The RelationAlias attributes of a model base (#51, spec 3.4). Each test
 * writes its base into a scratch folder. The attributes of a base start on
 * line 6 (text()).
 *
 * @phpstan-import-type Relation from ModelRelations
 */
final class RelationAliasesTest extends TestCase
{
    use ScratchDirectory;

    private const KEY = 'belongsTo invoices(customer_id) -> customers(id)';

    private const PAYMENTS = 'hasMany payments(invoice_id) -> invoices(id)';

    protected function setUp(): void
    {
        $this->makeScratchDirectory('relation-aliases');
    }

    protected function tearDown(): void
    {
        $this->removeScratchDirectory();
    }

    /**
     * Attributes that are not two plain strings, the key and the alias.
     *
     * @return iterable<string, array{string}>
     */
    public static function argumentsThatAreNotTwoPlainStrings(): iterable
    {
        yield 'no argument list' => ["#[\\Crest\\Attribute\\RelationAlias]\n"];
        yield 'no arguments' => ["#[\\Crest\\Attribute\\RelationAlias()]\n"];
        yield 'one argument' => ["#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "')]\n"];
        yield 'three arguments' => [
            "#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', 'Client', 'Other')]\n",
        ];
        yield 'a constant' => ["#[\\Crest\\Attribute\\RelationAlias(self::KEY, alias: 'Client')]\n"];
        yield 'a concatenation' => [
            "#[\\Crest\\Attribute\\RelationAlias('belongsTo ' . 'x', alias: 'Client')]\n",
        ];
        yield 'double quotes with a backslash' => [
            '#[\\Crest\\Attribute\\RelationAlias("a\\tb", alias: \'Client\')]' . "\n",
        ];
        yield 'a binary string' => [
            "#[\\Crest\\Attribute\\RelationAlias(b'" . self::KEY . "', alias: 'Client')]\n",
        ];
        yield 'an unknown name' => [
            "#[\\Crest\\Attribute\\RelationAlias(name: '" . self::KEY . "', alias: 'Client')]\n",
        ];
        yield 'a name two times' => [
            "#[\\Crest\\Attribute\\RelationAlias(key: '" . self::KEY . "', key: 'Client')]\n",
        ];
    }

    /**
     * The forms that a formatter, an IDE or a developer can give to the
     * attribute. Each one gives self::KEY => 'Client'.
     *
     * @return iterable<string, array{string}>
     */
    public static function formsOfTheAttribute(): iterable
    {
        yield 'both positional' => ["#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', 'Client')]\n"];
        yield 'both named' => [
            "#[\\Crest\\Attribute\\RelationAlias(key: '" . self::KEY . "', alias: 'Client')]\n",
        ];
        yield 'named, in the other order' => [
            "#[\\Crest\\Attribute\\RelationAlias(alias: 'Client', key: '" . self::KEY . "')]\n",
        ];
        yield 'the short name after a use import' => ["#[RelationAlias('" . self::KEY . "', alias: 'Client')]\n"];
        yield 'the name in another case' => [
            "#[\\crest\\attribute\\relationalias('" . self::KEY . "', alias: 'Client')]\n",
        ];
        yield 'lines and comments' => [
            "#[\\Crest\\Attribute\\RelationAlias(\n"
            . "    // the key\n"
            . "    '" . self::KEY . "',\n"
            . "    /* the alias */ alias: /** a doc comment */ 'Client'\n"
            . ")]\n",
        ];
        yield 'a last comma' => ["#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Client',)]\n"];
        yield 'double quotes' => ['#[\\Crest\\Attribute\\RelationAlias("' . self::KEY . '", alias: "Client")]' . "\n"];
        yield 'Windows line breaks' => [
            "#[\\Crest\\Attribute\\RelationAlias(\r\n    '" . self::KEY . "',\r\n    alias: 'Client'\r\n)]\r\n",
        ];
        yield 'nested parentheses in another attribute' => [
            "#[Foo((1 + 2) * 3, [4]), \\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Client')]\n",
        ];
        yield 'another attribute after it' => [
            "#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Client'), Bar]\n",
        ];
    }

    public function testAttributesGivesALineForEachRelation(): void
    {
        $this->assertSame(
            "#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Customer')]\n"
            . "#[\\Crest\\Attribute\\RelationAlias('" . self::PAYMENTS . "', alias: 'Payments')]\n",
            RelationAliases::attributes(
                [$this->relation(self::KEY, 'Customer'), $this->relation(self::PAYMENTS, 'Payments')]
            )
        );
    }

    public function testAttributesGivesNothingForNoRelations(): void
    {
        // An empty value keeps the class line of #50.
        $this->assertSame('', RelationAliases::attributes([]));
    }

    public function testAttributesQuotesTheKey(): void
    {
        // var_export() escapes the quote and the backslash.
        $this->assertSame(
            "#[\\Crest\\Attribute\\RelationAlias('hasMany notes(it\\'s_id, a\\\\b) -> users(id)', alias: 'Notes')]\n",
            RelationAliases::attributes([$this->relation("hasMany notes(it's_id, a\\b) -> users(id)", 'Notes')])
        );
    }

    public function testNoteGivesNothingForNoRelations(): void
    {
        // An empty value keeps the docblock of #50.
        $this->assertSame('', RelationAliases::note([]));
    }

    public function testNoteGivesTheParagraph(): void
    {
        $this->assertSame(
            " *\n"
            . " * You can change the alias in a RelationAlias attribute: crest keeps it when\n"
            . " * it writes this file again.\n",
            RelationAliases::note([$this->relation(self::KEY, 'Customer')])
        );
    }

    public function testReadGivesBackAKeyWithAQuoteABackslashAndALineBreak(): void
    {
        $key  = "hasMany notes(it's_id, a\\b, c\nd) -> users(id)";
        $file = $this->file($this->text(RelationAliases::attributes([$this->relation($key, 'Notes')])));

        $this->assertSame([$key => ['alias' => 'Notes', 'line' => 6]], RelationAliases::read($file));
    }

    public function testReadGivesNothingForAFileWithoutTheMarker(): void
    {
        // The file belongs to the developer (#46), so crest does not read it,
        // also when it has an attribute.
        $file = $this->file(
            "<?php\n\n#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Client')]\n"
            . "abstract class Base\n{\n}\n"
        );

        $this->assertSame([], RelationAliases::read($file));
    }

    public function testReadGivesNothingForNoFile(): void
    {
        $this->assertSame([], RelationAliases::read($this->root . '/Missing.php'));
    }

    public function testReadGivesTheAliasAndTheLineOfEachAttribute(): void
    {
        $attributes = RelationAliases::attributes(
            [$this->relation(self::KEY, 'Customer'), $this->relation(self::PAYMENTS, 'Payments')]
        );

        $this->assertSame(
            [
                self::KEY      => ['alias' => 'Customer', 'line' => 6],
                self::PAYMENTS => ['alias' => 'Payments', 'line' => 7],
            ],
            RelationAliases::read($this->file($this->text($attributes)))
        );
    }

    public function testReadReportsAFileItCouldNotRead(): void
    {
        $file = $this->file($this->text(''));
        chmod($file, 0o000);

        // root can read a file with mode 000, so the test proves nothing then.
        if (true === is_readable($file)) {
            $this->markTestSkipped('this user can read a file with mode 000');
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('could not read ' . $file);

        RelationAliases::read($file);
    }

    public function testReadSkipsOtherAttributes(): void
    {
        // Only the name RelationAlias, after the last \, counts.
        $file = $this->file(
            $this->text(
                "#[Foo('" . self::KEY . "', alias: 'Client')]\n"
                . "#[\\App\\MyRelationAlias('" . self::KEY . "', alias: 'Client')]\n"
            )
        );

        $this->assertSame([], RelationAliases::read($file));
    }

    public function testReadStopsOnABaseThatIsNotValidPhp(): void
    {
        // The ) is missing. The text of a ParseError depends on the version
        // of PHP, so the test checks the start and the line.
        $file = $this->file(
            $this->text("#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Client']\n")
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/^' . preg_quote($file . ' is not valid PHP: ', '/') . '.+ on line 6$/');

        RelationAliases::read($file);
    }

    public function testReadStopsOnAnAliasThatIsNotAName(): void
    {
        $file = $this->file($this->text("#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: '2nd')]\n"));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($file . ", line 6: the alias '2nd' is not a valid name");

        RelationAliases::read($file);
    }

    /**
     * @dataProvider argumentsThatAreNotTwoPlainStrings
     */
    public function testReadStopsOnArgumentsThatAreNotTwoPlainStrings(string $attribute): void
    {
        $file = $this->file($this->text($attribute));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            $file . ', line 6: a RelationAlias attribute needs two plain strings (single quotes, '
            . 'or double quotes with no backslash), the key and the alias'
        );

        RelationAliases::read($file);
    }

    public function testReadStopsOnTwoAttributesForOneKey(): void
    {
        $file = $this->file(
            $this->text(
                "#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Customer')]\n"
                . "#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Client')]\n"
            )
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($file . ", line 7: two RelationAlias attributes for '" . self::KEY . "'");

        RelationAliases::read($file);
    }

    /**
     * @dataProvider formsOfTheAttribute
     */
    public function testReadTakesEachFormOfTheAttribute(string $attribute): void
    {
        // In each form, the name of the attribute is on line 6.
        $this->assertSame(
            [self::KEY => ['alias' => 'Client', 'line' => 6]],
            RelationAliases::read($this->file($this->text($attribute)))
        );
    }

    /**
     * Writes Base.php into the scratch folder, and gives its path.
     */
    private function file(string $contents): string
    {
        $file = $this->root . '/Base.php';

        file_put_contents($file, $contents);

        return $file;
    }

    /**
     * A relation in the form of ModelRelations. attributes() and note() use
     * only the key and the alias.
     *
     * @return Relation
     */
    private function relation(string $key, string $alias): array
    {
        return [
            'kind'       => 'belongs-to',
            'fields'     => ['customer_id'],
            'class'      => '\\App\\Models\\Customers',
            'referenced' => ['id'],
            'alias'      => $alias,
            'key'        => $key,
        ];
    }

    /**
     * A base with the marker, and the attributes on line 6.
     */
    private function text(string $attributes): string
    {
        return "<?php\n\n/**\n * " . ArtifactWriter::MARKER . " Do not edit this file.\n */\n"
            . $attributes . "abstract class Base\n{\n}\n";
    }
}
