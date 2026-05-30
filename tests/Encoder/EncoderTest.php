<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Encoder;

use DateTimeImmutable;
use PhpCollective\Toml\Encoder\ArrayStyle;
use PhpCollective\Toml\Encoder\DocumentFormattingMode;
use PhpCollective\Toml\Encoder\Encoder;
use PhpCollective\Toml\Encoder\EncoderOptions;
use PhpCollective\Toml\Encoder\StringStyle;
use PhpCollective\Toml\Exception\EncodeException;
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\Value\LocalDate;
use PhpCollective\Toml\Value\LocalDateTime;
use PhpCollective\Toml\Value\LocalTime;
use PHPUnit\Framework\TestCase;

final class EncoderTest extends TestCase
{
    public function testEncodeScalars(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'string' => 'hello',
            'integer' => 42,
            'float' => 3.14,
            'bool' => true,
        ]);

        $this->assertStringContainsString('string = "hello"', $result);
        $this->assertStringContainsString('integer = 42', $result);
        $this->assertStringContainsString('float = 3.14', $result);
        $this->assertStringContainsString('bool = true', $result);
    }

    public function testEncodeArray(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'items' => [1, 2, 3],
        ]);

        $this->assertStringContainsString('items = [1, 2, 3]', $result);
    }

    public function testEncodeNestedTable(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'server' => [
                'host' => 'localhost',
                'port' => 8080,
            ],
        ]);

        $this->assertStringContainsString('[server]', $result);
        $this->assertStringContainsString('host = "localhost"', $result);
    }

    public function testEncodeArrayOfTables(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'products' => [
                ['name' => 'Hammer'],
                ['name' => 'Nail'],
            ],
        ]);

        $this->assertStringContainsString('[[products]]', $result);
    }

    public function testEncodeBooleans(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'yes' => true,
            'no' => false,
        ]);

        $this->assertStringContainsString('yes = true', $result);
        $this->assertStringContainsString('no = false', $result);
    }

    public function testEncodeSpecialFloats(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'positive_inf' => INF,
            'negative_inf' => -INF,
            'not_a_number' => NAN,
        ]);

        $this->assertStringContainsString('positive_inf = inf', $result);
        $this->assertStringContainsString('negative_inf = -inf', $result);
        $this->assertStringContainsString('not_a_number = nan', $result);
    }

    public function testEncodeStringWithEscapes(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'path' => "line1\nline2",
            'quote' => 'say "hello"',
        ]);

        $this->assertStringContainsString('path = "line1\\nline2"', $result);
        $this->assertStringContainsString('quote = "say \\"hello\\""', $result);
    }

    public function testEncodeStringStyleLiteral(): void
    {
        $encoder = new Encoder(new EncoderOptions(stringStyle: StringStyle::Literal));

        $result = $encoder->encode([
            'path' => 'C:\Users\name',
        ]);

        $this->assertStringContainsString("path = 'C:\\Users\\name'", $result);
    }

    public function testEncodeStringStyleLiteralFallsBackToBasicWhenNeeded(): void
    {
        $encoder = new Encoder(new EncoderOptions(stringStyle: StringStyle::Literal));

        $result = $encoder->encode([
            'quote' => "it's fine",
            'line' => "hello\nworld",
        ]);

        $this->assertStringContainsString('quote = "it\'s fine"', $result);
        $this->assertStringContainsString('line = "hello\\nworld"', $result);
    }

    public function testEncodeStringStyleMultilineBasic(): void
    {
        $encoder = new Encoder(new EncoderOptions(stringStyle: StringStyle::MultiLineBasic));

        $result = $encoder->encode([
            'message' => "hello\nworld",
        ]);

        $this->assertStringContainsString("message = \"\"\"\nhello\nworld\"\"\"", $result);
    }

    public function testEncodeStringStyleMultilineLiteral(): void
    {
        $encoder = new Encoder(new EncoderOptions(stringStyle: StringStyle::MultiLineLiteral));

        $result = $encoder->encode([
            'message' => "hello\nworld",
        ]);

        $this->assertStringContainsString("message = '''\nhello\nworld'''", $result);
    }

    public function testEncodeStringStyleDoesNotChangeKeyStyle(): void
    {
        $encoder = new Encoder(new EncoderOptions(stringStyle: StringStyle::Literal));

        $result = $encoder->encode([
            'key with spaces' => 'value',
        ]);

        $this->assertStringContainsString('"key with spaces" = \'value\'', $result);
    }

    public function testEncodeDateTime(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $date = new DateTimeImmutable('2024-03-15T10:30:00+00:00');

        $result = $encoder->encode([
            'created' => $date,
        ]);

        $this->assertStringContainsString('created = 2024-03-15T10:30:00', $result);
    }

    public function testEncodeQuotedKey(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'simple' => 1,
            'key with spaces' => 2,
        ]);

        $this->assertStringContainsString('simple = 1', $result);
        $this->assertStringContainsString('"key with spaces" = 2', $result);
    }

    public function testEncodeSortedKeys(): void
    {
        $encoder = new Encoder(new EncoderOptions(sortKeys: true));

        $result = $encoder->encode([
            'zebra' => 1,
            'apple' => 2,
            'mango' => 3,
        ]);

        // With sorted keys, apple should come before mango which comes before zebra
        $applePos = strpos($result, 'apple');
        $mangoPos = strpos($result, 'mango');
        $zebraPos = strpos($result, 'zebra');

        $this->assertLessThan($mangoPos, $applePos);
        $this->assertLessThan($zebraPos, $mangoPos);
    }

    public function testEncodeWithCustomNewline(): void
    {
        $encoder = new Encoder(new EncoderOptions(newline: "\r\n"));

        $result = $encoder->encode([
            'name' => 'test',
            'count' => 42,
        ]);

        $this->assertStringContainsString("\r\n", $result);
    }

    public function testEncodeNullThrowsException(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('TOML does not support null values');

        $encoder->encode(['value' => null]);
    }

    public function testEncodeSkipNullsInTable(): void
    {
        $encoder = new Encoder(new EncoderOptions(skipNulls: true));

        $result = $encoder->encode([
            'present' => 'value',
            'missing' => null,
            'also_present' => 42,
        ]);

        $this->assertStringContainsString('present = "value"', $result);
        $this->assertStringContainsString('also_present = 42', $result);
        $this->assertStringNotContainsString('missing', $result);
    }

    public function testEncodeSkipNullsInArray(): void
    {
        $encoder = new Encoder(new EncoderOptions(skipNulls: true));

        $result = $encoder->encode([
            'items' => [1, null, 2, null, 3],
        ]);

        $this->assertStringContainsString('items = [1, 2, 3]', $result);
    }

    public function testEncodeSkipNullsInInlineTable(): void
    {
        $encoder = new Encoder(new EncoderOptions(skipNulls: true));

        $result = $encoder->encode([
            'points' => [
                ['x' => 1, 'y' => null, 'z' => 3],
            ],
        ]);

        $this->assertStringContainsString('x = 1', $result);
        $this->assertStringContainsString('z = 3', $result);
        $this->assertStringNotContainsString('y', $result);
    }

    public function testEncodeSkipNullsInNestedTable(): void
    {
        $encoder = new Encoder(new EncoderOptions(skipNulls: true));

        $result = $encoder->encode([
            'database' => [
                'host' => 'localhost',
                'password' => null,
                'port' => 5432,
            ],
        ]);

        $this->assertStringContainsString('[database]', $result);
        $this->assertStringContainsString('host = "localhost"', $result);
        $this->assertStringContainsString('port = 5432', $result);
        $this->assertStringNotContainsString('password', $result);
    }

    public function testEncodeNestedArrayOfTables(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'servers' => [
                [
                    'name' => 'alpha',
                    'ports' => [8000, 8001],
                ],
                [
                    'name' => 'beta',
                    'ports' => [9000],
                ],
            ],
        ]);

        $this->assertStringContainsString('[[servers]]', $result);
        $this->assertStringContainsString('name = "alpha"', $result);
        $this->assertStringContainsString('ports = [8000, 8001]', $result);
        $this->assertStringContainsString('name = "beta"', $result);
    }

    public function testEncodeEmptyArray(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'empty' => [],
        ]);

        $this->assertStringContainsString('empty = []', $result);
    }

    public function testEncodeMixedArray(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'mixed' => [1, 'two', 3.0, true],
        ]);

        $this->assertStringContainsString('mixed = [1, "two", 3.0, true]', $result);
    }

    public function testEncodeLocalTemporalValues(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'date' => new LocalDate('2024-03-15'),
            'time' => new LocalTime('10:30:45'),
            'timestamp' => new LocalDateTime('2024-03-15T10:30:45'),
        ]);

        $this->assertStringContainsString('date = 2024-03-15', $result);
        $this->assertStringContainsString('time = 10:30:45', $result);
        $this->assertStringContainsString('timestamp = 2024-03-15T10:30:45', $result);
    }

    public function testEncodeDocumentDefaultsToNormalizedOutput(): void
    {
        $document = Toml::parse("count   =   1\n", true);

        $result = Toml::encodeDocument($document);

        $this->assertSame('count = 1', $result);
    }

    public function testEncodeDocumentCanUseSourceAwareFormatting(): void
    {
        $document = Toml::parse("count   =   1\n", true);

        $result = Toml::encodeDocument(
            $document,
            new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware),
        );

        $this->assertSame('count   =   1' . "\n", $result);
    }

    public function testEncodeIntegerGrouping(): void
    {
        $encoder = new Encoder(new EncoderOptions(integerGrouping: true));

        $result = $encoder->encode([
            'small' => 42,
            'medium' => 1000,
            'large' => 1000000,
            'huge' => 1234567890,
            'negative' => -9876543,
        ]);

        $this->assertStringContainsString('small = 42', $result);
        $this->assertStringContainsString('medium = 1_000', $result);
        $this->assertStringContainsString('large = 1_000_000', $result);
        $this->assertStringContainsString('huge = 1_234_567_890', $result);
        $this->assertStringContainsString('negative = -9_876_543', $result);
    }

    public function testEncodeIntegerGroupingDisabledByDefault(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'large' => 1000000,
        ]);

        $this->assertStringContainsString('large = 1000000', $result);
    }

    public function testEncodeTrailingComma(): void
    {
        $encoder = new Encoder(new EncoderOptions(trailingComma: true));

        $result = $encoder->encode([
            'items' => [1, 2, 3],
        ]);

        $this->assertStringContainsString('items = [1, 2, 3,]', $result);
    }

    public function testEncodeTrailingCommaEmptyArray(): void
    {
        $encoder = new Encoder(new EncoderOptions(trailingComma: true));

        $result = $encoder->encode([
            'empty' => [],
        ]);

        $this->assertStringContainsString('empty = []', $result);
    }

    public function testEncodeTrailingCommaDisabledByDefault(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'items' => [1, 2, 3],
        ]);

        $this->assertStringContainsString('items = [1, 2, 3]', $result);
        $this->assertStringNotContainsString('[1, 2, 3,]', $result);
    }

    public function testEncodeDottedKeys(): void
    {
        $encoder = new Encoder(new EncoderOptions(dottedKeys: true));

        $result = $encoder->encode([
            'database' => [
                'host' => 'localhost',
                'port' => 5432,
            ],
        ]);

        $this->assertStringContainsString('database.host = "localhost"', $result);
        $this->assertStringContainsString('database.port = 5432', $result);
        $this->assertStringNotContainsString('[database]', $result);
    }

    public function testEncodeDottedKeysDeepNesting(): void
    {
        $encoder = new Encoder(new EncoderOptions(dottedKeys: true));

        $result = $encoder->encode([
            'a' => [
                'b' => [
                    'c' => 'value',
                ],
            ],
        ]);

        $this->assertStringContainsString('a.b.c = "value"', $result);
        $this->assertStringNotContainsString('[a]', $result);
        $this->assertStringNotContainsString('[a.b]', $result);
    }

    public function testEncodeDottedKeysWithArrayOfTables(): void
    {
        $encoder = new Encoder(new EncoderOptions(dottedKeys: true));

        $result = $encoder->encode([
            'servers' => [
                ['name' => 'alpha'],
                ['name' => 'beta'],
            ],
        ]);

        // Array of tables still requires [[]] syntax
        $this->assertStringContainsString('[[servers]]', $result);
        $this->assertStringContainsString('name = "alpha"', $result);
        $this->assertStringContainsString('name = "beta"', $result);
    }

    public function testEncodeDottedKeysDisabledByDefault(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'database' => [
                'host' => 'localhost',
            ],
        ]);

        $this->assertStringContainsString('[database]', $result);
        $this->assertStringContainsString('host = "localhost"', $result);
        $this->assertStringNotContainsString('database.host', $result);
    }

    public function testEncodeArrayStyleInlineByDefault(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'items' => [1, 2, 3, 4, 5],
        ]);

        $this->assertStringContainsString('items = [1, 2, 3, 4, 5]', $result);
        $this->assertStringNotContainsString("\n    ", $result);
    }

    public function testEncodeArrayStyleMultiline(): void
    {
        $encoder = new Encoder(new EncoderOptions(arrayStyle: ArrayStyle::Multiline));

        $result = $encoder->encode([
            'items' => [1, 2, 3],
        ]);

        $expected = "items = [\n    1,\n    2,\n    3,\n]";
        $this->assertStringContainsString($expected, $result);
    }

    public function testEncodeArrayStyleMultilineEmpty(): void
    {
        $encoder = new Encoder(new EncoderOptions(arrayStyle: ArrayStyle::Multiline));

        $result = $encoder->encode([
            'empty' => [],
        ]);

        $this->assertStringContainsString('empty = []', $result);
    }

    public function testEncodeArrayStyleAutoAboveThreshold(): void
    {
        $encoder = new Encoder(new EncoderOptions(
            arrayStyle: ArrayStyle::Auto,
            arrayAutoThreshold: 3,
        ));

        $result = $encoder->encode([
            'items' => [1, 2, 3, 4],
        ]);

        $expected = "items = [\n    1,\n    2,\n    3,\n    4,\n]";
        $this->assertStringContainsString($expected, $result);
    }

    public function testEncodeArrayStyleAutoBelowThreshold(): void
    {
        $encoder = new Encoder(new EncoderOptions(
            arrayStyle: ArrayStyle::Auto,
            arrayAutoThreshold: 3,
        ));

        $result = $encoder->encode([
            'items' => [1, 2, 3],
        ]);

        $this->assertStringContainsString('items = [1, 2, 3]', $result);
        $this->assertStringNotContainsString("[\n", $result);
    }

    public function testEncodeArrayStyleCustomIndent(): void
    {
        $encoder = new Encoder(new EncoderOptions(
            arrayStyle: ArrayStyle::Multiline,
            indent: '  ',
        ));

        $result = $encoder->encode([
            'items' => [1, 2],
        ]);

        $expected = "items = [\n  1,\n  2,\n]";
        $this->assertStringContainsString($expected, $result);
    }

    public function testEncodeArrayStyleWithTabIndent(): void
    {
        $encoder = new Encoder(new EncoderOptions(
            arrayStyle: ArrayStyle::Multiline,
            indent: "\t",
        ));

        $result = $encoder->encode([
            'items' => [1, 2],
        ]);

        $expected = "items = [\n\t1,\n\t2,\n]";
        $this->assertStringContainsString($expected, $result);
    }

    public function testEncodeArrayStyleMultilineWithStrings(): void
    {
        $encoder = new Encoder(new EncoderOptions(arrayStyle: ArrayStyle::Multiline));

        $result = $encoder->encode([
            'hosts' => ['alpha', 'beta', 'gamma'],
        ]);

        $expected = "hosts = [\n    \"alpha\",\n    \"beta\",\n    \"gamma\",\n]";
        $this->assertStringContainsString($expected, $result);
    }

    public function testDiffFriendlyPreset(): void
    {
        $options = EncoderOptions::diffFriendly();

        $this->assertTrue($options->trailingComma);
        $this->assertSame(ArrayStyle::Auto, $options->arrayStyle);
    }

    public function testDiffFriendlyPresetProducesExpectedOutput(): void
    {
        $encoder = new Encoder(EncoderOptions::diffFriendly());

        $result = $encoder->encode([
            'small' => [1, 2],
            'large' => [1, 2, 3, 4],
        ]);

        // Small array stays inline with trailing comma
        $this->assertStringContainsString('small = [1, 2,]', $result);

        // Large array becomes multiline with trailing commas
        $this->assertStringContainsString("large = [\n    1,\n    2,\n    3,\n    4,\n]", $result);
    }
}
