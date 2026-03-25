<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Lexer;

use DateTimeImmutable;
use PhpCollective\Toml\Lexer\Lexer;
use PhpCollective\Toml\Lexer\TokenType;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    public function testStructuralTokens(): void
    {
        $lexer = new Lexer('[]={}.,');
        $tokens = iterator_to_array($lexer->tokenize());

        $types = array_map(fn ($t) => $t->type, $tokens);
        $this->assertSame([
            TokenType::LeftBracket,
            TokenType::RightBracket,
            TokenType::Equals,
            TokenType::LeftBrace,
            TokenType::RightBrace,
            TokenType::Dot,
            TokenType::Comma,
            TokenType::Eof,
        ], $types);
    }

    public function testBareKey(): void
    {
        $lexer = new Lexer('key_name-123');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::BareKey, $tokens[0]->type);
        $this->assertSame('key_name-123', $tokens[0]->value);
    }

    public function testBoolean(): void
    {
        $lexer = new Lexer('true false');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::Boolean, $tokens[0]->type);
        $this->assertTrue($tokens[0]->parsed);
        $this->assertSame(TokenType::Boolean, $tokens[2]->type);
        $this->assertFalse($tokens[2]->parsed);
    }

    public function testInteger(): void
    {
        $lexer = new Lexer('42 +17 -5 1_000');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(42, $tokens[0]->parsed);
        $this->assertSame(17, $tokens[2]->parsed);
        $this->assertSame(-5, $tokens[4]->parsed);
        $this->assertSame(1000, $tokens[6]->parsed);
    }

    public function testHexOctalBinary(): void
    {
        $lexer = new Lexer('0xDEAD 0o755 0b1010');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(0xDEAD, $tokens[0]->parsed);
        $this->assertSame(0755, $tokens[2]->parsed);
        $this->assertSame(0b1010, $tokens[4]->parsed);
    }

    public function testNegativeOctalAndBinary(): void
    {
        $lexer = new Lexer('-0o777 -0b1010');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(-0o777, $tokens[0]->parsed);
        $this->assertSame(-0b1010, $tokens[2]->parsed);
    }

    public function testFloat(): void
    {
        $lexer = new Lexer('3.14 -0.5 1e10 6.02e+23');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(3.14, $tokens[0]->parsed);
        $this->assertSame(-0.5, $tokens[2]->parsed);
        $this->assertSame(1e10, $tokens[4]->parsed);
        $this->assertSame(6.02e+23, $tokens[6]->parsed);
    }

    public function testBasicString(): void
    {
        $lexer = new Lexer('"hello world"');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::BasicString, $tokens[0]->type);
        $this->assertSame('hello world', $tokens[0]->parsed);
    }

    public function testBasicStringEscapes(): void
    {
        $lexer = new Lexer('"line1\\nline2\\ttab"');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame("line1\nline2\ttab", $tokens[0]->parsed);
    }

    public function testUnicodeEscape(): void
    {
        $lexer = new Lexer('"\\u0041\\U0001F600"');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame('A😀', $tokens[0]->parsed);
    }

    public function testHexEscape(): void
    {
        $lexer = new Lexer('"\\x41\\x42"');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame('AB', $tokens[0]->parsed);
    }

    public function testLiteralString(): void
    {
        $lexer = new Lexer("'no\\\\escapes'");
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::LiteralString, $tokens[0]->type);
        $this->assertSame('no\\\\escapes', $tokens[0]->parsed);
    }

    public function testMultiLineBasicString(): void
    {
        $input = '"""
line1
line2"""';
        $lexer = new Lexer($input);
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::MultiLineBasicString, $tokens[0]->type);
        $this->assertSame("line1\nline2", $tokens[0]->parsed);
    }

    public function testMultiLineLiteralString(): void
    {
        $input = "'''
no\\\\escape
here'''";
        $lexer = new Lexer($input);
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::MultiLineLiteralString, $tokens[0]->type);
        $this->assertSame("no\\\\escape\nhere", $tokens[0]->parsed);
    }

    public function testMultiLineStringWithTrailingQuotes(): void
    {
        // TOML allows up to 2 quotes before the closing delimiter
        // 5 quotes = 2 content quotes + 3 closing quotes
        $lexer = new Lexer('"""test"""""');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::MultiLineBasicString, $tokens[0]->type);
        $this->assertSame('test""', $tokens[0]->parsed);

        // Same for literal strings
        $lexer = new Lexer("'''test'''''");
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::MultiLineLiteralString, $tokens[0]->type);
        $this->assertSame("test''", $tokens[0]->parsed);

        // 4 quotes = 1 content quote + 3 closing quotes
        $lexer = new Lexer('"""test""""');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::MultiLineBasicString, $tokens[0]->type);
        $this->assertSame('test"', $tokens[0]->parsed);
    }

    public function testOffsetDateTime(): void
    {
        $lexer = new Lexer('1979-05-27T07:32:00Z');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::OffsetDateTime, $tokens[0]->type);
        $this->assertInstanceOf(DateTimeImmutable::class, $tokens[0]->parsed);
    }

    public function testLocalDateTime(): void
    {
        $lexer = new Lexer('1979-05-27T07:32:00');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::LocalDateTime, $tokens[0]->type);
    }

    public function testLocalDate(): void
    {
        $lexer = new Lexer('1979-05-27');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::LocalDate, $tokens[0]->type);
    }

    public function testLocalTime(): void
    {
        $lexer = new Lexer('07:32:00');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::LocalTime, $tokens[0]->type);
    }

    public function testOptionalSeconds(): void
    {
        // TOML 1.1 feature
        $lexer = new Lexer('07:32');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::LocalTime, $tokens[0]->type);
    }

    public function testInvalidFloatMissingFraction(): void
    {
        // "1." is not a valid float in TOML - must have digits after decimal
        $lexer = new Lexer('n = 1.');
        $tokens = iterator_to_array($lexer->tokenize());

        // Should be recognized as invalid
        $valueToken = $tokens[4]; // n, ws, =, ws, value
        $this->assertSame(TokenType::Invalid, $valueToken->type);
    }

    public function testValidFloatWithFraction(): void
    {
        $lexer = new Lexer('1.0');
        $tokens = iterator_to_array($lexer->tokenize());

        $this->assertSame(TokenType::Float, $tokens[0]->type);
        $this->assertSame(1.0, $tokens[0]->parsed);
    }

    public function testSpecialFloats(): void
    {
        // Unsigned inf/nan are bare keys (parser interprets as float in value position)
        // Signed versions are Float tokens
        $lexer = new Lexer('inf -inf nan +nan');
        $tokens = iterator_to_array($lexer->tokenize());

        // Unsigned inf is a bare key
        $this->assertSame(TokenType::BareKey, $tokens[0]->type);
        $this->assertSame('inf', $tokens[0]->value);

        // Signed -inf is a float
        $this->assertSame(TokenType::Float, $tokens[2]->type);
        $this->assertSame(-INF, $tokens[2]->parsed);

        // Unsigned nan is a bare key
        $this->assertSame(TokenType::BareKey, $tokens[4]->type);
        $this->assertSame('nan', $tokens[4]->value);

        // Signed +nan is a float
        $this->assertSame(TokenType::Float, $tokens[6]->type);
        $this->assertTrue(is_nan($tokens[6]->parsed));
    }
}
