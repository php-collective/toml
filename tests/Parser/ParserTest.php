<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Parser;

use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Table;
use PhpCollective\Toml\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    public function testParseTableHeader(): void
    {
        $parser = new Parser();
        $doc = $parser->parse('[server]');

        $this->assertCount(1, $doc->items);
        $this->assertInstanceOf(Table::class, $doc->items[0]);
        $this->assertSame(['server'], $doc->items[0]->key->parts);
        $this->assertFalse($doc->items[0]->isArrayTable);
    }

    public function testParseArrayTableHeader(): void
    {
        $parser = new Parser();
        $doc = $parser->parse('[[products]]');

        $this->assertCount(1, $doc->items);
        $this->assertInstanceOf(Table::class, $doc->items[0]);
        $this->assertTrue($doc->items[0]->isArrayTable);
    }

    public function testParseDottedTableHeader(): void
    {
        $parser = new Parser();
        $doc = $parser->parse('[server.database]');

        $this->assertCount(1, $doc->items);
        $this->assertInstanceOf(Table::class, $doc->items[0]);
        $this->assertSame(['server', 'database'], $doc->items[0]->key->parts);
    }

    public function testParseStringValue(): void
    {
        $parser = new Parser();
        $doc = $parser->parse('key = "value"');

        $this->assertCount(1, $doc->items);
        $kv = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $kv);
        $this->assertSame('value', $kv->value->getValue());
    }

    public function testParseIntegerValue(): void
    {
        $parser = new Parser();
        $doc = $parser->parse('count = 42');

        $kv = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $kv);
        $this->assertSame(42, $kv->value->getValue());
    }

    public function testParseArray(): void
    {
        $parser = new Parser();
        $doc = $parser->parse('arr = [1, 2, 3]');

        $kv = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $kv);
        $this->assertSame([1, 2, 3], $kv->value->getValue());
    }

    public function testParseInlineTable(): void
    {
        $parser = new Parser();
        $doc = $parser->parse('point = { x = 1, y = 2 }');

        $kv = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $kv);
        $this->assertSame(['x' => 1, 'y' => 2], $kv->value->getValue());
    }

    public function testInlineTableTrailingCommaAllowed(): void
    {
        $parser = new Parser();
        $doc = $parser->parse('t = { a = 1, }');

        $this->assertEmpty($parser->getErrors());
        $kv = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $kv);
        $this->assertSame(['a' => 1], $kv->value->getValue());
    }

    public function testArrayTrailingCommaAllowed(): void
    {
        $parser = new Parser();
        $doc = $parser->parse('arr = [1, 2, 3,]');

        $this->assertEmpty($parser->getErrors());
        $kv = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $kv);
        $this->assertSame([1, 2, 3], $kv->value->getValue());
    }

    public function testParseSkipsLeadingUtf8Bom(): void
    {
        $parser = new Parser();
        $doc = $parser->parse("\u{FEFF}a = 1");

        $this->assertEmpty($parser->getErrors());
        $kv = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $kv);
        $this->assertSame(['a'], $kv->key->parts);
        $this->assertSame(1, $kv->value->getValue());
    }

    public function testParseSkipsLeadingBomBeforeComment(): void
    {
        $parser = new Parser();
        $doc = $parser->parse("\u{FEFF}# comment\nb = 2");

        $this->assertEmpty($parser->getErrors());
        $kv = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $kv);
        $this->assertSame(['b'], $kv->key->parts);
    }

    public function testBomOnlyStrippedAtStart(): void
    {
        // A BOM that is not the very first byte is a normal (invalid) character.
        $parser = new Parser();
        $parser->parse("a = 1\n\u{FEFF}b = 2");

        $this->assertNotEmpty($parser->getErrors());
    }
}
