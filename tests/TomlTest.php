<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test;

use PhpCollective\Toml\Ast\Document;
use PhpCollective\Toml\Encoder\DocumentFormattingMode;
use PhpCollective\Toml\Encoder\EncoderOptions;
use PhpCollective\Toml\Exception\EncodeException;
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

    public function testEncodeDocumentWithSourceAwareOption(): void
    {
        $doc = Toml::parse("original   =   \"value\"\n", true);
        $result = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame("original   =   \"value\"\n", $result);
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
        // 1__2 is not a valid number (double underscore), and not valid in value position
        $this->expectExceptionMessage('Expected value');

        Toml::decode("n = 1__2\n");
    }

    public function testDecodeRejectsInvalidOffsetDateTime(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid token');

        Toml::decode("d = 2024-01-01T00:00:00+99:00\n");
    }

    public function testDecodeRejectsSignedIntegerKey(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Expected key');

        Toml::decode("+1 = 2\n");
    }

    public function testDecodeRejectsSignedFloatLikeKey(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Expected key');

        Toml::decode("+1.2 = 3\n");
    }

    public function testDecodeRejectsBareCrInMultilineBasicStringContinuation(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid token');

        Toml::decode("x = \"\"\"a\\\rb\"\"\"\n");
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

    public function testDecodeRejectsContentAfterTableHeader(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Expected newline or comment after table header');

        // TOML spec: table headers must be on a line by themselves
        Toml::decode("[table] key = \"value\"\n");
    }

    public function testDecodeRejectsContentAfterArrayTableHeader(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Expected newline or comment after table header');

        Toml::decode("[[array]] key = \"value\"\n");
    }

    public function testDecodeAcceptsCommentAfterTableHeader(): void
    {
        // Comments after table headers are valid
        $result = Toml::decode("[table] # this is a comment\nkey = \"value\"\n");

        $this->assertSame([
            'table' => [
                'key' => 'value',
            ],
        ], $result);
    }

    public function testDecodeAcceptsWhitespaceBeforeCommentAfterTableHeader(): void
    {
        $result = Toml::decode("[table]   # comment with leading spaces\nkey = \"value\"\n");

        $this->assertSame([
            'table' => [
                'key' => 'value',
            ],
        ], $result);
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

    public function testEncodeFile(): void
    {
        $path = sys_get_temp_dir() . '/toml_test_' . uniqid() . '.toml';

        try {
            Toml::encodeFile($path, ['name' => 'test', 'count' => 42]);

            $this->assertFileExists($path);
            $content = file_get_contents($path);
            $this->assertIsString($content);
            $this->assertStringContainsString('name = "test"', $content);
            $this->assertStringContainsString('count = 42', $content);

            // Verify round-trip
            $decoded = Toml::decodeFile($path);
            $this->assertSame(['name' => 'test', 'count' => 42], $decoded);
        } finally {
            @unlink($path);
        }
    }

    public function testEncodeFileWithOptions(): void
    {
        $path = sys_get_temp_dir() . '/toml_test_' . uniqid() . '.toml';

        try {
            Toml::encodeFile($path, ['zebra' => 1, 'apple' => 2], new EncoderOptions(sortKeys: true));

            $content = file_get_contents($path);
            $this->assertIsString($content);
            $applePos = strpos($content, 'apple');
            $zebraPos = strpos($content, 'zebra');

            $this->assertLessThan($zebraPos, $applePos);
        } finally {
            @unlink($path);
        }
    }

    public function testEncodeFileThrowsOnInvalidPath(): void
    {
        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('Cannot write file');

        Toml::encodeFile('/nonexistent/directory/file.toml', ['key' => 'value']);
    }

    public function testEncodeDocumentFile(): void
    {
        $path = sys_get_temp_dir() . '/toml_test_' . uniqid() . '.toml';

        try {
            $doc = Toml::parse("title = \"Example\"\ncount = 10\n", true);
            Toml::encodeDocumentFile($path, $doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

            $this->assertFileExists($path);
            $content = file_get_contents($path);
            $this->assertSame("title = \"Example\"\ncount = 10\n", $content);
        } finally {
            @unlink($path);
        }
    }

    public function testEncodeDocumentFileThrowsOnInvalidPath(): void
    {
        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('Cannot write file');

        $doc = Toml::parse('key = "value"');
        Toml::encodeDocumentFile('/nonexistent/directory/file.toml', $doc);
    }

    public function testEncodeDocumentRejectsSemanticallyInvalidAstInNormalizedMode(): void
    {
        $doc = Toml::parse("a = 1\n");
        $doc->items[] = clone $doc->items[0];

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage("Duplicate key 'a'");

        Toml::encodeDocument($doc);
    }

    public function testEncodeDocumentRejectsSemanticallyInvalidAstInSourceAwareMode(): void
    {
        $doc = Toml::parse("a = 1\n", true);
        $doc->items[] = clone $doc->items[0];

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage("Duplicate key 'a'");

        Toml::encodeDocument(
            $doc,
            new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware),
        );
    }
}
