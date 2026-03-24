<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Parser;

use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Table;
use PhpCollective\Toml\Ast\TriviaKind;
use PhpCollective\Toml\Ast\Value\ArrayValue;
use PhpCollective\Toml\Ast\Value\InlineTable;
use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;

final class TriviaPreservationTest extends TestCase
{
    public function testParseCanAttachLeadingTriviaToTopLevelItem(): void
    {
        $doc = Toml::parse(<<<'TOML'
# Header comment

title = "Example"
TOML, true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertGreaterThanOrEqual(2, count($item->getLeadingTrivia()));
        $this->assertSame(TriviaKind::Comment, $item->getLeadingTrivia()[0]->kind);
        $this->assertSame('# Header comment', $item->getLeadingTrivia()[0]->value);
        $this->assertSame(TriviaKind::Newline, $item->getLeadingTrivia()[1]->kind);
    }

    public function testParseCanAttachTrailingTriviaToKeyValue(): void
    {
        $doc = Toml::parse("title = \"Example\" # inline comment\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertCount(3, $item->getTrailingTrivia());
        $this->assertSame(TriviaKind::Whitespace, $item->getTrailingTrivia()[0]->kind);
        $this->assertSame(TriviaKind::Comment, $item->getTrailingTrivia()[1]->kind);
        $this->assertSame('# inline comment', $item->getTrailingTrivia()[1]->value);
        $this->assertSame(TriviaKind::Newline, $item->getTrailingTrivia()[2]->kind);
    }

    public function testParseCanAttachTrailingTriviaToTableHeaderAndLeadingTriviaToChildItem(): void
    {
        $doc = Toml::parse(<<<'TOML'
[server] # section comment
# child comment
host = "localhost"
TOML, true);

        $table = $doc->items[0];
        $this->assertInstanceOf(Table::class, $table);
        $this->assertCount(3, $table->getTrailingTrivia());
        $this->assertSame(TriviaKind::Comment, $table->getTrailingTrivia()[1]->kind);

        $child = $table->items[0];
        $this->assertGreaterThanOrEqual(1, count($child->getLeadingTrivia()));
        $this->assertSame(TriviaKind::Comment, $child->getLeadingTrivia()[0]->kind);
        $this->assertSame('# child comment', $child->getLeadingTrivia()[0]->value);
    }

    public function testParseWithoutTriviaPreservationLeavesTriviaEmpty(): void
    {
        $doc = Toml::parse("# comment\nkey = 1\n");

        $item = $doc->items[0];
        $this->assertSame([], $item->getLeadingTrivia());
        $this->assertSame([], $item->getTrailingTrivia());
    }

    public function testParseCanAttachTriviaInsideArrays(): void
    {
        $doc = Toml::parse("values = [\n 1,\n # between\n 2,\n]\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(ArrayValue::class, $item->value);
        $this->assertNotSame([], $item->value->openingTrivia);
        $this->assertSame(TriviaKind::Newline, $item->value->openingTrivia[0]->kind);
        $this->assertSame(TriviaKind::Comment, $item->value->items[1]->getLeadingTrivia()[2]->kind);
        $this->assertTrue($item->value->hasTrailingComma);
        $this->assertNotSame([], $item->value->closingTrivia);
    }

    public function testParseCanAttachTriviaInsideInlineTables(): void
    {
        $doc = Toml::parse("point = { x = 1,  y = 2 }\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(InlineTable::class, $item->value);
        $this->assertCount(2, $item->value->items);
        $this->assertSame(' ', $item->value->items[0]->getLeadingTrivia()[0]->value);
        $this->assertSame('  ', $item->value->items[1]->getLeadingTrivia()[0]->value);
        $this->assertSame(' ', $item->value->items[1]->getTrailingTrivia()[0]->value);
    }
}
