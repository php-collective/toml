<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Parser;

use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Value\ArrayValue;
use PhpCollective\Toml\Ast\Value\InlineTable;
use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;

/**
 * Tests for improved error recovery in the parser.
 *
 * These tests verify that the parser can recover from errors inside collections
 * (arrays, inline tables) without cascading errors and while preserving as much
 * of the document structure as possible.
 */
final class ErrorRecoveryTest extends TestCase
{
    public function testRecoveryInInlineTableMissingValue(): void
    {
        $input = <<<'TOML'
data = {name = , age = 30}
other = "test"
TOML;

        $result = Toml::tryParse($input);

        // Should only have 1 error for the missing value, not cascading errors
        $this->assertCount(1, $result->getErrors());
        $this->assertSame('Expected value', $result->getErrors()[0]->message);
        $this->assertSame(1, $result->getErrors()[0]->span->line);

        // Both document items should be parsed
        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertCount(2, $doc->items);

        $this->assertInstanceOf(KeyValue::class, $doc->items[0]);
        $this->assertSame(['data'], $doc->items[0]->key->parts);

        $this->assertInstanceOf(KeyValue::class, $doc->items[1]);
        $this->assertSame(['other'], $doc->items[1]->key->parts);
    }

    public function testRecoveryInInlineTableRecoversContinuingItems(): void
    {
        $input = '{a = 1, b = , c = 3}';

        $result = Toml::tryParse('t = ' . $input);

        // Should only have 1 error
        $this->assertCount(1, $result->getErrors());
        $this->assertSame('Expected value', $result->getErrors()[0]->message);

        // The inline table should have 2 items: a and c (b failed)
        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertCount(1, $doc->items);
        $this->assertInstanceOf(KeyValue::class, $doc->items[0]);

        $inlineTable = $doc->items[0]->value;
        $this->assertInstanceOf(InlineTable::class, $inlineTable);
        $this->assertCount(2, $inlineTable->items);

        $this->assertSame(['a'], $inlineTable->items[0]->key->parts);
        $this->assertSame(['c'], $inlineTable->items[1]->key->parts);
    }

    public function testRecoveryInArrayOfInlineTables(): void
    {
        $input = <<<'TOML'
data = [{a = 1}, {b = }, {c = 3}]
other = "value"
TOML;

        $result = Toml::tryParse($input);

        // Should only have 1 error for the missing value in second inline table
        $this->assertCount(1, $result->getErrors());
        $this->assertSame('Expected value', $result->getErrors()[0]->message);
        $this->assertSame(1, $result->getErrors()[0]->span->line);

        // Both document items should be parsed
        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertCount(2, $doc->items);

        $this->assertInstanceOf(KeyValue::class, $doc->items[0]);
        $this->assertSame(['data'], $doc->items[0]->key->parts);

        $this->assertInstanceOf(KeyValue::class, $doc->items[1]);
        $this->assertSame(['other'], $doc->items[1]->key->parts);
    }

    public function testRecoveryInDeeplyNestedInlineTable(): void
    {
        $input = <<<'TOML'
deep = {level1 = {level2 = {invalid = }}}
after = "test"
TOML;

        $result = Toml::tryParse($input);

        // Should only have 1 error for the missing value at the deepest level
        $this->assertCount(1, $result->getErrors());
        $this->assertSame('Expected value', $result->getErrors()[0]->message);
        $this->assertSame(1, $result->getErrors()[0]->span->line);

        // Both document items should be parsed
        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertCount(2, $doc->items);

        $this->assertInstanceOf(KeyValue::class, $doc->items[0]);
        $this->assertSame(['deep'], $doc->items[0]->key->parts);

        $this->assertInstanceOf(KeyValue::class, $doc->items[1]);
        $this->assertSame(['after'], $doc->items[1]->key->parts);
    }

    public function testRecoveryInArrayWithInvalidValue(): void
    {
        // Tests that array recovery skips invalid values and continues
        $input = <<<'TOML'
arr = [1, , 3]
other = 123
TOML;

        $result = Toml::tryParse($input);

        // Should have 1 error for the empty array element
        $this->assertCount(1, $result->getErrors());
        $this->assertStringContainsString('Expected value', $result->getErrors()[0]->message);

        // Both document items should be parsed
        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertCount(2, $doc->items);
    }

    public function testRecoveryInNestedArrays(): void
    {
        $input = <<<'TOML'
arr = [1, [2, , 4], 5]
after = "test"
TOML;

        $result = Toml::tryParse($input);

        // Should have 1 error for the empty nested array element
        $this->assertCount(1, $result->getErrors());

        // Both document items should be parsed
        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertCount(2, $doc->items);
    }

    public function testTopLevelRecoveryStillWorks(): void
    {
        // Test that top-level synchronization still works
        $input = <<<'TOML'
[table
key = "value"
other = 123
TOML;

        $result = Toml::tryParse($input);

        // Should have error for missing bracket
        $this->assertGreaterThanOrEqual(1, count($result->getErrors()));

        // The key-value pairs should be recovered
        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertGreaterThanOrEqual(2, count($doc->items));
    }

    public function testMultipleInlineTableErrorsInSameTable(): void
    {
        $input = '{a = , b = , c = 3}';

        $result = Toml::tryParse('t = ' . $input);

        // Should have 2 errors (one for each missing value)
        $this->assertCount(2, $result->getErrors());

        // The inline table should have 1 item: c (a and b failed)
        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertCount(1, $doc->items);
        $this->assertInstanceOf(KeyValue::class, $doc->items[0]);

        $inlineTable = $doc->items[0]->value;
        $this->assertInstanceOf(InlineTable::class, $inlineTable);
        $this->assertCount(1, $inlineTable->items);

        $this->assertSame(['c'], $inlineTable->items[0]->key->parts);
    }

    public function testRecoveryPreservesValidArrayElements(): void
    {
        $input = 'arr = [1, 2, invalid, 4, 5]';

        $result = Toml::tryParse($input);

        // Should have error for the invalid element
        $this->assertGreaterThanOrEqual(1, count($result->getErrors()));

        // Array should still be created
        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertCount(1, $doc->items);
        $this->assertInstanceOf(KeyValue::class, $doc->items[0]);

        $arr = $doc->items[0]->value;
        $this->assertInstanceOf(ArrayValue::class, $arr);

        // Should have parsed elements before and after the invalid one
        $this->assertGreaterThanOrEqual(2, count($arr->items));
    }

    public function testRecoveryCollectsMultipleErrorsAcrossLines(): void
    {
        // Lines 1, 2 and 4 are broken; line 3 is valid and must still parse.
        $result = Toml::tryParse("a = \nb = @\nc = 1\nd = ]");

        $errors = $result->getErrors();
        $this->assertCount(3, $errors);
        $this->assertSame([1, 2, 4], array_map(static fn ($e) => $e->span->line, $errors));

        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $valid = array_filter($doc->items, static fn ($item) => $item instanceof KeyValue && $item->key->parts === ['c']);
        $this->assertCount(1, $valid);
    }

    public function testRecoveryContinuesAcrossTableHeaders(): void
    {
        // An error in one table must not stop collection in a later table.
        $result = Toml::tryParse("[t]\nx = \ny = 1\n[u]\nz = @");

        $errors = $result->getErrors();
        $this->assertCount(2, $errors);
        $this->assertSame(2, $errors[0]->span->line);
        $this->assertSame(5, $errors[1]->span->line);
    }

    public function testMissingCommaInInlineTableReportsOnceAndRecovers(): void
    {
        // A single missing separator must not cascade or leak the `}` to the
        // document parser; both key-values still parse.
        $result = Toml::tryParse('t = { x = 1 y = 2 }');

        $errors = $result->getErrors();
        $this->assertCount(1, $errors);
        $this->assertSame('Expected , or } in inline table', $errors[0]->message);

        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertInstanceOf(KeyValue::class, $doc->items[0]);
        $inlineTable = $doc->items[0]->value;
        $this->assertInstanceOf(InlineTable::class, $inlineTable);
        $this->assertCount(2, $inlineTable->items);
    }

    public function testMultipleMissingCommasInInlineTableRecoverEveryItem(): void
    {
        $result = Toml::tryParse('t = { x = 1 y = 2 z = 3 }');

        $this->assertCount(2, $result->getErrors());

        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertInstanceOf(KeyValue::class, $doc->items[0]);
        $inlineTable = $doc->items[0]->value;
        $this->assertInstanceOf(InlineTable::class, $inlineTable);
        $this->assertCount(3, $inlineTable->items);
    }

    public function testMissingCommaInArrayReportsAndRecovers(): void
    {
        $result = Toml::tryParse('a = [1 2 3]');

        // One separator error per gap, no document-level cascade.
        $this->assertCount(2, $result->getErrors());
        foreach ($result->getErrors() as $error) {
            $this->assertSame('Expected , or ] in array', $error->message);
        }

        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertInstanceOf(KeyValue::class, $doc->items[0]);
        $array = $doc->items[0]->value;
        $this->assertInstanceOf(ArrayValue::class, $array);
        $this->assertCount(3, $array->items);
    }

    public function testUnterminatedArrayDoesNotSwallowFollowingKey(): void
    {
        // The array is missing its `]`; `b` is a new top-level key on the next line
        // and must not be absorbed into the array recovery.
        $result = Toml::tryParse("a = [1\nb = 2");

        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $keys = array_map(
            static fn ($item) => $item instanceof KeyValue ? implode('.', $item->key->parts) : null,
            $doc->items,
        );
        $this->assertContains('b', $keys);
    }

    public function testUnterminatedInlineTableDoesNotSwallowFollowingKey(): void
    {
        $result = Toml::tryParse("a = { x = 1\nb = 2");

        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $keys = array_map(
            static fn ($item) => $item instanceof KeyValue ? implode('.', $item->key->parts) : null,
            $doc->items,
        );
        $this->assertContains('b', $keys);
    }

    public function testUnterminatedArrayDoesNotSwallowValueLikeKey(): void
    {
        // `2` is a value token but here begins a top-level `2 = 3` key; the newline
        // boundary must keep it out of the array.
        $result = Toml::tryParse("a = [1\n2 = 3");

        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $keys = array_map(
            static fn ($item) => $item instanceof KeyValue ? implode('.', $item->key->parts) : null,
            $doc->items,
        );
        $this->assertContains('2', $keys);
    }

    public function testSameLineMissingCommaRecoversValueKeywordsAndMultilineStrings(): void
    {
        // No newline in the gaps, so these are missing-comma typos: report and recover
        // without dropping the top-level key.
        $result = Toml::tryParse('a = [1 nan "x"]');

        $doc = $result->getDocument();
        $this->assertNotNull($doc);
        $this->assertInstanceOf(KeyValue::class, $doc->items[0]);
        $array = $doc->items[0]->value;
        $this->assertInstanceOf(ArrayValue::class, $array);
        $this->assertCount(3, $array->items);
    }
}
