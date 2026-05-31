<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Encoder;

use DateTimeImmutable;
use PhpCollective\Toml\Ast\Value\IntegerBase;
use PhpCollective\Toml\Encoder\ArrayStyle;
use PhpCollective\Toml\Encoder\DocumentFormattingMode;
use PhpCollective\Toml\Encoder\Encoder;
use PhpCollective\Toml\Encoder\EncoderOptions;
use PhpCollective\Toml\Exception\EncodeException;
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\Value\LocalDate;
use PhpCollective\Toml\Value\LocalDateTime;
use PhpCollective\Toml\Value\LocalTime;
use PhpCollective\Toml\Value\TomlInteger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

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

    public function testEncodeIntegerBaseHexadecimal(): void
    {
        $encoder = new Encoder(new EncoderOptions(integerBase: IntegerBase::Hexadecimal));

        $result = $encoder->encode([
            'zero' => 0,
            'mask' => 255,
            'color' => 16711935,
        ]);

        $this->assertStringContainsString('zero = 0x0', $result);
        $this->assertStringContainsString('mask = 0xFF', $result);
        $this->assertStringContainsString('color = 0xFF00FF', $result);
    }

    public function testEncodeIntegerBaseOctal(): void
    {
        $encoder = new Encoder(new EncoderOptions(integerBase: IntegerBase::Octal));

        $result = $encoder->encode([
            'perms' => 493,
        ]);

        $this->assertStringContainsString('perms = 0o755', $result);
    }

    public function testEncodeIntegerBaseBinary(): void
    {
        $encoder = new Encoder(new EncoderOptions(integerBase: IntegerBase::Binary));

        $result = $encoder->encode([
            'flags' => 10,
        ]);

        $this->assertStringContainsString('flags = 0b1010', $result);
    }

    public function testEncodeIntegerBaseFallsBackToDecimalForNegativeValues(): void
    {
        // TOML hex/octal/binary literals are unsigned; negatives stay decimal so the
        // output remains valid TOML.
        $encoder = new Encoder(new EncoderOptions(integerBase: IntegerBase::Hexadecimal));

        $result = $encoder->encode([
            'negative' => -255,
        ]);

        $this->assertStringContainsString('negative = -255', $result);
        $this->assertSame(-255, Toml::decode($result)['negative']);
    }

    public function testEncodeIntegerBaseDefaultsToDecimal(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'value' => 255,
        ]);

        $this->assertStringContainsString('value = 255', $result);
    }

    public function testEncodeIntegerBaseRoundTripsToSameValue(): void
    {
        $encoder = new Encoder(new EncoderOptions(integerBase: IntegerBase::Hexadecimal));

        $toml = $encoder->encode([
            'mask' => 255,
        ]);

        $this->assertSame(255, Toml::decode($toml)['mask']);
    }

    public function testEncodeIntegerBaseDoesNotAffectSourceAwareDocumentBase(): void
    {
        // integerBase governs the array-encode path; source-aware document
        // re-encoding preserves each integer's original source base instead.
        $document = Toml::parse('mask = 0xFF' . "\n", true);

        $toml = Toml::encodeDocument(
            $document,
            new EncoderOptions(
                documentFormatting: DocumentFormattingMode::SourceAware,
                integerBase: IntegerBase::Octal,
            ),
        );

        $this->assertStringContainsString('0xFF', $toml);
        $this->assertStringNotContainsString('0o', $toml);
    }

    public function testEncodeMixedIntegerBasesViaTomlIntegerValueObject(): void
    {
        // TomlInteger lets a plain array carry per-value bases, unlike the global
        // integerBase option which applies one radix to every integer.
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'mask' => new TomlInteger(255, IntegerBase::Hexadecimal),
            'mode' => new TomlInteger(493, IntegerBase::Octal),
            'flags' => new TomlInteger(10, IntegerBase::Binary),
            'count' => 10,
        ]);

        $this->assertStringContainsString('mask = 0xFF', $result);
        $this->assertStringContainsString('mode = 0o755', $result);
        $this->assertStringContainsString('flags = 0b1010', $result);
        $this->assertStringContainsString('count = 10', $result);

        $decoded = Toml::decode($result);
        $this->assertSame(255, $decoded['mask']);
        $this->assertSame(493, $decoded['mode']);
        $this->assertSame(10, $decoded['flags']);
        $this->assertSame(10, $decoded['count']);
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

    /**
     * @return iterable<string, array{float}>
     */
    public static function precisionFloatProvider(): iterable
    {
        yield 'pi' => [3.141592653589793];
        yield 'long-decimal' => [123456789.123456789];
        yield 'max-double' => [1.7976931348623157e308];
        yield 'min-normal' => [2.2250738585072014e-308];
        yield 'tenth' => [0.1];
    }

    #[DataProvider('precisionFloatProvider')]
    public function testEncodeFloatRoundTripsWithFullPrecision(float $value): void
    {
        // (string) casts obey precision=14 and drop digits; the encoder must emit a
        // representation that decodes back to the exact same double.
        $encoder = new Encoder(new EncoderOptions());

        $toml = $encoder->encode(['x' => $value]);

        $this->assertSame($value, Toml::decode($toml)['x']);
    }

    public function testEncodeEscapesControlCharactersInBasicString(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'esc' => "\x1B",
            'nul' => "\x00",
            'del' => "\x7F",
            'unit' => "\x1F",
        ]);

        $this->assertStringContainsString('esc = "\u001B"', $result);
        $this->assertStringContainsString('nul = "\u0000"', $result);
        $this->assertStringContainsString('del = "\u007F"', $result);
        $this->assertStringContainsString('unit = "\u001F"', $result);
    }

    public function testEncodeControlCharactersRoundTrip(): void
    {
        $encoder = new Encoder(new EncoderOptions());
        $value = "a\x00b\x1Bc\x7Fd";

        $toml = $encoder->encode(['x' => $value]);

        $this->assertSame($value, Toml::decode($toml)['x']);
    }

    public function testEncodeOffsetDateTimeOmitsZeroMicroseconds(): void
    {
        // A value without sub-second precision must not gain a spurious `.000000`.
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'x' => new DateTimeImmutable('1979-05-27T07:32:00-08:00'),
        ]);

        $this->assertStringContainsString('x = 1979-05-27T07:32:00-08:00', $result);
        $this->assertStringNotContainsString('.000000', $result);
    }

    public function testEncodeOffsetDateTimeKeepsNonZeroFraction(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'x' => new DateTimeImmutable('1979-05-27T07:32:00.500000-08:00'),
        ]);

        $this->assertStringContainsString('x = 1979-05-27T07:32:00.5-08:00', $result);
    }

    public function testEncodeOffsetDateTimeUsesZuluForUtc(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'x' => new DateTimeImmutable('1987-07-05T17:45:00+00:00'),
        ]);

        $this->assertStringContainsString('x = 1987-07-05T17:45:00Z', $result);
    }

    public function testEncodeEmptyStdClassAsEmptyTable(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode(['settings' => new stdClass()]);

        $this->assertSame('[settings]', trim($result));
    }

    public function testEncodeEmptyArrayStillEncodesAsArray(): void
    {
        // Backward compatible: an empty array is unchanged by stdClass table support.
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode(['arr' => []]);

        $this->assertSame('arr = []', trim($result));
    }

    public function testEncodeStdClassAsTableSection(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $object = new stdClass();
        $object->host = 'localhost';
        $object->port = 8080;

        $result = $encoder->encode(['server' => $object]);

        $this->assertStringContainsString('[server]', $result);
        $this->assertStringContainsString('host = "localhost"', $result);
        $this->assertStringContainsString('port = 8080', $result);
    }

    public function testEncodeNestedEmptyStdClass(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $outer = new stdClass();
        $outer->inner = new stdClass();

        $result = $encoder->encode(['a' => $outer]);

        $this->assertStringContainsString('[a]', $result);
        $this->assertStringContainsString('[a.inner]', $result);
    }

    public function testEncodeStdClassInsideArrayAsInlineTable(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $populated = new stdClass();
        $populated->x = 1;

        $result = $encoder->encode(['list' => [$populated, new stdClass()]]);

        $this->assertStringContainsString('list = [{ x = 1 }, {}]', $result);
    }

    public function testEncodeStdClassRespectsInlineTableThreshold(): void
    {
        $encoder = new Encoder(new EncoderOptions(inlineTableThreshold: 3));

        $point = new stdClass();
        $point->x = 1;
        $point->y = 2;

        $result = $encoder->encode(['p' => $point]);

        $this->assertSame('p = { x = 1, y = 2 }', trim($result));
    }

    public function testEncodeEmptyStdClassWithDottedKeys(): void
    {
        // Dotted-key mode emits no [table] headers, so an empty table is written inline.
        $encoder = new Encoder(new EncoderOptions(dottedKeys: true));

        $result = $encoder->encode(['a' => (object)['b' => new stdClass()]]);

        $this->assertSame('a.b = {}', trim($result));
    }
}
