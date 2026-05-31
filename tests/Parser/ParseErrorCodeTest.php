<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Parser;

use PhpCollective\Toml\Exception\ParseException;
use PhpCollective\Toml\Parser\ParseErrorCode;
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\TomlVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParseErrorCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, \PhpCollective\Toml\Parser\ParseErrorCode}>
     */
    public static function codeProvider(): iterable
    {
        yield 'integer overflow' => ['x = 9223372036854775808', ParseErrorCode::IntegerOutOfRange];
        yield 'unterminated string' => ['x = "abc', ParseErrorCode::UnterminatedString];
        yield 'invalid escape' => ['x = "\q"', ParseErrorCode::InvalidToken];
        yield 'invalid datetime' => ['x = 2024-13-45', ParseErrorCode::InvalidDateTime];
        yield 'expected value' => ['x =', ParseErrorCode::ExpectedValue];
        yield 'missing equals' => ['x 1', ParseErrorCode::ExpectedToken];
        yield 'unexpected token' => ['} = 1', ParseErrorCode::UnexpectedToken];
        yield 'duplicate key' => ["a = 1\na = 2", ParseErrorCode::DuplicateKey];
        yield 'duplicate table' => ["[t]\n[t]", ParseErrorCode::DuplicateTable];
        yield 'key redefinition' => ["a = 1\n[a]\nb = 2", ParseErrorCode::KeyRedefinition];
        yield 'extend inline table' => ["a = {x = 1}\na.y = 2", ParseErrorCode::ExtendInlineTable];
    }

    #[DataProvider('codeProvider')]
    public function testParseAssignsStableErrorCode(string $toml, ParseErrorCode $expected): void
    {
        $result = Toml::tryParse($toml);
        $errors = $result->getErrors();

        $this->assertNotEmpty($errors);
        $this->assertSame($expected, $errors[0]->code, $errors[0]->message);
    }

    public function testDecodeExceptionCarriesErrorCode(): void
    {
        try {
            Toml::decode("a = 1\na = 2");
            $this->fail('Expected ParseException');
        } catch (ParseException $exception) {
            $this->assertSame(ParseErrorCode::DuplicateKey, $exception->errorCode);
        }
    }

    public function testStrictVersionErrorIsCoded(): void
    {
        $result = Toml::tryParse('point = { x = 1, }', TomlVersion::V10);

        $this->assertNotEmpty($result->getErrors());
        $this->assertSame(ParseErrorCode::UnsupportedVersion, $result->getErrors()[0]->code);
    }

    public function testFromMessageFallsBackToSyntaxError(): void
    {
        $this->assertSame(ParseErrorCode::SyntaxError, ParseErrorCode::fromMessage('something unmapped'));
    }

    public function testFromInvalidLexemeDistinguishesUnterminatedFromClosed(): void
    {
        $this->assertSame(ParseErrorCode::UnterminatedString, ParseErrorCode::fromInvalidLexeme('"abc'));
        $this->assertSame(ParseErrorCode::InvalidToken, ParseErrorCode::fromInvalidLexeme('"\q"'));
        $this->assertSame(ParseErrorCode::IntegerOutOfRange, ParseErrorCode::fromInvalidLexeme('9223372036854775808'));
    }

    public function testMalformedDecimalIsNotReportedAsOverflow(): void
    {
        // Leading-zero and bad-underscore integers are syntax errors, not overflow.
        $this->assertSame(ParseErrorCode::InvalidNumber, ParseErrorCode::fromInvalidLexeme('+01'));
        $this->assertSame(ParseErrorCode::InvalidNumber, ParseErrorCode::fromInvalidLexeme('-01'));
        $this->assertSame(ParseErrorCode::InvalidNumber, ParseErrorCode::fromInvalidLexeme('+1__2'));
    }
}
