<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Lexer;

use DateTimeImmutable;
use Generator;
use PhpCollective\Toml\Support\TemporalValidator;
use PhpCollective\Toml\TomlVersion;

final class Lexer
{
    private int $pos = 0;

    private int $line = 1;

    private int $column = 0;

    private int $length;

    public function __construct(
        private readonly string $input,
        private readonly TomlVersion $version = TomlVersion::V11,
    ) {
        $this->length = strlen($input);
    }

    /**
     * @return \Generator<\PhpCollective\Toml\Lexer\Token>
     */
    public function tokenize(): Generator
    {
        // Validate UTF-8 encoding upfront
        if (!mb_check_encoding($this->input, 'UTF-8')) {
            yield new Token(TokenType::Invalid, 'Invalid UTF-8 encoding', null, new Span(0, $this->length, 1, 0));
            yield new Token(TokenType::Eof, '', null, new Span($this->length, $this->length, 1, 0));

            return;
        }

        // Skip a single optional leading UTF-8 BOM (U+FEFF, bytes EF BB BF). The
        // toml-test conformance suite treats a leading BOM as valid input.
        if ($this->pos === 0 && str_starts_with($this->input, "\u{FEFF}")) {
            $this->pos = 3;
        }

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

        while ($this->pos < $this->length && $this->input[$this->pos] !== "\n" && $this->input[$this->pos] !== "\r") {
            $char = $this->input[$this->pos];
            // Check for control characters (except tab which is allowed)
            $ord = ord($char);
            if (($ord < 0x20 && $ord !== 0x09) || $ord === 0x7F) {
                // Control character found - mark as invalid
                $this->advance();
                while ($this->pos < $this->length && $this->input[$this->pos] !== "\n") {
                    $this->advance();
                }
                $value = substr($this->input, $start, $this->pos - $start);

                return new Token(TokenType::Invalid, $value, null, new Span($start, $this->pos, $this->line, $col));
            }
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
            } elseif ($char === "\n" || $char === "\r") {
                // Unescaped newline not allowed in basic string
                return new Token(TokenType::Invalid, substr($this->input, $start, $this->pos - $start), null, new Span($start, $this->pos, $this->line, $col));
            } else {
                // Check for control characters (except tab which is allowed)
                $ord = ord($char);
                if (($ord < 0x20 && $ord !== 0x09) || $ord === 0x7F) {
                    $valid = false;
                }
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
            'e' => $this->version === TomlVersion::V11 ? "\x1B" : null,
            'x' => $this->version === TomlVersion::V11 ? $this->parseHexEscape(2) : null,
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
                // TOML allows up to 2 quotes at the end before closing """
                // So """"" = "" content + """ close, """""" = "" content + """ close + " after
                $quoteCount = 3;
                while (
                    $quoteCount < 5 &&
                    $this->pos + $quoteCount < $this->length &&
                    $this->input[$this->pos + $quoteCount] === '"'
                ) {
                    $quoteCount++;
                }

                // Extra quotes (1-2) become part of content
                $extraQuotes = $quoteCount - 3;
                $parsed .= str_repeat('"', $extraQuotes);

                for ($i = 0; $i < $quoteCount; $i++) {
                    $this->advance();
                }
                $value = substr($this->input, $start, $this->pos - $start);

                if (!$valid) {
                    return new Token(TokenType::Invalid, $value, null, new Span($start, $this->pos, $startLine, $col));
                }

                return new Token(TokenType::MultiLineBasicString, $value, $parsed, new Span($start, $this->pos, $startLine, $col));
            }

            $char = $this->input[$this->pos];

            if ($char === '\\') {
                // Check for line-ending backslash (may have whitespace before newline)
                $this->advance();

                // Skip any horizontal whitespace after backslash
                $tempPos = $this->pos;
                while ($tempPos < $this->length && ($this->input[$tempPos] === ' ' || $this->input[$tempPos] === "\t")) {
                    $tempPos++;
                }

                if ($tempPos < $this->length && ($this->input[$tempPos] === "\n" || $this->input[$tempPos] === "\r")) {
                    if ($this->input[$tempPos] === "\r" && ($tempPos + 1 >= $this->length || $this->input[$tempPos + 1] !== "\n")) {
                        $valid = false;
                        $this->pos = $tempPos + 1;

                        continue;
                    }
                    // Line continuation: skip to next non-whitespace
                    $this->pos = $tempPos;
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
                    $this->line++;
                    $this->column = 0;
                } else {
                    // Bare CR without LF is invalid
                    $valid = false;
                }
            } else {
                // Check for control characters (except tab which is allowed)
                $ord = ord($char);
                if (($ord < 0x20 && $ord !== 0x09) || $ord === 0x7F) {
                    $valid = false;
                }
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
        $valid = true;
        while ($this->pos < $this->length) {
            $char = $this->input[$this->pos];

            if ($char === "'") {
                $this->advance();
                $value = substr($this->input, $start, $this->pos - $start);

                if (!$valid) {
                    return new Token(TokenType::Invalid, $value, null, new Span($start, $this->pos, $startLine, $col));
                }

                return new Token(TokenType::LiteralString, $value, $parsed, new Span($start, $this->pos, $startLine, $col));
            }

            if ($char === "\n" || $char === "\r") {
                return new Token(TokenType::Invalid, substr($this->input, $start, $this->pos - $start), null, new Span($start, $this->pos, $startLine, $col));
            }

            // Check for control characters (except tab which is allowed)
            $ord = ord($char);
            if (($ord < 0x20 && $ord !== 0x09) || $ord === 0x7F) {
                $valid = false;
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
        $valid = true;
        while ($this->pos < $this->length) {
            if (
                $this->pos + 2 < $this->length &&
                $this->input[$this->pos] === "'" &&
                $this->input[$this->pos + 1] === "'" &&
                $this->input[$this->pos + 2] === "'"
            ) {
                // TOML allows up to 2 quotes at the end before closing '''
                $quoteCount = 3;
                while (
                    $quoteCount < 5 &&
                    $this->pos + $quoteCount < $this->length &&
                    $this->input[$this->pos + $quoteCount] === "'"
                ) {
                    $quoteCount++;
                }

                // Extra quotes (1-2) become part of content
                $extraQuotes = $quoteCount - 3;
                $parsed .= str_repeat("'", $extraQuotes);

                for ($i = 0; $i < $quoteCount; $i++) {
                    $this->advance();
                }
                $value = substr($this->input, $start, $this->pos - $start);

                if (!$valid) {
                    return new Token(TokenType::Invalid, $value, null, new Span($start, $this->pos, $startLine, $col));
                }

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
                    $this->line++;
                    $this->column = 0;
                } else {
                    // Bare CR without LF is invalid
                    $valid = false;
                }
            } else {
                // Check for control characters (except tab which is allowed)
                $ord = ord($char);
                if (($ord < 0x20 && $ord !== 0x09) || $ord === 0x7F) {
                    $valid = false;
                }
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

        // nan, inf are valid bare keys AND special float values
        // Return as BareKey - parser interprets as float in value position
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
            $char = $this->input[$this->pos];
            // Stop at '.' if we have a leading-zero decimal number (e.g., 01.23)
            // This allows dotted keys like `01.23` to be tokenized as separate parts
            if ($char === '.') {
                $soFar = substr($this->input, $start, $this->pos - $start);
                $cleanSoFar = ltrim($soFar, '+-');
                // If it's 0 followed by more digits (not 0 alone, not 0x/0o/0b), stop here
                if (preg_match('/^0\d+$/', $cleanSoFar)) {
                    break;
                }
            }
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
            // Stop at dot unless it's fractional seconds (follows time part with digits)
            if ($this->input[$this->pos] === '.') {
                // In valid datetime, '.' is only for fractional seconds, after HH:MM:SS
                // Check if we're in a time context by looking for ':' before
                $soFar = substr($this->input, $start, $this->pos - $start);
                if (!preg_match('/:\d{2}$/', $soFar)) {
                    // Not after :SS, so this is not fractional seconds - stop here
                    break;
                }
                // Check if followed by digits (fractional seconds)
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
            // TOML 1.1: if it's not a valid number but is a valid bare key, return as BareKey
            // But only if it doesn't look like an attempted number literal
            // (signed values or values with 0x/0o/0b prefixes should be Invalid, not BareKey)
            if (
                preg_match('/^[A-Za-z0-9_-]+$/', $value) &&
                !preg_match('/^[+-]/', $value) &&
                !preg_match('/^0[xXoObB]/', $value)
            ) {
                return new Token(TokenType::BareKey, $value, $value, $span);
            }

            return new Token(TokenType::Invalid, $value, null, $span);
        }

        $clean = str_replace('_', '', $value);

        // Hex (lowercase 0x only, no sign)
        if (str_starts_with($clean, '0x')) {
            // hexdec() returns a float when the value exceeds the signed 64-bit range
            $parsed = hexdec(substr($clean, 2));
            if (!is_int($parsed)) {
                return new Token(TokenType::Invalid, $value, null, $span);
            }

            return new Token(TokenType::Integer, $value, $parsed, $span);
        }

        // Octal (lowercase 0o only, no sign)
        if (str_starts_with($clean, '0o')) {
            $parsed = octdec(substr($clean, 2));
            if (!is_int($parsed)) {
                return new Token(TokenType::Invalid, $value, null, $span);
            }

            return new Token(TokenType::Integer, $value, $parsed, $span);
        }

        // Binary (lowercase 0b only, no sign)
        if (str_starts_with($clean, '0b')) {
            $parsed = bindec(substr($clean, 2));
            if (!is_int($parsed)) {
                return new Token(TokenType::Invalid, $value, null, $span);
            }

            return new Token(TokenType::Integer, $value, $parsed, $span);
        }

        // Float (has . or e/E)
        if (str_contains($clean, '.') || str_contains(strtolower($clean), 'e')) {
            return new Token(TokenType::Float, $value, (float)$clean, $span);
        }

        // Plain integer; TOML integers are 64-bit signed, so reject out-of-range values
        // instead of silently saturating to PHP_INT_MAX/PHP_INT_MIN via an (int) cast.
        $parsed = filter_var($clean, FILTER_VALIDATE_INT);
        if ($parsed === false) {
            return new Token(TokenType::Invalid, $value, null, $span);
        }

        return new Token(TokenType::Integer, $value, $parsed, $span);
    }

    private function isNumberChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '.' || $char === '+' || $char === '-';
    }

    private function isValidNumberLiteral(string $value): bool
    {
        // Integer: decimal (only decimal can have +/- sign)
        if (preg_match('/^[+-]?(?:0|[1-9](?:_?\d)*)$/', $value) === 1) {
            return true;
        }
        // Integer: hexadecimal (lowercase 'x' only, no sign allowed)
        if (preg_match('/^0x[0-9A-Fa-f](?:_?[0-9A-Fa-f])*$/', $value) === 1) {
            return true;
        }
        // Integer: octal (lowercase 'o' only, no sign allowed)
        if (preg_match('/^0o[0-7](?:_?[0-7])*$/', $value) === 1) {
            return true;
        }
        // Integer: binary (lowercase 'b' only, no sign allowed)
        if (preg_match('/^0b[01](?:_?[01])*$/', $value) === 1) {
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
        return TemporalValidator::isValidOffsetDateTime($value, $this->version);
    }

    private function isValidLocalDateTime(string $value): bool
    {
        return TemporalValidator::isValidLocalDateTime($value, $this->version);
    }

    private function isValidLocalDate(string $value): bool
    {
        return TemporalValidator::isValidLocalDate($value);
    }

    private function isValidLocalTime(string $value): bool
    {
        return TemporalValidator::isValidLocalTime($value, $this->version);
    }

    private function isBareKeyChar(string $char): bool
    {
        return ctype_alnum($char) || $char === '_' || $char === '-';
    }
}
