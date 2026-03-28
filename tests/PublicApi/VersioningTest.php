<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\PublicApi;

use PhpCollective\Toml\Encoder\DocumentFormattingMode;
use PhpCollective\Toml\Encoder\EncoderOptions;
use PhpCollective\Toml\Exception\EncodeException;
use PhpCollective\Toml\Exception\ParseException;
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\TomlVersion;
use PhpCollective\Toml\Value\LocalDateTime;
use PhpCollective\Toml\Value\LocalTime;
use PHPUnit\Framework\TestCase;

final class VersioningTest extends TestCase
{
    public function testDecodeDefaultsToToml11Behavior(): void
    {
        $decoded = Toml::decode("time = 07:32\nescaped = \"\\x41\"\n");

        $this->assertSame([
            'time' => '07:32',
            'escaped' => 'A',
        ], $decoded);
    }

    public function testDecodeRejectsToml11ByteEscapeInToml10Mode(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid token');

        Toml::decode("escaped = \"\\x41\"\n", TomlVersion::V10);
    }

    public function testDecodeRejectsOptionalSecondsInToml10Mode(): void
    {
        $this->expectException(ParseException::class);
        $this->expectExceptionMessage('Invalid token');

        Toml::decode("time = 07:32\n", TomlVersion::V10);
    }

    public function testTryParseRejectsInlineTableTrailingCommaInToml10Mode(): void
    {
        $result = Toml::tryParse('point = { x = 1, }', TomlVersion::V10);

        $this->assertFalse($result->isValid());
        $this->assertSame('Inline table trailing commas require TOML 1.1', $result->getErrors()[0]->message);
    }

    public function testTryParseRejectsMultilineInlineTableInToml10Mode(): void
    {
        $result = Toml::tryParse("point = {\n  x = 1,\n}\n", TomlVersion::V10);

        $this->assertFalse($result->isValid());
        $this->assertSame('Multiline inline tables require TOML 1.1', $result->getErrors()[0]->message);
    }

    public function testEncodeNormalizesLocalTimeForToml10Mode(): void
    {
        $encoded = Toml::encode([
            'time' => new LocalTime('07:32'),
        ], new EncoderOptions(version: TomlVersion::V10));

        $this->assertSame('time = 07:32:00', $encoded);
    }

    public function testEncodeNormalizesLocalDateTimeForToml10Mode(): void
    {
        $encoded = Toml::encode([
            'published' => new LocalDateTime('2026-03-28T07:32'),
        ], new EncoderOptions(version: TomlVersion::V10));

        $this->assertSame('published = 2026-03-28T07:32:00', $encoded);
    }

    public function testSourceAwareEncodeDocumentRejectsToml11InlineTableSyntaxInToml10Mode(): void
    {
        $document = Toml::parse('point = { x = 1, }', true);

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('inline table trailing commas');

        Toml::encodeDocument(
            $document,
            new EncoderOptions(
                documentFormatting: DocumentFormattingMode::SourceAware,
                version: TomlVersion::V10,
            ),
        );
    }

    public function testSourceAwareEncodeDocumentRejectsToml11TimeSyntaxInToml10Mode(): void
    {
        $document = Toml::parse("time = 07:32\n", true);

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('local times without seconds');

        Toml::encodeDocument(
            $document,
            new EncoderOptions(
                documentFormatting: DocumentFormattingMode::SourceAware,
                version: TomlVersion::V10,
            ),
        );
    }
}
