<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Lexer;

use DateTimeImmutable;
use Generator;
use PhpCollective\Toml\Support\TemporalValidator;

final class Lexer
{
    private int $pos = 0;

    private int $line = 1;

    private int $column = 0;

    private int $length;

    public function __construct(private readonly string $input)
    {
        $this->length = strlen($input);
    }

    /**
     * @return \Generator<\PhpCollective\Toml\Lexer\Token>
     */
    public function tokenize(): Generator
    {
        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            yield match ($char) {
                '[' => $this->single(TokenType::LeftBracket),
                ']' => $this->single(TokenType::RightBracket),
                '{' => $this->single(TokenType::LeftBrace),
                '}' => $this->single(TokenType::RightBrace),
                '=' => $this->single(TokenType::Equals),
                ',' => $this->single(TokenType::Comma),
                '.' => $this->single(TokenType::Dot),
                "\n" => $this->newline(),
                '#' => $this->comment(),
                '"' => $this->string(),
                "'" => $this->literalString(),
                ' ', "\t" => $this->whitespace(),
                "\r" => $this->skipCr(),
                default => $this->keyOrValue(),
            };
        }

        yield new Token(
            TokenType::Eof,
            '',
            null,
            new Span($this->pos, $this->pos, $this->line, $this->column),
        );
    }

    private function single(TokenType $type): Token
    {
        $start = $this->pos;
        $col = $this->column;
        $char = $this->input[$this->pos];
        $this->advance();

        return new Token($type, $char, $char, new Span($start, $this->pos, $this->line, $col));
    }

    private function advance(): void
    {
        $this->pos++;
        $this->column++;
    }

    private function newline(): Token
    {
        $start = $this->pos;
        $col = $this->column;
        $this->pos++;
        $line = $this->line;
        $this->line++;
        $this->column = 0;

        return new Token(TokenType::Newline, "\n", "\n", new Span($start, $this->pos, $line, $col));
    }

    private function skipCr(): Token
    {
        $this->pos++;
        if ($this->pos < $this->length && $this->input[$this->pos] === "\n") {
            return $this->newline();
        }

        return new Token(
            TokenType::Invalid,
            "\r",
            null,
            new Span($this->pos - 1, $this->pos, $this->line, $this->column),
        );
    }

    private function comment(): Token
    {
        $start = $this->pos;
        $col = $this->column;
        $this->advance(); // skip #

        while ($this->pos < $this->length && $this->input[$this->pos] !== "\n") {
            $this->advance();
        }

        $value = substr($this->input, $start, $this->pos - $start);

        return new Token(TokenType::Comment, $value, $value, new Span($start, $this->pos, $this->line, $col));
    }

    private function whitespace(): Token
    {
        $start = $this->pos;
        $col = $this->column;

        while ($this->pos < $this->length && ($this->input[$this->pos] === ' ' || $this->input[$this->pos] === "\t")) {
            $this->advance();
        }

        $value = substr($this->input, $start, $this->pos - $start);

        return new Token(TokenType::Whitespace, $value, $value, new Span($start, $this->pos, $this->line, $col));
    }

    private function string(): Token
    {
        $start = $this->pos;
        $col = $this->column;

        // Check for multi-line
        if (
            $this->pos + 2 < $this->length &&
            $this->input[$this->pos + 1] === '"' &&
            $this->input[$this->pos + 2] === '"'
        ) {
            return $this->multiLineBasicString();
        }

        $this->advance(); // skip opening "

        $parsed = '';
        $valid = true;
        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if ($char === '"') {
                $this->advance();
                $value = substr($this->input, $start, $this->pos - $start);

                if (!$valid) {
                    return new Token(TokenType::Invalid, $value, null, new Span($start, $this->pos, $this->line, $col));
                }

                return new Token(TokenType::BasicString, $value, $parsed, new Span($start, $this->pos, $this->line, $col));
            }

            if ($char === '\\') {
                $escaped = $this->parseEscape();
                if ($escaped === null) {
                    $valid = false;

                    continue;
                }
                $parsed .= $escaped;
            } elseif ($char === "\n") {
                // Unescaped newline not allowed in basic string
                return new Token(TokenType::Invalid, substr($this->input, $start, $this->pos - $start), null, new Span($start, $this->pos, $this->line, $col));
            } else {
                $parsed .= $char;
                $this->advance();
            }
        }

        // Unterminated string
        return new Token(TokenType::Invalid, substr($this->input, $start), null, new Span($start, $this->pos, $this->line, $col));
    }

    private function parseEscape(): ?string
    {
        $this->advance(); // skip \

        if ($this->pos >= $this->length) {
            return null;
        }

        $char = $this->input[$this->pos];
        $this->advance();

        return match ($char) {
            'b' => "\x08",
            't' => "\t",
            'n' => "\n",
            'f' => "\x0C",
            'r' => "\r",
            '"' => '"',
            '\\' => '\\',
            'e' => "\x1B", // TOML 1.1
            'x' => $this->parseHexEscape(2), // TOML 1.1
            'u' => $this->parseHexEscape(4),
            'U' => $this->parseHexEscape(8),
            default => null,
        };
    }

    private function parseHexEscape(int $length): ?string
    {
        $hex = '';
        for ($i = 0; $i < $length && $this->pos < $this->length; $i++) {
            $hex .= $this->input[$this->pos];
            $this->advance();
        }

        if (strlen($hex) !== $length || !ctype_xdigit($hex)) {
            return null;
        }

        $codepoint = (int)hexdec($hex);

        if (($codepoint >= 0xD800 && $codepoint <= 0xDFFF) || $codepoint > 0x10FFFF) {
            return null;
        }

        return mb_chr($codepoint, 'UTF-8') ?: null;
    }

    private function multiLineBasicString(): Token
    {
        $start = $this->pos;
        $col = $this->column;
        $startLine = $this->line;

        $this->advance(); // skip first "
        $this->advance(); // skip second "
        $this->advance(); // skip third "

        // Skip immediate newline after opening
        if ($this->pos < $this->length && $this->input[$this->pos] === "\n") {
            $this->pos++;
            $this->line++;
            $this->column = 0;
        } elseif ($this->pos < $this->length && $this->input[$this->pos] === "\r") {
            $this->pos++;
            if ($this->pos < $this->length && $this->input[$this->pos] === "\n") {
                $this->pos++;
            }
            $this->line++;
            $this->column = 0;
        }

        $parsed = '';
        $valid = true;
        while ($this->pos < $this->length) {
            if (
                $this->pos + 2 < $this->length &&
                $this->input[$this->pos] === '"' &&
                $this->input[$this->pos + 1] === '"' &&
                $this->input[$this->pos + 2] === '"'
            ) {
                $this->advance();
                $this->advance();
                $this->advance();
                $value = substr($this->input, $start, $this->pos - $start);

                if (!$valid) {
                    return new Token(TokenType::Invalid, $value, null, new Span($start, $this->pos, $startLine, $col));
                }

                return new Token(TokenType::MultiLineBasicString, $value, $parsed, new Span($start, $this->pos, $startLine, $col));
            }

            $char = $this->input[$this->pos];

            if ($char === '\\') {
                // Check for line-ending backslash
                $this->advance();
                if ($this->pos < $this->length && ($this->input[$this->pos] === "\n" || $this->input[$this->pos] === "\r")) {
                    $this->skipWhitespaceAndNewlines();
                } else {
                    $this->pos--; // go back
                    $this->column--;
                    $escaped = $this->parseEscape();
                    if ($escaped === null) {
                        $valid = false;

                        continue;
                    }
                    $parsed .= $escaped;
                }
            } elseif ($char === "\n") {
                $parsed .= $char;
                $this->pos++;
                $this->line++;
                $this->column = 0;
            } elseif ($char === "\r") {
                $this->pos++;
                if ($this->pos < $this->length && $this->input[$this->pos] === "\n") {
                    $parsed .= "\n";
                    $this->pos++;
                }
                $this->line++;
                $this->column = 0;
            } else {
                $parsed .= $char;
                $this->advance();
            }
        }

        return new Token(TokenType::Invalid, substr($this->input, $start), null, new Span($start, $this->pos, $startLine, $col));
    }

    private function literalString(): Token
    {
        $start = $this->pos;
        $col = $this->column;
        $startLine = $this->line;

        // Check for multi-line
        if (
            $this->pos + 2 < $this->length &&
            $this->input[$this->pos + 1] === "'" &&
            $this->input[$this->pos + 2] === "'"
        ) {
            return $this->multiLineLiteralString();
        }

        $this->advance(); // skip opening '

        $parsed = '';
        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if ($char === "'") {
                $this->advance();
                $value = substr($this->input, $start, $this->pos - $start);

                return new Token(TokenType::LiteralString, $value, $parsed, new Span($start, $this->pos, $startLine, $col));
            }

            if ($char === "\n") {
                return new Token(TokenType::Invalid, substr($this->input, $start, $this->pos - $start), null, new Span($start, $this->pos, $startLine, $col));
            }

            $parsed .= $char;
            $this->advance();
        }

        return new Token(TokenType::Invalid, substr($this->input, $start), null, new Span($start, $this->pos, $startLine, $col));
    }

    private function multiLineLiteralString(): Token
    {
        $start = $this->pos;
        $col = $this->column;
        $startLine = $this->line;

        $this->advance();
        $this->advance();
        $this->advance();

        // Skip immediate newline
        if ($this->pos < $this->length && $this->input[$this->pos] === "\n") {
            $this->pos++;
            $this->line++;
            $this->column = 0;
        } elseif ($this->pos < $this->length && $this->input[$this->pos] === "\r") {
            $this->pos++;
            if ($this->pos < $this->length && $this->input[$this->pos] === "\n") {
                $this->pos++;
            }
            $this->line++;
            $this->column = 0;
        }

        $parsed = '';
        while ($this->pos < $this->length) {
            if (
                $this->pos + 2 < $this->length &&
                $this->input[$this->pos] === "'" &&
                $this->input[$this->pos + 1] === "'" &&
                $this->input[$this->pos + 2] === "'"
            ) {
                $this->advance();
                $this->advance();
                $this->advance();
                $value = substr($this->input, $start, $this->pos - $start);

                return new Token(TokenType::MultiLineLiteralString, $value, $parsed, new Span($start, $this->pos, $startLine, $col));
            }

            $char = $this->input[$this->pos];
            if ($char === "\n") {
                $parsed .= $char;
                $this->pos++;
                $this->line++;
                $this->column = 0;
            } elseif ($char === "\r") {
                $this->pos++;
                if ($this->pos < $this->length && $this->input[$this->pos] === "\n") {
                    $parsed .= "\n";
                    $this->pos++;
                }
                $this->line++;
                $this->column = 0;
            } else {
                $parsed .= $char;
                $this->advance();
            }
        }

        return new Token(TokenType::Invalid, substr($this->input, $start), null, new Span($start, $this->pos, $startLine, $col));
    }

    private function skipWhitespaceAndNewlines(): void
    {
        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];
            if ($char === ' ' || $char === "\t") {
                $this->advance();
            } elseif ($char === "\n") {
                $this->pos++;
                $this->line++;
                $this->column = 0;
            } elseif ($char === "\r") {
                $this->pos++;
            } else {
                break;
            }
        }
    }

    private function keyOrValue(): Token
    {
        $start = $this->pos;
        $col = $this->column;
        $char = $this->input[$this->pos];

        // Numbers can start with digit, +, or -
        if (ctype_digit($char)) {
            return $this->number();
        }
        if (($char === '+' || $char === '-') && $this->pos + 1 < $this->length) {
            $next = $this->input[$this->pos + 1];
            // Check for number or special float (inf, nan)
            if (ctype_digit($next) || $next === 'i' || $next === 'n') {
                return $this->number();
            }
        }

        // Bare key
        while ($this->pos < $this->length && $this->isBareKeyChar($this->input[$this->pos])) {
            $this->advance();
        }

        if ($this->pos === $start) {
            $this->advance();

            return new Token(TokenType::Invalid, $char, null, new Span($start, $this->pos, $this->line, $col));
        }

        $value = substr($this->input, $start, $this->pos - $start);
        $span = new Span($start, $this->pos, $this->line, $col);

        // Check for boolean
        if ($value === 'true') {
            return new Token(TokenType::Boolean, $value, true, $span);
        }
        if ($value === 'false') {
            return new Token(TokenType::Boolean, $value, false, $span);
        }

        // Check for special floats (without sign)
        if ($value === 'inf') {
            return new Token(TokenType::Float, $value, INF, $span);
        }
        if ($value === 'nan') {
            return new Token(TokenType::Float, $value, NAN, $span);
        }

        return new Token(TokenType::BareKey, $value, $value, $span);
    }

    private function number(): Token
    {
        $start = $this->pos;
        $col = $this->column;

        // Read ahead to detect datetime patterns (before processing signs)
        $lookahead = '';
        $tempPos = $this->pos;
        while ($tempPos < $this->length && $this->isDateTimeChar($this->input[$tempPos])) {
            $lookahead .= $this->input[$tempPos];
            $tempPos++;
        }

        // Check for datetime patterns: YYYY-MM-DD or HH:MM
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $lookahead)) {
            return $this->dateTime();
        }
        if (preg_match('/^\d{2}:\d{2}/', $lookahead)) {
            return $this->time();
        }

        // Handle sign
        $sign = '';
        if ($this->input[$this->pos] === '+' || $this->input[$this->pos] === '-') {
            $sign = $this->input[$this->pos];
            $this->advance();
        }

        // Check for special floats after sign
        if ($this->pos < $this->length) {
            $remaining = substr($this->input, $this->pos, 3);
            if ($remaining === 'inf') {
                $this->advance();
                $this->advance();
                $this->advance();
                $value = substr($this->input, $start, $this->pos - $start);

                return new Token(TokenType::Float, $value, $sign === '-' ? -INF : INF, new Span($start, $this->pos, $this->line, $col));
            }
            if ($remaining === 'nan') {
                $this->advance();
                $this->advance();
                $this->advance();
                $value = substr($this->input, $start, $this->pos - $start);

                return new Token(TokenType::Float, $value, NAN, new Span($start, $this->pos, $this->line, $col));
            }
        }

        // Read the rest of the number
        while ($this->pos < $this->length && $this->isNumberChar($this->input[$this->pos])) {
            $this->advance();
        }

        $value = substr($this->input, $start, $this->pos - $start);

        return $this->classifyNumber($value, new Span($start, $this->pos, $this->line, $col));
    }

    private function isDateTimeChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '-' || $char === ':' || $char === '.' || $char === '+' || $char === 'T' || $char === 't' || $char === 'Z' || $char === ' ';
    }

    private function dateTime(): Token
    {
        $start = $this->pos;
        $col = $this->column;

        // Read the full datetime
        while ($this->pos < $this->length && $this->isDateTimeChar($this->input[$this->pos])) {
            // Stop at space if followed by non-digit (not a datetime with space separator)
            if ($this->input[$this->pos] === ' ') {
                if ($this->pos + 1 >= $this->length || !ctype_digit($this->input[$this->pos + 1])) {
                    break;
                }
            }
            $this->advance();
        }

        $value = substr($this->input, $start, $this->pos - $start);
        $span = new Span($start, $this->pos, $this->line, $col);

        // Classify: offset datetime, local datetime, or local date
        if (preg_match('/[Zz]$|[+-]\d{2}:\d{2}$/', $value)) {
            if (!$this->isValidOffsetDateTime($value)) {
                return new Token(TokenType::Invalid, $value, null, $span);
            }

            $parsed = new DateTimeImmutable($value);

            return new Token(TokenType::OffsetDateTime, $value, $parsed, $span);
        }

        if (str_contains($value, 'T') || str_contains($value, 't') || str_contains($value, ' ')) {
            if (!$this->isValidLocalDateTime($value)) {
                return new Token(TokenType::Invalid, $value, null, $span);
            }

            return new Token(TokenType::LocalDateTime, $value, $value, $span);
        }

        if (!$this->isValidLocalDate($value)) {
            return new Token(TokenType::Invalid, $value, null, $span);
        }

        return new Token(TokenType::LocalDate, $value, $value, $span);
    }

    private function time(): Token
    {
        $start = $this->pos;
        $col = $this->column;

        while ($this->pos < $this->length && $this->isTimeChar($this->input[$this->pos])) {
            $this->advance();
        }

        $value = substr($this->input, $start, $this->pos - $start);

        if (!$this->isValidLocalTime($value)) {
            return new Token(TokenType::Invalid, $value, null, new Span($start, $this->pos, $this->line, $col));
        }

        return new Token(TokenType::LocalTime, $value, $value, new Span($start, $this->pos, $this->line, $col));
    }

    private function isTimeChar(string $char): bool
    {
        return ctype_digit($char) || $char === ':' || $char === '.';
    }

    private function classifyNumber(string $value, Span $span): Token
    {
        if (!$this->isValidNumberLiteral($value)) {
            return new Token(TokenType::Invalid, $value, null, $span);
        }

        $clean = str_replace('_', '', $value);

        // Hex
        if (
            str_starts_with($clean, '0x') || str_starts_with($clean, '0X') ||
            str_starts_with($clean, '+0x') || str_starts_with($clean, '-0x')
        ) {
            $parsed = intval($clean, 16);

            return new Token(TokenType::Integer, $value, $parsed, $span);
        }

        // Octal
        if (
            str_starts_with($clean, '0o') || str_starts_with($clean, '0O') ||
            str_starts_with($clean, '+0o') || str_starts_with($clean, '-0o')
        ) {
            $hex = str_replace(['0o', '0O'], '', $clean);
            $parsed = intval($hex, 8);
            if (str_starts_with($clean, '-')) {
                $parsed = -$parsed;
            }

            return new Token(TokenType::Integer, $value, $parsed, $span);
        }

        // Binary
        if (
            str_starts_with($clean, '0b') || str_starts_with($clean, '0B') ||
            str_starts_with($clean, '+0b') || str_starts_with($clean, '-0b')
        ) {
            $bin = str_replace(['0b', '0B', '+', '-'], '', $clean);
            $parsed = bindec($bin);
            if (str_starts_with($clean, '-')) {
                $parsed = -$parsed;
            }

            return new Token(TokenType::Integer, $value, (int)$parsed, $span);
        }

        // Float (has . or e/E)
        if (str_contains($clean, '.') || str_contains(strtolower($clean), 'e')) {
            return new Token(TokenType::Float, $value, (float)$clean, $span);
        }

        // Plain integer
        return new Token(TokenType::Integer, $value, (int)$clean, $span);
    }

    private function isNumberChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '.' || $char === '+' || $char === '-';
    }

    private function isValidNumberLiteral(string $value): bool
    {
        // Integer: decimal
        if (preg_match('/^[+-]?(?:0|[1-9](?:_?\d)*)$/', $value) === 1) {
            return true;
        }
        // Integer: hexadecimal
        if (preg_match('/^[+-]?0[xX][0-9A-Fa-f](?:_?[0-9A-Fa-f])*$/', $value) === 1) {
            return true;
        }
        // Integer: octal
        if (preg_match('/^[+-]?0[oO][0-7](?:_?[0-7])*$/', $value) === 1) {
            return true;
        }
        // Integer: binary
        if (preg_match('/^[+-]?0[bB][01](?:_?[01])*$/', $value) === 1) {
            return true;
        }
        // Float with decimal point (requires digit after decimal)
        if (preg_match('/^[+-]?(?:0|[1-9](?:_?\d)*)\.(?:\d(?:_?\d)*)(?:[eE][+-]?\d(?:_?\d)*)?$/', $value) === 1) {
            return true;
        }
        // Float with exponent only (no decimal point)
        if (preg_match('/^[+-]?\d(?:_?\d)*[eE][+-]?\d(?:_?\d)*$/', $value) === 1) {
            return true;
        }

        return false;
    }

    private function isValidOffsetDateTime(string $value): bool
    {
        return TemporalValidator::isValidOffsetDateTime($value);
    }

    private function isValidLocalDateTime(string $value): bool
    {
        return TemporalValidator::isValidLocalDateTime($value);
    }

    private function isValidLocalDate(string $value): bool
    {
        return TemporalValidator::isValidLocalDate($value);
    }

    private function isValidLocalTime(string $value): bool
    {
        return TemporalValidator::isValidLocalTime($value);
    }

    private function isBareKeyChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-';
    }
}
