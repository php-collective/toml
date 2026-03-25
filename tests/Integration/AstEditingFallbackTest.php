<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Integration;

use PhpCollective\Toml\Ast\Key;
use PhpCollective\Toml\Ast\KeyStyle;
use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Table;
use PhpCollective\Toml\Ast\Value\ArrayValue;
use PhpCollective\Toml\Ast\Value\InlineTable;
use PhpCollective\Toml\Ast\Value\IntegerBase;
use PhpCollective\Toml\Ast\Value\IntegerValue;
use PhpCollective\Toml\Ast\Value\StringStyle;
use PhpCollective\Toml\Ast\Value\StringValue;
use PhpCollective\Toml\Encoder\DocumentFormattingMode;
use PhpCollective\Toml\Encoder\EncoderOptions;
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

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

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

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame("point = { x = 1, y = 2 }\n", $encoded);
    }

    public function testEncodeDocumentCanonicalizesSingleLineArrayDelimitersForInsertedItem(): void
    {
        $doc = Toml::parse("values = [ 1 ,2 ]\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(ArrayValue::class, $item->value);

        $item->value->items[] = new IntegerValue(3, IntegerBase::Decimal, $this->span());

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

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

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame("point = { x = 1, y = 2, z = 3 }\n", $encoded);
    }

    public function testEncodeDocumentCanonicalizesSingleLineArrayAfterRemoval(): void
    {
        $doc = Toml::parse("values = [ 1 ,2 , 3 ]\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(ArrayValue::class, $item->value);

        array_splice($item->value->items, 1, 1);

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame("values = [1, 3]\n", $encoded);
    }

    public function testEncodeDocumentCanonicalizesInlineTableAfterRemoval(): void
    {
        $doc = Toml::parse("point = { x = 1,  y = 2, z = 3 }\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(InlineTable::class, $item->value);

        array_splice($item->value->items, 1, 1);

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame("point = { x = 1, z = 3 }\n", $encoded);
    }

    public function testEncodeDocumentCanonicalizesNestedSingleLineCollectionAfterReplacement(): void
    {
        $doc = Toml::parse("point = { dims = [ 1 ,2 ] }\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(InlineTable::class, $item->value);
        $this->assertInstanceOf(ArrayValue::class, $item->value->items[0]->value);

        $item->value->items[0]->value->items[1] = new IntegerValue(9, IntegerBase::Decimal, $this->span());

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame("point = { dims = [1, 9] }\n", $encoded);
    }

    public function testEncodeDocumentKeepsOuterMultilineLayoutWhileCanonicalizingNestedArrayRemoval(): void
    {
        $doc = Toml::parse(<<<'TOML'
items = [
  { dims = [ 1 ,2 , 3 ] },
]
TOML, true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(ArrayValue::class, $item->value);
        $this->assertInstanceOf(InlineTable::class, $item->value->items[0]);
        $this->assertInstanceOf(ArrayValue::class, $item->value->items[0]->items[0]->value);

        array_splice($item->value->items[0]->items[0]->value->items, 1, 1);

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame(<<<'TOML'
items = [
  { dims = [1, 3] },
]
TOML, $encoded);
    }

    public function testEncodeDocumentKeepsOuterMultilineLayoutWhileCanonicalizingNestedInlineTableReplacement(): void
    {
        $doc = Toml::parse(<<<'TOML'
items = [
  { point = { x = 1,  y = 2 } },
]
TOML, true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(ArrayValue::class, $item->value);
        $this->assertInstanceOf(InlineTable::class, $item->value->items[0]);
        $this->assertInstanceOf(InlineTable::class, $item->value->items[0]->items[0]->value);

        $item->value->items[0]->items[0]->value->items[1] = new KeyValue(
            new Key(['z'], [KeyStyle::Bare], $this->span()),
            new IntegerValue(9, IntegerBase::Decimal, $this->span()),
            $this->span(),
        );

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame(<<<'TOML'
items = [
  { point = { x = 1, z = 9 } },
]
TOML, $encoded);
    }

    public function testEncodeDocumentPreservesHeaderSpacingForTableKeyEdit(): void
    {
        $doc = Toml::parse(<<<'TOML'
[ server . "old.key" ]
value = 1
TOML, true);

        $table = $doc->items[0];
        $this->assertInstanceOf(Table::class, $table);

        $table->key->parts[1] = 'new.key';

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame(<<<'TOML'
[ server . "new.key" ]
value = 1
TOML, $encoded);
    }

    public function testEncodeDocumentPreservesDottedKeySeparatorSpacingForKeyEdit(): void
    {
        $doc = Toml::parse("server . \"old\"   = 1\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);

        $item->key->parts[1] = 'new';

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame("server . \"new\" = 1\n", $encoded);
    }

    public function testEncodeDocumentPreservesAssignmentSpacingForMultilineStringEdit(): void
    {
        $doc = Toml::parse("message   =   \"old\"\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);

        $item->value = new StringValue("line1\nline2", StringStyle::MultiLineBasic, $this->span());

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame(<<<'TOML'
message   =   """
line1
line2"""
TOML . "\n", $encoded);
    }

    public function testEncodeDocumentPreservesArrayTableHeaderSpacingForKeyEdit(): void
    {
        $doc = Toml::parse(<<<'TOML'
[[ products . "old.name" ]]
value = 1
TOML, true);

        $table = $doc->items[0];
        $this->assertInstanceOf(Table::class, $table);

        $table->key->parts[1] = 'new.name';

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame(<<<'TOML'
[[ products . "new.name" ]]
value = 1
TOML, $encoded);
    }

    public function testEncodeDocumentPreservesDottedKeySpacingInsideInlineTableEdit(): void
    {
        $doc = Toml::parse("point = { nested . \"old\" = 1, plain = 2 }\n", true);

        $item = $doc->items[0];
        $this->assertInstanceOf(KeyValue::class, $item);
        $this->assertInstanceOf(InlineTable::class, $item->value);

        $item->value->items[0]->key->parts[1] = 'new';

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame("point = { nested . \"new\" = 1, plain = 2 }\n", $encoded);
    }

    public function testEncodeDocumentPreservesDottedKeySpacingInsideArrayTableEntryEdit(): void
    {
        $doc = Toml::parse(<<<'TOML'
[[ products ]]
name . "old" = 1
TOML, true);

        $table = $doc->items[0];
        $this->assertInstanceOf(Table::class, $table);
        $this->assertCount(1, $table->items);

        $table->items[0]->key->parts[1] = 'new';

        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame(<<<'TOML'
[[ products ]]
name . "new" = 1
TOML, $encoded);
    }

    private function span(): Span
    {
        return new Span(0, 0, 1, 1);
    }
}
