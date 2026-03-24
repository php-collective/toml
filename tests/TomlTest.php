<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test;

use PhpCollective\Toml\Ast\Document;
use PhpCollective\Toml\Encoder\EncoderOptions;
use PhpCollective\Toml\Exception\ParseException;
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\Value\LocalDate;
use PhpCollective\Toml\Value\LocalDateTime;
use PhpCollective\Toml\Value\LocalTime;
use PHPUnit\Framework\TestCase;

final class TomlTest extends TestCase
{
    public function testDecode(): void
    {
        $result = Toml::decode(<<<'TOML'
[database]
host = "localhost"
port = 5432
TOML);

        $this->assertSame([
            'database' => [
                'host' => 'localhost',
                'port' => 5432,
            ],
        ], $result);
    }

    public function testDecodeThrowsOnInvalid(): void
    {
        $this->expectException(ParseException::class);
        // Unclosed string generates a lex error
        Toml::decode('key = "unclosed');
    }

    public function testTryParse(): void
    {
        $result = Toml::tryParse('key = "value"');

        $this->assertTrue($result->isValid());
        $this->assertSame(['key' => 'value'], $result->getValue());
    }

    public function testTryParseCollectsErrors(): void
    {
        // Use an unclosed string to generate an error
        $result = Toml::tryParse('key = "unclosed');

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function testParse(): void
    {
        $doc = Toml::parse('key = "value"');

        $this->assertInstanceOf(Document::class, $doc);
        $this->assertCount(1, $doc->items);
    }

    public function testEncode(): void
    {
        $result = Toml::encode([
            'name' => 'test',
            'count' => 42,
        ]);

        $this->assertStringContainsString('name = "test"', $result);
        $this->assertStringContainsString('count = 42', $result);
    }

    public function testEncodeWithOptions(): void
    {
        $result = Toml::encode(
            ['zebra' => 1, 'apple' => 2],
            new EncoderOptions(sortKeys: true),
        );

        $applePos = strpos($result, 'apple');
        $zebraPos = strpos($result, 'zebra');

        $this->assertLessThan($zebraPos, $applePos);
    }

    public function testEncodeDocument(): void
    {
        $doc = Toml::parse('original = "value"');
        $result = Toml::encodeDocument($doc);

        $this->assertStringContainsString('original = "value"', $result);
    }

    public function testRoundTrip(): void
    {
        // Scalars first, then tables - matches TOML output order
        $original = [
            'title' => 'Example',
            'items' => [1, 2, 3],
            'database' => [
                'host' => 'localhost',
                'port' => 5432,
            ],
        ];

        $encoded = Toml::encode($original);
        $decoded = Toml::decode($encoded);

        $this->assertSame($original, $decoded);
    }

    public function testDecodeFileThrowsOnMissingFile(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Cannot read file');

        Toml::decodeFile('/nonexistent/path/to/file.toml');
    }

    public function testDecodeRejectsDuplicateKey(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage("Duplicate key 'a'");

        Toml::decode("a = 1\na = 2\n");
    }

    public function testDecodeRejectsDuplicateTable(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage("Duplicate table 'a'");

        Toml::decode("[a]\nx = 1\n[a]\ny = 2\n");
    }

    public function testDecodeRejectsScalarTableConflict(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage("Cannot redefine key 'a' as a table");

        Toml::decode("a = 1\n[a]\nb = 2\n");
    }

    public function testDecodeRejectsInvalidEscapeSequence(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid token');

        Toml::decode("x = \"\\q\"\n");
    }

    public function testDecodeRejectsInvalidNumberLiteral(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid token');

        Toml::decode("n = 1__2\n");
    }

    public function testDecodeRejectsInvalidOffsetDateTime(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid token');

        Toml::decode("d = 2024-01-01T00:00:00+99:00\n");
    }

    public function testTryParseReportsSemanticErrors(): void
    {
        $result = Toml::tryParse("a = 1\n[a]\nb = 2\n");

        $this->assertFalse($result->isValid());
        $this->assertNull($result->getValue());
        $this->assertSame("Cannot redefine key 'a' as a table", $result->getErrors()[0]->message);
    }

    public function testDecodeNormalizesDottedInlineTableKeys(): void
    {
        $result = Toml::decode('point = { x.y = 1 }');

        $this->assertSame([
            'point' => [
                'x' => ['y' => 1],
            ],
        ], $result);
    }

    public function testDecodeRejectsReopeningInlineTableAsRegularTable(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage("Cannot redefine key 'a' as a table");

        Toml::decode("a = { b = 1 }\n[a]\nc = 2\n");
    }

    public function testDecodeRejectsReopeningArrayValueAsArrayTable(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage("Cannot redefine key 'a' as an array table");

        Toml::decode("a = []\n[[a]]\nb = 1\n");
    }

    public function testEncodeSupportsExplicitLocalTemporalValues(): void
    {
        $encoded = Toml::encode([
            'date' => new LocalDate('2024-03-15'),
            'time' => new LocalTime('10:30:45'),
            'timestamp' => new LocalDateTime('2024-03-15 10:30:45'),
        ]);

        $decoded = Toml::decode($encoded);

        $this->assertSame('2024-03-15', $decoded['date']);
        $this->assertSame('10:30:45', $decoded['time']);
        $this->assertSame('2024-03-15 10:30:45', $decoded['timestamp']);
    }
}
