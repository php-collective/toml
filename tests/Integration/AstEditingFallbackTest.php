<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Integration;

use PhpCollective\Toml\Ast\Key;
use PhpCollective\Toml\Ast\KeyStyle;
use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Value\ArrayValue;
use PhpCollective\Toml\Ast\Value\InlineTable;
use PhpCollective\Toml\Ast\Value\IntegerBase;
use PhpCollective\Toml\Ast\Value\IntegerValue;
use PhpCollective\Toml\Lexer\Span;
use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;

final class AstEditingFallbackTest extends TestCase
{
    public function testEncodeDocumentKeepsMultilineArrayLayoutForInsertedItem(): void
    {
        $doc = Toml::parse(<<<'TOML'
values = [
  1,
  2,
]
TOML, true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(ArrayValue::class, $item->value);

        $item->value->items[] = new IntegerValue(3, IntegerBase::Decimal, $this->span());

        $encoded = Toml::encodeDocument($doc);

        $this->assertSame(<<<'TOML'
values = [
  1,
  2,
  3,
]
TOML, $encoded);
    }

    public function testEncodeDocumentUsesCanonicalSpacingForInsertedInlineTableItem(): void
    {
        $doc = Toml::parse("point = { x = 1 }\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(InlineTable::class, $item->value);

        $item->value->items[] = new KeyValue(
            new Key(['y'], [KeyStyle::Bare], $this->span()),
            new IntegerValue(2, IntegerBase::Decimal, $this->span()),
            $this->span(),
        );

        $encoded = Toml::encodeDocument($doc);

        $this->assertSame("point = { x = 1, y = 2 }\n", $encoded);
    }

    public function testEncodeDocumentCanonicalizesSingleLineArrayDelimitersForInsertedItem(): void
    {
        $doc = Toml::parse("values = [ 1 ,2 ]\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(ArrayValue::class, $item->value);

        $item->value->items[] = new IntegerValue(3, IntegerBase::Decimal, $this->span());

        $encoded = Toml::encodeDocument($doc);

        $this->assertSame("values = [1, 2, 3]\n", $encoded);
    }

    public function testEncodeDocumentCanonicalizesInlineTableDelimitersForInsertedItem(): void
    {
        $doc = Toml::parse("point = { x = 1,  y = 2 }\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(InlineTable::class, $item->value);

        $item->value->items[] = new KeyValue(
            new Key(['z'], [KeyStyle::Bare], $this->span()),
            new IntegerValue(3, IntegerBase::Decimal, $this->span()),
            $this->span(),
        );

        $encoded = Toml::encodeDocument($doc);

        $this->assertSame("point = { x = 1, y = 2, z = 3 }\n", $encoded);
    }

    private function span(): Span
    {
        return new Span(0, 0, 1, 1);
    }
}
