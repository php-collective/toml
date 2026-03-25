<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Integration;

use PhpCollective\Toml\Ast\Document;
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EditingFixtureTest extends TestCase
{
    #[DataProvider('fixtureProvider')]
    public function testEditedFixturesReencodeAsExpected(string $caseDir): void
    {
        $input = $this->readFixture($caseDir . '/input.toml');
        $expected = $this->readFixture($caseDir . '/expected.toml');
        $document = Toml::parse($input, true);

        match (basename($caseDir)) {
            'single-line-array-remove' => $this->applySingleLineArrayRemoval($document),
            'single-line-inline-insert' => $this->applySingleLineInlineInsert($document),
            'single-line-value-replace' => $this->applySingleLineValueReplace($document),
            'single-line-inline-value-replace' => $this->applySingleLineInlineValueReplace($document),
            'multiline-string-replace' => $this->applyMultilineStringReplace($document),
            'array-table-header-edit' => $this->applyArrayTableHeaderEdit($document),
            'inline-table-dotted-key-edit' => $this->applyInlineTableDottedKeyEdit($document),
            'array-table-dotted-key-edit' => $this->applyArrayTableDottedKeyEdit($document),
            'nested-multiline-array-remove' => $this->applyNestedMultilineArrayRemoval($document),
            'nested-inline-replace' => $this->applyNestedInlineReplacement($document),
            default => $this->fail('Unknown editing fixture: ' . basename($caseDir)),
        };

        $this->assertSame(
            $expected,
            Toml::encodeDocument($document, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware)),
            basename($caseDir),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function fixtureProvider(): iterable
    {
        $paths = glob(__DIR__ . '/../Fixtures/Editing/*');
        if ($paths === false) {
            return;
        }

        sort($paths);

        foreach ($paths as $path) {
            if (is_dir($path)) {
                yield basename($path) => [$path];
            }
        }
    }

    private function applySingleLineArrayRemoval(Document $document): void
    {
        $item = $document->items[0];
        self::assertInstanceOf(KeyValue::class, $item);
        self::assertInstanceOf(ArrayValue::class, $item->value);

        array_splice($item->value->items, 1, 1);
    }

    private function applySingleLineInlineInsert(Document $document): void
    {
        $item = $document->items[0];
        self::assertInstanceOf(KeyValue::class, $item);
        self::assertInstanceOf(InlineTable::class, $item->value);

        $item->value->items[] = new KeyValue(
            new Key(['z'], [KeyStyle::Bare], $this->span()),
            new IntegerValue(3, IntegerBase::Decimal, $this->span()),
            $this->span(),
        );
    }

    private function applyNestedMultilineArrayRemoval(Document $document): void
    {
        $item = $document->items[0];
        self::assertInstanceOf(KeyValue::class, $item);
        self::assertInstanceOf(ArrayValue::class, $item->value);
        self::assertInstanceOf(InlineTable::class, $item->value->items[0]);
        self::assertInstanceOf(ArrayValue::class, $item->value->items[0]->items[0]->value);

        array_splice($item->value->items[0]->items[0]->value->items, 1, 1);
    }

    private function applySingleLineValueReplace(Document $document): void
    {
        $item = $document->items[0];
        self::assertInstanceOf(KeyValue::class, $item);
        self::assertInstanceOf(IntegerValue::class, $item->value);

        $item->value = new IntegerValue(9, IntegerBase::Decimal, $this->span());
    }

    private function applySingleLineInlineValueReplace(Document $document): void
    {
        $item = $document->items[0];
        self::assertInstanceOf(KeyValue::class, $item);
        self::assertInstanceOf(InlineTable::class, $item->value);
        self::assertInstanceOf(IntegerValue::class, $item->value->items[0]->value);

        $item->value->items[0]->value = new IntegerValue(9, IntegerBase::Decimal, $this->span());
    }

    private function applyMultilineStringReplace(Document $document): void
    {
        $item = $document->items[0];
        self::assertInstanceOf(KeyValue::class, $item);

        $item->value = new StringValue("line1\nline2", StringStyle::MultiLineBasic, $this->span());
    }

    private function applyArrayTableHeaderEdit(Document $document): void
    {
        $table = $document->items[0];
        self::assertInstanceOf(Table::class, $table);

        $table->key->parts[1] = 'new.name';
    }

    private function applyInlineTableDottedKeyEdit(Document $document): void
    {
        $item = $document->items[0];
        self::assertInstanceOf(KeyValue::class, $item);
        self::assertInstanceOf(InlineTable::class, $item->value);

        $item->value->items[0]->key->parts[1] = 'new';
    }

    private function applyArrayTableDottedKeyEdit(Document $document): void
    {
        $table = $document->items[0];
        self::assertInstanceOf(Table::class, $table);
        self::assertCount(1, $table->items);

        $table->items[0]->key->parts[1] = 'new';
    }

    private function applyNestedInlineReplacement(Document $document): void
    {
        $item = $document->items[0];
        self::assertInstanceOf(KeyValue::class, $item);
        self::assertInstanceOf(ArrayValue::class, $item->value);
        self::assertInstanceOf(InlineTable::class, $item->value->items[0]);
        self::assertInstanceOf(InlineTable::class, $item->value->items[0]->items[0]->value);

        $item->value->items[0]->items[0]->value->items[1] = new KeyValue(
            new Key(['z'], [KeyStyle::Bare], $this->span()),
            new IntegerValue(9, IntegerBase::Decimal, $this->span()),
            $this->span(),
        );
    }

    private function readFixture(string $path): string
    {
        $contents = file_get_contents($path);
        self::assertNotFalse($contents);

        return $contents;
    }

    private function span(): Span
    {
        return new Span(0, 0, 1, 1);
    }
}
