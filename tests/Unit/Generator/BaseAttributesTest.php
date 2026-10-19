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
use Crest\Generator\BaseAttributes;
use Crest\Generator\ModelRelations;
use Crest\Generator\RelationAliases;
use Crest\Tests\Support\ScratchDirectory;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_put_contents;
use function is_readable;
use function preg_quote;

/**
 * What make:model reads from a model base before it writes it again (#51,
 * #53). Each test writes its base into a scratch folder. The attributes of a
 * base start on line 6 (text()).
 *
 * @phpstan-import-type Relation from ModelRelations
 */
final class BaseAttributesTest extends TestCase
{
    use ScratchDirectory;

    private const KEY = 'belongsTo invoices(customer_id) -> customers(id)';

    private const PAYMENTS = 'hasMany payments(invoice_id) -> invoices(id)';

    protected function setUp(): void
    {
        $this->makeScratchDirectory('base-attributes');
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
     * The forms of the body of columnMap() that crest cannot read.
     *
     * @return iterable<string, array{string}>
     */
    public static function columnMapsThatCrestCannotRead(): iterable
    {
        $method = static fn (string $body): string => "    public function columnMap(): array\n    {\n"
            . $body . "    }\n";

        yield 'array()' => [$method("        return array('id' => 'code');\n")];
        yield 'a statement before the return' => [
            $method("        \$map = ['id' => 'code'];\n\n        return \$map;\n"),
        ];
        yield 'a statement after the return' => [$method("        return ['id' => 'code'];\n        echo 1;\n")];
        yield 'a value that is not a plain string' => [$method("        return ['id' => self::CODE];\n")];
        yield 'a key that is not a plain string' => [$method("        return [self::ID => 'code'];\n")];
        yield 'a list with no keys' => [$method("        return ['id', 'code'];\n")];
        yield 'no body' => ["    abstract public function columnMap(): array;\n"];
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

    /**
     * The forms of a PropertyName attribute. Each one gives 'first name' =>
     * 'firstName'.
     *
     * @return iterable<string, array{string}>
     */
    public static function formsOfThePropertyName(): iterable
    {
        yield 'both positional' => ["#[\\Crest\\Attribute\\PropertyName('first name', 'firstName')]\n"];
        yield 'both named, in the other order' => [
            "#[\\Crest\\Attribute\\PropertyName(name: 'firstName', column: 'first name')]\n",
        ];
        yield 'the short name after a use import' => ["#[PropertyName('first name', name: 'firstName')]\n"];
        yield 'the name in another case' => [
            "#[\\crest\\attribute\\propertyname('first name', name: 'firstName')]\n",
        ];
    }

    public function testAPropertyNameIsNotCheckedHere(): void
    {
        // ModelNames checks a property name, with the file and the line.
        $file = $this->file($this->text("#[\\Crest\\Attribute\\PropertyName('id', name: '2nd')]\n"));

        $this->assertSame(['id' => ['name' => '2nd', 'line' => 6]], BaseAttributes::read($file)->properties);
    }

    public function testReadGivesBackAKeyWithAQuoteABackslashAndALineBreak(): void
    {
        $key  = "hasMany notes(it's_id, a\\b, c\nd) -> users(id)";
        $file = $this->file($this->text(RelationAliases::attributes([$this->relation($key, 'Notes')])));

        $this->assertSame([$key => ['alias' => 'Notes', 'line' => 6]], BaseAttributes::read($file)->aliases);
    }

    public function testReadGivesBothKindsOfAttributesInOnePass(): void
    {
        $read = BaseAttributes::read(
            $this->file(
                $this->text(
                    "#[\\Crest\\Attribute\\PropertyName('first name', name: 'firstName'), "
                    . "\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Client')]\n"
                    . "#[\\Crest\\Attribute\\PropertyName('USER_ID', name: 'userId')]\n"
                )
            )
        );

        $this->assertSame([self::KEY => ['alias' => 'Client', 'line' => 6]], $read->aliases);
        $this->assertSame(
            ['first name' => ['name' => 'firstName', 'line' => 6], 'USER_ID' => ['name' => 'userId', 'line' => 7]],
            $read->properties
        );
    }

    public function testReadGivesNothingForAFileWithoutTheMarker(): void
    {
        // The file belongs to the developer (#46), so crest does not read it,
        // also when it has an attribute.
        $file = $this->file(
            "<?php\n\n#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: 'Client')]\n"
            . "abstract class Base\n{\n}\n"
        );

        $this->assertSame([], BaseAttributes::read($file)->aliases);
    }

    public function testReadGivesNothingForNoFile(): void
    {
        $this->assertSame([], BaseAttributes::read($this->root . '/Missing.php')->aliases);
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
            BaseAttributes::read($this->file($this->text($attributes)))->aliases
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

        BaseAttributes::read($file);
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

        $this->assertSame([], BaseAttributes::read($file)->aliases);
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

        BaseAttributes::read($file);
    }

    public function testReadStopsOnABaseWithACompileError(): void
    {
        // The parser finds two access modifiers, and PHP throws a
        // CompileError, the parent of ParseError. make:model --all fails only
        // this table, so the message names the file (#52).
        $file = $this->file(
            "<?php\n\n/**\n * " . ArtifactWriter::MARKER . " Do not edit this file.\n */\n"
            . "abstract class Base\n{\n    public public \$name;\n}\n"
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/^' . preg_quote($file . ' is not valid PHP: ', '/') . '.+ on line 8$/');

        BaseAttributes::read($file);
    }

    public function testReadStopsOnAnAliasThatIsNotAName(): void
    {
        $file = $this->file($this->text("#[\\Crest\\Attribute\\RelationAlias('" . self::KEY . "', alias: '2nd')]\n"));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($file . ", line 6: the alias '2nd' is not a valid name");

        BaseAttributes::read($file);
    }

    public function testReadStopsOnAPropertyNameWithoutTwoPlainStrings(): void
    {
        $file = $this->file($this->text("#[\\Crest\\Attribute\\PropertyName('first name')]\n"));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            $file . ', line 6: a PropertyName attribute needs two plain strings (single quotes, '
            . 'or double quotes with no backslash), the column and the name'
        );

        BaseAttributes::read($file);
    }

    public function testReadStopsOnAPropertyNameWithTheArgumentOfARelationAlias(): void
    {
        // key: is an argument of RelationAlias, not of PropertyName.
        $file = $this->file($this->text("#[\\Crest\\Attribute\\PropertyName(key: 'first name', name: 'x')]\n"));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            $file . ', line 6: a PropertyName attribute needs two plain strings (single quotes, '
            . 'or double quotes with no backslash), the column and the name'
        );

        BaseAttributes::read($file);
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

        BaseAttributes::read($file);
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

        BaseAttributes::read($file);
    }

    public function testReadStopsOnTwoPropertyNamesForOneColumn(): void
    {
        $file = $this->file(
            $this->text(
                "#[\\Crest\\Attribute\\PropertyName('first name', name: 'firstName')]\n"
                . "#[\\Crest\\Attribute\\PropertyName('first name', name: 'givenName')]\n"
            )
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage($file . ", line 7: two PropertyName attributes for 'first name'");

        BaseAttributes::read($file);
    }

    /**
     * @dataProvider formsOfTheAttribute
     */
    public function testReadTakesEachFormOfTheAttribute(string $attribute): void
    {
        // In each form, the name of the attribute is on line 6.
        $this->assertSame(
            [self::KEY => ['alias' => 'Client', 'line' => 6]],
            BaseAttributes::read($this->file($this->text($attribute)))->aliases
        );
    }

    /**
     * @dataProvider formsOfThePropertyName
     */
    public function testReadTakesEachFormOfThePropertyName(string $attribute): void
    {
        $this->assertSame(
            ['first name' => ['name' => 'firstName', 'line' => 6]],
            BaseAttributes::read($this->file($this->text($attribute)))->properties
        );
    }

    public function testRenamedComparesWithTheColumnMap(): void
    {
        // A column that the map does not have is new, not renamed. The name
        // of the method has another case: PHP ignores the case of a method.
        $file = $this->file(
            $this->base(
                "    public ?int \$code = null;\n    public ?string \$name = null;\n\n"
                . "    public function ColumnMap(): array\n    {\n        return [\n"
                . "            'id'   => 'code',\n            'name' => 'name',\n        ];\n    }\n"
            )
        );

        $this->assertSame(
            ['id' => ['code', 'id'], 'name' => ['name', 'fullName']],
            BaseAttributes::read($file)->renamed(['id' => 'id', 'name' => 'fullName', 'email' => 'eMail'])
        );
    }

    public function testRenamedCountsOnlyTheDeclaredProperties(): void
    {
        // A parameter, a variable in a method, a variable in a string, and a
        // variable after the class are not properties. $late comes after a
        // method, so the read must find the end of each method.
        $file = $this->file(
            $this->base(
                "    public ?int \$id = null;\n\n"
                . "    public function name(string \$a): string\n    {\n        \$b = \$a;\n\n"
                . "        return \"{\$b}\";\n    }\n\n"
                . "    public ?string \$late = null;\n"
            ) . "if (true) {\n    \$c = 1;\n}\n"
        );

        $next = ['a' => 'x', 'b' => 'y', 'c' => 'z', 'id' => 'code', 'late' => 'later'];

        $this->assertSame(
            ['id' => ['id', 'code'], 'late' => ['late', 'later']],
            BaseAttributes::read($file)->renamed($next)
        );
    }

    public function testRenamedFindsNothingWithNoBase(): void
    {
        $this->assertSame([], BaseAttributes::read($this->root . '/Missing.php')->renamed(['id' => 'code']));
    }

    /**
     * @dataProvider columnMapsThatCrestCannotRead
     */
    public function testRenamedIsNullForAColumnMapThatCrestCannotRead(string $method): void
    {
        $file = $this->file($this->base("    public ?int \$code = null;\n\n" . $method));

        $this->assertNull(BaseAttributes::read($file)->renamed(['id' => 'id']));
    }

    public function testRenamedReadsAColumnMapWithNoLastComma(): void
    {
        $file = $this->file(
            $this->base(
                "    public ?int \$code = null;\n\n"
                . "    public function columnMap(): array\n    {\n        return ['id' => 'code'];\n    }\n"
            )
        );

        $this->assertSame(['id' => ['code', 'id']], BaseAttributes::read($file)->renamed(['id' => 'id']));
    }

    public function testRenamedSkipsAClassConstantBeforeTheClass(): void
    {
        // A published model-base can have Foo::class before the class. With
        // TOKEN_PARSE, PHP gives T_STRING for that class, not T_CLASS, so the
        // read starts at the class itself.
        $file = $this->file(
            "<?php\n\n/**\n * " . ArtifactWriter::MARKER . " Do not edit this file.\n */\n"
            . "#[Foo(Bar::class)]\nabstract class Base\n{\n"
            . "    public ?int \$id = null;\n    public ?string \$first_name = null;\n}\n"
        );

        $this->assertSame(
            ['first_name' => ['first_name', 'firstName']],
            BaseAttributes::read($file)->renamed(['id' => 'id', 'first_name' => 'firstName'])
        );
    }

    public function testRenamedWithNoColumnMapComparesWithTheProperties(): void
    {
        // With no columnMap(), each property has the name of its column (#50
        // to #52). A column that the base does not declare is new.
        $file = $this->file($this->base("    public ?int \$id = null;\n    public ?string \$first_name = null;\n"));

        $this->assertSame(
            ['first_name' => ['first_name', 'firstName']],
            BaseAttributes::read($file)->renamed(['id' => 'id', 'first_name' => 'firstName', 'nick_name' => 'nickName'])
        );
    }

    /**
     * A base with the marker, no attributes, and the body of the class.
     */
    private function base(string $body): string
    {
        return "<?php\n\n/**\n * " . ArtifactWriter::MARKER . " Do not edit this file.\n */\n"
            . "abstract class Base\n{\n" . $body . "}\n";
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
