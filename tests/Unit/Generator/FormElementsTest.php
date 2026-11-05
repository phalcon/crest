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

use Crest\Generator\FormElements;
use Phalcon\Db\Column;
use PHPUnit\Framework\TestCase;

/**
 * The table of spec 3.6, one row at a time. The columns have no database.
 */
final class FormElementsTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function binaryTypes(): iterable
    {
        yield 'BLOB' => [Column::TYPE_BLOB];
        yield 'TINYBLOB' => [Column::TYPE_TINYBLOB];
        yield 'MEDIUMBLOB' => [Column::TYPE_MEDIUMBLOB];
        yield 'LONGBLOB' => [Column::TYPE_LONGBLOB];
        yield 'BINARY' => [Column::TYPE_BINARY];
        yield 'VARBINARY' => [Column::TYPE_VARBINARY];
        yield 'BYTEA' => [Column::TYPE_BYTEA];
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function types(): iterable
    {
        $int      = self::line("new Numeric('value')", 'int');
        $float    = self::line("new Numeric('value', ['step' => 'any'])", 'float');
        $decimal  = self::line("new Numeric('value', ['step' => 'any'])", 'trim');
        $bool     = self::line("new Select('value', [0 => 'No', 1 => 'Yes'], ['useEmpty' => true])", 'bool');
        $date     = self::line("new Date('value')", 'trim');
        $textArea = self::line("new TextArea('value')", 'trim');
        $text     = self::line("new Text('value')", 'trim');

        yield 'BIGINTEGER' => [Column::TYPE_BIGINTEGER, $int];
        yield 'INTEGER' => [Column::TYPE_INTEGER, $int];
        yield 'MEDIUMINTEGER' => [Column::TYPE_MEDIUMINTEGER, $int];
        yield 'SMALLINTEGER' => [Column::TYPE_SMALLINTEGER, $int];
        yield 'TINYINTEGER' => [Column::TYPE_TINYINTEGER, $int];
        yield 'DOUBLE' => [Column::TYPE_DOUBLE, $float];
        yield 'FLOAT' => [Column::TYPE_FLOAT, $float];
        yield 'DECIMAL' => [Column::TYPE_DECIMAL, $decimal];
        yield 'BOOLEAN' => [Column::TYPE_BOOLEAN, $bool];
        yield 'DATE' => [Column::TYPE_DATE, $date];
        yield 'TEXT' => [Column::TYPE_TEXT, $textArea];
        yield 'TINYTEXT' => [Column::TYPE_TINYTEXT, $textArea];
        yield 'MEDIUMTEXT' => [Column::TYPE_MEDIUMTEXT, $textArea];
        yield 'LONGTEXT' => [Column::TYPE_LONGTEXT, $textArea];
        yield 'JSON' => [Column::TYPE_JSON, $textArea];
        yield 'JSONB' => [Column::TYPE_JSONB, $textArea];
        yield 'VARCHAR' => [Column::TYPE_VARCHAR, $text];
        yield 'CHAR' => [Column::TYPE_CHAR, $text];
        yield 'DATETIME' => [Column::TYPE_DATETIME, $text];
        yield 'TIMESTAMP' => [Column::TYPE_TIMESTAMP, $text];
        yield 'TIME' => [Column::TYPE_TIME, $text];
        yield 'ENUM' => [Column::TYPE_ENUM, $text];
    }

    private static function line(string $element, string $filter): string
    {
        return "        \$this->add((" . $element . ")->setLabel('Value')->setFilters(['" . $filter . "']));\n";
    }

    /**
     * @dataProvider binaryTypes
     */
    public function testABinaryColumnHasNoElement(int $type): void
    {
        $elements = FormElements::of([new Column('value', ['type' => $type])], ['value' => 'value']);

        $this->assertSame('', $elements->code);
        $this->assertSame('', $elements->imports);
        $this->assertSame(0, $elements->editCount);
        $this->assertSame(['value'], $elements->skipped);
    }

    public function testAColumnAfterABinaryColumnHasItsElement(): void
    {
        $elements = FormElements::of(
            [
                new Column('picture', ['type' => Column::TYPE_BLOB]),
                new Column('name', ['type' => Column::TYPE_VARCHAR]),
            ],
            ['picture' => 'picture', 'name' => 'name']
        );

        $this->assertSame(
            "        \$this->add((new Text('name'))->setLabel('Name')->setFilters(['trim']));\n",
            $elements->code
        );
        $this->assertSame(1, $elements->editCount);
        $this->assertSame(['picture'], $elements->skipped);
    }

    public function testAColumnNamedEmailIsAnEmailField(): void
    {
        $elements = FormElements::of(
            [new Column('EMAIL', ['type' => Column::TYPE_VARCHAR])],
            ['EMAIL' => 'EMAIL']
        );

        $this->assertSame(
            "        \$this->add((new Email('EMAIL'))->setLabel('EMAIL')->setFilters(['email']));\n",
            $elements->code
        );
        $this->assertSame("use Phalcon\\Forms\\Element\\Email;\n", $elements->imports);
    }

    public function testAnIdentityColumnHasNoElement(): void
    {
        $elements = FormElements::of(
            [new Column('id', ['type' => Column::TYPE_INTEGER, 'primary' => true, 'autoIncrement' => true])],
            ['id' => 'id']
        );

        $this->assertSame('', $elements->code);
        $this->assertSame('', $elements->imports);
        $this->assertSame(0, $elements->editCount);
        $this->assertSame([], $elements->skipped);
    }

    public function testAPrimaryKeyAfterAColumnKeepsTheLineOfTheColumn(): void
    {
        $elements = FormElements::of(
            [
                new Column('name', ['type' => Column::TYPE_VARCHAR]),
                new Column('code', ['type' => Column::TYPE_VARCHAR, 'primary' => true]),
            ],
            ['name' => 'name', 'code' => 'code']
        );

        $this->assertSame(
            "        \$this->add((new Text('name'))->setLabel('Name')->setFilters(['trim']));\n"
            . "        if (null === \$entity) {\n"
            . "            \$this->add((new Text('code'))->setLabel('Code')->setFilters(['trim']));\n"
            . "        }\n",
            $elements->code
        );
        $this->assertSame(1, $elements->editCount);
    }

    public function testAPrimaryKeyThatIsNotIdentityIsOnlyOnTheNewForm(): void
    {
        $elements = FormElements::of(
            [new Column('code', ['type' => Column::TYPE_VARCHAR, 'primary' => true])],
            ['code' => 'code']
        );

        $this->assertSame(
            "        if (null === \$entity) {\n"
            . "            \$this->add((new Text('code'))->setLabel('Code')->setFilters(['trim']));\n"
            . "        }\n",
            $elements->code
        );
        $this->assertSame(0, $elements->editCount);
    }

    public function testAPropertyWithAnotherNameKeepsTheLabelOfItsColumn(): void
    {
        $elements = FormElements::of(
            [new Column('first_name', ['type' => Column::TYPE_VARCHAR])],
            ['first_name' => 'firstName']
        );

        $this->assertSame(
            "        \$this->add((new Text('firstName'))->setLabel('First name')->setFilters(['trim']));\n",
            $elements->code
        );
    }

    /**
     * @dataProvider types
     */
    public function testATypeGivesItsElement(int $type, string $line): void
    {
        $elements = FormElements::of([new Column('value', ['type' => $type])], ['value' => 'value']);

        $this->assertSame($line, $elements->code);
        $this->assertSame(1, $elements->editCount);
        $this->assertSame([], $elements->skipped);
    }

    public function testTheElementsKeepTheOrderOfTheTable(): void
    {
        $elements = FormElements::of(
            [
                new Column('id', ['type' => Column::TYPE_INTEGER, 'primary' => true, 'autoIncrement' => true]),
                new Column('name', ['type' => Column::TYPE_VARCHAR]),
                new Column('age', ['type' => Column::TYPE_INTEGER]),
            ],
            ['id' => 'id', 'name' => 'name', 'age' => 'age']
        );

        $this->assertSame(
            "        \$this->add((new Text('name'))->setLabel('Name')->setFilters(['trim']));\n"
            . "        \$this->add((new Numeric('age'))->setLabel('Age')->setFilters(['int']));\n",
            $elements->code
        );
        $this->assertSame(2, $elements->editCount);
    }

    public function testTheImportsAreSortedAndOnlyTheOnesThatTheTableNeeds(): void
    {
        $elements = FormElements::of(
            [
                new Column('bio', ['type' => Column::TYPE_TEXT]),
                new Column('age', ['type' => Column::TYPE_INTEGER]),
                new Column('name', ['type' => Column::TYPE_VARCHAR]),
                new Column('nick', ['type' => Column::TYPE_VARCHAR]),
            ],
            ['bio' => 'bio', 'age' => 'age', 'name' => 'name', 'nick' => 'nick']
        );

        $this->assertSame(
            "use Phalcon\\Forms\\Element\\Numeric;\n"
            . "use Phalcon\\Forms\\Element\\Text;\n"
            . "use Phalcon\\Forms\\Element\\TextArea;\n",
            $elements->imports
        );
    }
}
