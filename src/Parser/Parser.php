<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Parser;

use LogicException;
use PhpCollective\Toml\Ast\Document;
use PhpCollective\Toml\Ast\Key;
use PhpCollective\Toml\Ast\KeyStyle;
use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Table;
use PhpCollective\Toml\Ast\Trivia;
use PhpCollective\Toml\Ast\TriviaKind;
use PhpCollective\Toml\Ast\Value\ArrayValue;
use PhpCollective\Toml\Ast\Value\BoolValue;
use PhpCollective\Toml\Ast\Value\FloatValue;
use PhpCollective\Toml\Ast\Value\InlineTable;
use PhpCollective\Toml\Ast\Value\IntegerBase;
use PhpCollective\Toml\Ast\Value\IntegerValue;
use PhpCollective\Toml\Ast\Value\LocalDate;
use PhpCollective\Toml\Ast\Value\LocalDateTime;
use PhpCollective\Toml\Ast\Value\LocalTime;
use PhpCollective\Toml\Ast\Value\OffsetDateTime;
use PhpCollective\Toml\Ast\Value\StringStyle;
use PhpCollective\Toml\Ast\Value\StringValue;
use PhpCollective\Toml\Ast\Value\Value;
use PhpCollective\Toml\Lexer\Lexer;
use PhpCollective\Toml\Lexer\Span;
use PhpCollective\Toml\Lexer\Token;
use PhpCollective\Toml\Lexer\TokenType;
use PhpCollective\Toml\TomlVersion;

final class Parser
{
    /**
     * @var string
     */
    private const CONTEXT_ARRAY = 'array';

    /**
     * @var string
     */
    private const CONTEXT_INLINE_TABLE = 'inline_table';

    /**
     * @var array<\PhpCollective\Toml\Parser\ParseError>
     */
    private array $errors = [];

    /**
     * @var array<\PhpCollective\Toml\Lexer\Token>
     */
    private array $tokens = [];

    private int $pos = 0;

    private bool $preserveTrivia;

    private string $input = '';

    /**
     * Stack tracking nesting context for error recovery.
     * Contains 'array' or 'inline_table' entries.
     *
     * @var array<string>
     */
    private array $contextStack = [];

    public function __construct(
        bool $preserveTrivia = false,
        private readonly TomlVersion $version = TomlVersion::V11,
    ) {
        $this->preserveTrivia = $preserveTrivia;
    }

    public function parse(string $input): Document
    {
        $this->input = $input;
        $lexer = new Lexer($input, $this->version);
        $this->tokens = iterator_to_array($lexer->tokenize());
        $this->pos = 0;
        $this->errors = [];
        $this->contextStack = [];

        return $this->parseDocument();
    }

    /**
     * @return array<\PhpCollective\Toml\Parser\ParseError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    private function parseDocument(): Document
    {
        $doc = new Document();
        $currentTable = null;

        while (!$this->isAtEnd()) {
            $leadingTrivia = $this->preserveTrivia ? $this->collectLeadingTrivia() : [];
            if (!$this->preserveTrivia) {
                $this->skipTrivia();
            }

            if ($this->isAtEnd()) {
                break;
            }

            $token = $this->current();

            if ($token->is(TokenType::LeftBracket)) {
                $table = $this->parseTableHeader();
                if ($table !== null) {
                    // TOML requires table headers to be on a line by themselves
                    $this->checkTableHeaderTerminator();

                    if ($this->preserveTrivia) {
                        $table->setLeadingTrivia($leadingTrivia);
                        $table->setTrailingTrivia($this->collectTrailingTrivia());
                    }
                    $doc->items[] = $table;
                    $currentTable = $table;
                }
            } elseif (
                $token->is(TokenType::BareKey, TokenType::BasicString, TokenType::LiteralString, TokenType::Invalid) ||
                $token->is(TokenType::Integer, TokenType::Float, TokenType::Boolean) ||
                $token->is(TokenType::LocalDate, TokenType::LocalTime, TokenType::LocalDateTime, TokenType::OffsetDateTime)
            ) {
                $kv = $this->parseKeyValue();
                if ($kv !== null) {
                    // Check that key-value is followed by newline, EOF, comment, or whitespace leading to those
                    $this->checkKeyValueTerminator();

                    if ($this->preserveTrivia) {
                        $kv->setLeadingTrivia($leadingTrivia);
                        $kv->setTrailingTrivia($this->collectTrailingTrivia());
                    }
                    if ($currentTable !== null) {
                        $currentTable->items[] = $kv;
                    } else {
                        $doc->items[] = $kv;
                    }
                }
            } elseif ($token->is(TokenType::Newline)) {
                $this->advance();
            } elseif ($token->is(TokenType::Invalid)) {
                $hint = $this->getInvalidTokenHint($token->value);
                $this->error("Invalid token: `{$token->value}`", $token->span, $hint);
                $this->synchronize();
            } else {
                $hint = $this->getUnexpectedTokenHint($token);
                $this->error("Unexpected token: `{$token->type->value}`", $token->span, $hint);
                $this->synchronize();
            }
        }

        return $doc;
    }

    private function parseTableHeader(): ?Table
    {
        $start = $this->current()->span;
        $this->advance(); // skip [

        $isArrayTable = false;
        if ($this->check(TokenType::LeftBracket)) {
            $isArrayTable = true;
            $this->advance();
        }

        $key = $this->parseKey();
        if ($key === null) {
            $this->synchronize();

            return null;
        }

        if (!$this->expect(TokenType::RightBracket)) {
            return null;
        }

        if ($isArrayTable && !$this->expect(TokenType::RightBracket)) {
            return null;
        }

        $end = $this->previous()->span;

        $span = $start->merge($end);

        return new Table(
            $key,
            $isArrayTable,
            $span,
            $this->slice($span),
            null,
            $this->sliceRange($span->start, $key->getSpan()->start),
            $this->sliceRange($key->getSpan()->end, $span->end),
        );
    }

    private function parseKeyValue(): ?KeyValue
    {
        $start = $this->current()->span;
        $key = $this->parseKey();

        if ($key === null) {
            $this->synchronize();

            return null;
        }

        if (!$this->expect(TokenType::Equals)) {
            return null;
        }

        $this->skipWhitespace();

        $value = $this->parseValue();
        if ($value === null) {
            $token = $this->current();
            if ($token->is(TokenType::Invalid)) {
                $hint = $this->getInvalidTokenHint($token->value);
                $this->error("Invalid token: `{$token->value}`", $token->span, $hint);
            } else {
                $hint = $this->getExpectedValueHint($token);
                $this->error('Expected value', $token->span, $hint);
            }
            $this->synchronize();

            return null;
        }

        $span = $start->merge($value->getSpan());

        return new KeyValue(
            $key,
            $value,
            $span,
            $this->slice($span),
            $this->slicePrefixTo($span, $value->getSpan()),
            $this->sliceRange($key->getSpan()->end, $value->getSpan()->start),
        );
    }

    /**
     * Parse a key-value pair inside an inline table.
     * Unlike parseKeyValue(), this does not call synchronize() on error,
     * allowing the inline table parser to handle recovery.
     */
    private function parseInlineTableKeyValue(): ?KeyValue
    {
        $start = $this->current()->span;
        $key = $this->parseKeyWithoutSync();

        if ($key === null) {
            return null;
        }

        if (!$this->check(TokenType::Equals)) {
            $hint = $this->getExpectHint(TokenType::Equals);
            $this->error('Expected =', $this->current()->span, $hint);

            return null;
        }
        $this->advance();

        $this->skipWhitespace();

        $value = $this->parseValue();
        if ($value === null) {
            $token = $this->current();
            if ($token->is(TokenType::Invalid)) {
                $hint = $this->getInvalidTokenHint($token->value);
                $this->error("Invalid token: `{$token->value}`", $token->span, $hint);
            } else {
                $hint = $this->getExpectedValueHint($token);
                $this->error('Expected value', $token->span, $hint);
            }

            return null;
        }

        $span = $start->merge($value->getSpan());

        return new KeyValue(
            $key,
            $value,
            $span,
            $this->slice($span),
            $this->slicePrefixTo($span, $value->getSpan()),
            $this->sliceRange($key->getSpan()->end, $value->getSpan()->start),
        );
    }

    /**
     * Parse a key without calling synchronize() on error.
     * Used for inline table key-value parsing where the parent handles recovery.
     */
    private function parseKeyWithoutSync(): ?Key
    {
        $parts = [];
        $styles = [];
        $start = null;
        $rawSeparators = [];
        $lastPartEnd = 0;
        $separatorStart = null;

        do {
            $this->skipWhitespace();
            $token = $this->current();
            $start ??= $token->span;

            if ($separatorStart !== null) {
                $rawSeparators[] = $this->sliceRange($separatorStart, $token->span->start);
                $separatorStart = null;
            }

            if ($token->is(TokenType::BareKey)) {
                $parts[] = $token->parsed;
                $styles[] = KeyStyle::Bare;
                $this->advance();
            } elseif ($token->is(TokenType::BasicString)) {
                $parts[] = $token->parsed;
                $styles[] = KeyStyle::Basic;
                $this->advance();
            } elseif ($token->is(TokenType::LiteralString)) {
                $parts[] = $token->parsed;
                $styles[] = KeyStyle::Literal;
                $this->advance();
            } elseif ($token->is(TokenType::Integer)) {
                if (preg_match('/^[+-]?\d[\d_]*$/', $token->value) !== 1) {
                    $this->error('Expected key', $token->span, 'Only decimal integers can be used as bare keys.');

                    return null;
                }
                $parts[] = $token->value;
                $styles[] = KeyStyle::Bare;
                $this->advance();
            } elseif ($token->is(TokenType::Invalid)) {
                if (preg_match('/^[A-Za-z0-9_-]+$/', $token->value) !== 1) {
                    $hint = $this->getExpectedKeyHint($token);
                    $this->error('Expected key', $token->span, $hint);

                    return null;
                }
                $parts[] = $token->value;
                $styles[] = KeyStyle::Bare;
                $this->advance();
            } elseif ($token->is(TokenType::Boolean)) {
                $parts[] = $token->value;
                $styles[] = KeyStyle::Bare;
                $this->advance();
            } elseif ($token->is(TokenType::Float)) {
                $value = $token->value;
                if (preg_match('/^\d+\.\d+$/', str_replace('_', '', $value)) === 1 && !str_contains(strtolower($value), 'e')) {
                    $dotParts = explode('.', $value);
                    foreach ($dotParts as $part) {
                        $parts[] = $part;
                        $styles[] = KeyStyle::Bare;
                        $rawSeparators[] = '.';
                    }
                    array_pop($rawSeparators);
                } else {
                    $parts[] = $value;
                    $styles[] = KeyStyle::Bare;
                }
                $this->advance();
            } elseif ($token->is(TokenType::LocalDate, TokenType::LocalTime, TokenType::LocalDateTime, TokenType::OffsetDateTime)) {
                $parts[] = $token->value;
                $styles[] = KeyStyle::Bare;
                $this->advance();
            } else {
                $hint = $this->getExpectedKeyHint($token);
                $this->error('Expected key', $token->span, $hint);

                return null;
            }

            $lastPartEnd = $token->span->end;
            $this->skipWhitespace();
            if ($this->match(TokenType::Dot)) {
                $separatorStart = $lastPartEnd;

                continue;
            }

            break;
        } while (true);

        $span = new Span($start->start, $lastPartEnd, $start->line, $start->column);

        return new Key($parts, $styles, $span, $this->slice($span), null, null, $rawSeparators);
    }

    private function parseKey(): ?Key
    {
        $parts = [];
        $styles = [];
        $start = null;
        $rawSeparators = [];
        $lastPartEnd = 0;
        $separatorStart = null;

        do {
            $this->skipWhitespace();
            $token = $this->current();
            $start ??= $token->span;

            if ($separatorStart !== null) {
                $rawSeparators[] = $this->sliceRange($separatorStart, $token->span->start);
                $separatorStart = null;
            }

            if ($token->is(TokenType::BareKey)) {
                $parts[] = $token->parsed;
                $styles[] = KeyStyle::Bare;
                $this->advance();
            } elseif ($token->is(TokenType::BasicString)) {
                $parts[] = $token->parsed;
                $styles[] = KeyStyle::Basic;
                $this->advance();
            } elseif ($token->is(TokenType::LiteralString)) {
                $parts[] = $token->parsed;
                $styles[] = KeyStyle::Literal;
                $this->advance();
            } elseif ($token->is(TokenType::Integer)) {
                // Integer-looking tokens can be bare keys when they are decimal integers.
                if (preg_match('/^[+-]?\d[\d_]*$/', $token->value) !== 1) {
                    $this->error('Expected key', $token->span, 'Only decimal integers can be used as bare keys.');

                    return null;
                }
                $parts[] = $token->value;
                $styles[] = KeyStyle::Bare;
                $this->advance();
            } elseif ($token->is(TokenType::Invalid)) {
                if (preg_match('/^[A-Za-z0-9_-]+$/', $token->value) !== 1) {
                    $hint = $this->getExpectedKeyHint($token);
                    $this->error('Expected key', $token->span, $hint);

                    return null;
                }
                $parts[] = $token->value;
                $styles[] = KeyStyle::Bare;
                $this->advance();
            } elseif ($token->is(TokenType::Boolean)) {
                // TOML 1.1: booleans are valid bare keys
                $parts[] = $token->value;
                $styles[] = KeyStyle::Bare;
                $this->advance();
            } elseif ($token->is(TokenType::Float)) {
                // TOML 1.1: float-like tokens may be dotted keys (e.g., 1.2 = "a.b")
                $value = $token->value;
                // Check if it's a simple unsigned dotted key (no exponent, just digit.digit)
                if (preg_match('/^\d+\.\d+$/', str_replace('_', '', $value)) === 1 && !str_contains(strtolower($value), 'e')) {
                    // Split into parts at the dot
                    $dotParts = explode('.', $value);
                    foreach ($dotParts as $part) {
                        $parts[] = $part;
                        $styles[] = KeyStyle::Bare;
                        $rawSeparators[] = '.';
                    }
                    // Remove the extra separator we added
                    array_pop($rawSeparators);
                } else {
                    $parts[] = $value;
                    $styles[] = KeyStyle::Bare;
                }
                $this->advance();
            } elseif ($token->is(TokenType::LocalDate, TokenType::LocalTime, TokenType::LocalDateTime, TokenType::OffsetDateTime)) {
                // Date/time-like tokens are valid bare keys in key position
                // e.g., 2001-02-03 = 1 or 15:16:17 = 2
                $parts[] = $token->value;
                $styles[] = KeyStyle::Bare;
                $this->advance();
            } else {
                $hint = $this->getExpectedKeyHint($token);
                $this->error('Expected key', $token->span, $hint);

                return null;
            }

            $lastPartEnd = $token->span->end;
            $this->skipWhitespace();
            if ($this->match(TokenType::Dot)) {
                $separatorStart = $lastPartEnd;

                continue;
            }

            break;
        } while (true);

        $span = new Span($start->start, $lastPartEnd, $start->line, $start->column);

        return new Key($parts, $styles, $span, $this->slice($span), null, null, $rawSeparators);
    }

    private function parseValue(): ?Value
    {
        $this->skipWhitespace();
        $token = $this->current();

        // Handle special float keywords (nan, inf) as bare keys
        if ($token->type === TokenType::BareKey) {
            if ($token->value === 'nan') {
                $this->advance();

                return new FloatValue(NAN, $token->span, $token->value);
            }
            if ($token->value === 'inf') {
                $this->advance();

                return new FloatValue(INF, $token->span, $token->value);
            }

            return null;
        }

        return match ($token->type) {
            TokenType::BasicString,
            TokenType::LiteralString,
            TokenType::MultiLineBasicString,
            TokenType::MultiLineLiteralString => $this->parseStringValue(),

            TokenType::Integer => $this->parseIntegerValue(),
            TokenType::Float => $this->parseFloatValue(),
            TokenType::Boolean => $this->parseBoolValue(),

            TokenType::OffsetDateTime => $this->parseOffsetDateTime(),
            TokenType::LocalDateTime => $this->parseLocalDateTime(),
            TokenType::LocalDate => $this->parseLocalDate(),
            TokenType::LocalTime => $this->parseLocalTime(),

            TokenType::LeftBracket => $this->parseArray(),
            TokenType::LeftBrace => $this->parseInlineTable(),

            default => null,
        };
    }

    private function parseStringValue(): StringValue
    {
        $token = $this->advance();
        $style = match ($token->type) {
            TokenType::BasicString => StringStyle::Basic,
            TokenType::LiteralString => StringStyle::Literal,
            TokenType::MultiLineBasicString => StringStyle::MultiLineBasic,
            TokenType::MultiLineLiteralString => StringStyle::MultiLineLiteral,
            default => StringStyle::Basic,
        };

        return new StringValue($token->parsed, $style, $token->span, $token->value);
    }

    private function parseIntegerValue(): IntegerValue
    {
        $token = $this->advance();
        $base = IntegerBase::Decimal;

        // Strip optional sign before checking base prefix
        $value = ltrim($token->value, '+-');

        if (str_starts_with($value, '0x') || str_starts_with($value, '0X')) {
            $base = IntegerBase::Hexadecimal;
        } elseif (str_starts_with($value, '0o') || str_starts_with($value, '0O')) {
            $base = IntegerBase::Octal;
        } elseif (str_starts_with($value, '0b') || str_starts_with($value, '0B')) {
            $base = IntegerBase::Binary;
        }

        return new IntegerValue($token->parsed, $base, $token->span, $token->value);
    }

    private function parseFloatValue(): FloatValue
    {
        $token = $this->advance();

        return new FloatValue($token->parsed, $token->span, $token->value);
    }

    private function parseBoolValue(): BoolValue
    {
        $token = $this->advance();

        return new BoolValue($token->parsed, $token->span, $token->value);
    }

    private function parseOffsetDateTime(): OffsetDateTime
    {
        $token = $this->advance();

        return new OffsetDateTime($token->parsed, $token->value, $token->span);
    }

    private function parseLocalDateTime(): LocalDateTime
    {
        $token = $this->advance();

        return new LocalDateTime($token->value, $token->span, $token->value);
    }

    private function parseLocalDate(): LocalDate
    {
        $token = $this->advance();

        return new LocalDate($token->value, $token->span, $token->value);
    }

    private function parseLocalTime(): LocalTime
    {
        $token = $this->advance();

        return new LocalTime($token->value, $token->span, $token->value);
    }

    private function parseArray(): ArrayValue
    {
        $this->contextStack[] = self::CONTEXT_ARRAY;

        $start = $this->current()->span;
        $this->advance(); // skip [

        $items = [];
        $openingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
        $closingTrivia = [];
        $hasTrailingComma = false;
        $nextLeadingTrivia = $openingTrivia;

        while (!$this->check(TokenType::RightBracket) && !$this->isAtEnd()) {
            if (!$this->preserveTrivia) {
                $this->skipTriviaInCollection();
            }

            if ($this->check(TokenType::RightBracket)) {
                if ($items === []) {
                    $closingTrivia = $nextLeadingTrivia;
                }

                break;
            }

            $value = $this->parseValue();
            if ($value === null) {
                // Check for invalid syntax like [1,,2] or [,] - expected a value
                $token = $this->current();
                if (!$this->check(TokenType::RightBracket)) {
                    $this->error('Expected value in array', $token->span);
                    // Recover within the array context
                    if (!$this->synchronizeInCollection()) {
                        break;
                    }

                    continue;
                }

                break;
            }

            if ($this->preserveTrivia) {
                $value->setLeadingTrivia($nextLeadingTrivia);
            }
            $items[] = $value;

            $trailingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
            if (!$this->preserveTrivia) {
                $this->skipTriviaInCollection();
            }

            if (!$this->check(TokenType::RightBracket)) {
                if (!$this->match(TokenType::Comma)) {
                    break;
                }

                if ($this->preserveTrivia) {
                    $value->setTrailingTrivia($trailingTrivia);
                }

                $nextLeadingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
                if ($this->check(TokenType::RightBracket)) {
                    $hasTrailingComma = true;
                    $closingTrivia = $nextLeadingTrivia;

                    break;
                }
            } elseif ($this->preserveTrivia) {
                $value->setTrailingTrivia($trailingTrivia);
            }
        }

        $this->expect(TokenType::RightBracket);
        $span = $start->merge($this->previous()->span);

        array_pop($this->contextStack);

        return new ArrayValue(
            $items,
            $span,
            $openingTrivia,
            $closingTrivia,
            $hasTrailingComma,
            count($items),
            $this->slice($span),
            $this->inferSingleLineArrayStyle($items, $openingTrivia, $closingTrivia, $hasTrailingComma),
        );
    }

    private function parseInlineTable(): InlineTable
    {
        $this->contextStack[] = self::CONTEXT_INLINE_TABLE;

        $start = $this->current()->span;
        $this->advance(); // skip {

        $items = [];
        $openingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
        $closingTrivia = [];
        $hasTrailingComma = false;
        $nextLeadingTrivia = $openingTrivia;
        $hasInlineTableLayoutNewline = $this->triviaContainsNewline($openingTrivia);

        while (!$this->check(TokenType::RightBrace) && !$this->isAtEnd()) {
            if (!$this->preserveTrivia) {
                $hasInlineTableLayoutNewline = $this->skipTriviaInCollection() || $hasInlineTableLayoutNewline;
            }

            if ($this->check(TokenType::RightBrace)) {
                if ($items === []) {
                    $closingTrivia = $nextLeadingTrivia;
                }

                break;
            }

            $kv = $this->parseInlineTableKeyValue();
            if ($kv !== null) {
                if ($this->preserveTrivia) {
                    $kv->setLeadingTrivia($nextLeadingTrivia);
                }
                $items[] = $kv;
            } else {
                // Failed to parse key-value, try to recover
                if (!$this->synchronizeInCollection()) {
                    break;
                }

                continue;
            }

            $trailingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
            $hasInlineTableLayoutNewline = $this->triviaContainsNewline($trailingTrivia) || $hasInlineTableLayoutNewline;
            if (!$this->preserveTrivia) {
                $hasInlineTableLayoutNewline = $this->skipTriviaInCollection() || $hasInlineTableLayoutNewline;
            }

            if (!$this->check(TokenType::RightBrace)) {
                if (!$this->match(TokenType::Comma)) {
                    break;
                }
                if ($this->preserveTrivia) {
                    $kv->setTrailingTrivia($trailingTrivia);
                }

                $nextLeadingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
                $hasInlineTableLayoutNewline = $this->triviaContainsNewline($nextLeadingTrivia) || $hasInlineTableLayoutNewline;
                if (!$this->preserveTrivia) {
                    $hasInlineTableLayoutNewline = $this->skipTriviaInCollection() || $hasInlineTableLayoutNewline;
                }
                if ($this->check(TokenType::RightBrace)) {
                    $hasTrailingComma = true;
                    $closingTrivia = $nextLeadingTrivia;

                    break;
                }
            } elseif ($this->preserveTrivia) {
                $kv->setTrailingTrivia($trailingTrivia);
            }
        }

        $this->expect(TokenType::RightBrace);
        $span = $start->merge($this->previous()->span);

        array_pop($this->contextStack);

        if ($this->version === TomlVersion::V10) {
            if ($hasInlineTableLayoutNewline) {
                $this->error('Multiline inline tables require TOML 1.1', $span);
            }

            if ($hasTrailingComma) {
                $this->error('Inline table trailing commas require TOML 1.1', $span);
            }
        }

        return new InlineTable(
            $items,
            $span,
            $openingTrivia,
            $closingTrivia,
            $hasTrailingComma,
            count($items),
            $this->slice($span),
            $this->inferSingleLineInlineTableStyle($items, $openingTrivia, $closingTrivia),
        );
    }

    // Helper methods

    private function current(): Token
    {
        return $this->tokens[$this->pos] ?? $this->tokens[count($this->tokens) - 1];
    }

    private function previous(): Token
    {
        return $this->tokens[$this->pos - 1] ?? $this->tokens[0];
    }

    private function isAtEnd(): bool
    {
        return $this->current()->type === TokenType::Eof;
    }

    /**
     * @phpstan-impure
     */
    private function check(TokenType $type): bool
    {
        return $this->current()->type === $type;
    }

    private function match(TokenType ...$types): bool
    {
        foreach ($types as $type) {
            if ($this->check($type)) {
                $this->advance();

                return true;
            }
        }

        return false;
    }

    private function advance(): Token
    {
        if (!$this->isAtEnd()) {
            $this->pos++;
        }

        return $this->previous();
    }

    /**
     * @phpstan-impure
     */
    private function expect(TokenType $type): bool
    {
        if ($this->check($type)) {
            $this->advance();

            return true;
        }

        $hint = $this->getExpectHint($type);
        $this->error("Expected {$type->value}", $this->current()->span, $hint);

        return false;
    }

    private function getExpectHint(TokenType $expected): ?string
    {
        $current = $this->current();

        return match ($expected) {
            TokenType::Equals => $this->getEqualsHint($current),
            TokenType::RightBracket => 'Unclosed bracket. Table headers use [name] syntax.',
            TokenType::RightBrace => 'Unclosed brace. Inline tables use { key = value } syntax.',
            default => null,
        };
    }

    private function getEqualsHint(Token $current): ?string
    {
        // YAML-style colon
        if ($current->value === ':') {
            return 'TOML uses `=` for key-value pairs, not `:`. Example: key = "value"';
        }

        // Missing equals - bare word after key
        if ($current->is(TokenType::BareKey, TokenType::BasicString, TokenType::LiteralString)) {
            return 'Key and value must be separated by `=`. Example: key = "value"';
        }

        return null;
    }

    private function getExpectedValueHint(Token $token): ?string
    {
        // Check for YAML-style booleans
        $yamlBooleans = ['yes', 'no', 'on', 'off', 'y', 'n'];
        if ($token->is(TokenType::BareKey) && in_array(strtolower($token->value), $yamlBooleans, true)) {
            return "TOML booleans are `true` or `false`, not `{$token->value}`.";
        }

        // Check for unquoted string (bare key in value position)
        if ($token->is(TokenType::BareKey)) {
            return "Strings must be quoted in TOML. Did you mean `\"{$token->value}\"`?";
        }

        return null;
    }

    private function getInvalidTokenHint(string $value): ?string
    {
        // Check for semver-like patterns (e.g., 1.0.0, 2.1.3)
        if (preg_match('/^\d+\.\d+\.\d+/', $value) === 1) {
            return "This looks like a version string. Strings must be quoted: `\"{$value}\"`";
        }

        // Check for multiple dots in what looks like a number
        if (preg_match('/^\d+\./', $value) === 1 && substr_count($value, '.') > 1) {
            return 'Numbers can only have one decimal point. If this is a string, it must be quoted.';
        }

        return null;
    }

    private function getUnexpectedTokenHint(Token $token): ?string
    {
        // Equals without a key
        if ($token->is(TokenType::Equals)) {
            return 'A key is required before `=`. Example: key = "value"';
        }

        return null;
    }

    private function getExpectedKeyHint(Token $token): ?string
    {
        // Consecutive dots (empty key segment)
        if ($token->is(TokenType::Dot)) {
            return 'Empty key segment. Keys cannot have consecutive dots.';
        }

        // Check for special characters that aren't allowed in bare keys
        if ($token->is(TokenType::Invalid) && preg_match('/[^A-Za-z0-9_-]/', $token->value) === 1) {
            return 'Bare keys can only contain A-Za-z0-9_-. Use quotes for other characters.';
        }

        return null;
    }

    private function skipTrivia(): void
    {
        while ($this->check(TokenType::Whitespace) || $this->check(TokenType::Comment) || $this->check(TokenType::Newline)) {
            $this->advance();
        }
    }

    private function skipWhitespace(): void
    {
        while ($this->check(TokenType::Whitespace)) {
            $this->advance();
        }
    }

    /**
     * Check that a table header is properly terminated.
     * TOML requires table headers to be on a line by themselves.
     */
    private function checkTableHeaderTerminator(): void
    {
        $token = $this->current();

        // Direct valid terminators
        if ($token->is(TokenType::Newline, TokenType::Eof, TokenType::Comment)) {
            return;
        }

        // If whitespace, peek ahead to check what follows
        if ($token->is(TokenType::Whitespace)) {
            $peekPos = $this->pos + 1;
            $tokenCount = count($this->tokens);
            while ($peekPos < $tokenCount && $this->tokens[$peekPos]->is(TokenType::Whitespace)) {
                $peekPos++;
            }
            if ($peekPos < $tokenCount) {
                $nextToken = $this->tokens[$peekPos];
                if ($nextToken->is(TokenType::Newline, TokenType::Eof, TokenType::Comment)) {
                    return;
                }
            } else {
                return;
            }
        }

        $this->error('Expected newline or comment after table header', $token->span);
    }

    /**
     * Check that a key-value pair is properly terminated.
     * After a value, we must see newline, EOF, comment, or whitespace followed by those.
     */
    private function checkKeyValueTerminator(): void
    {
        $token = $this->current();
        $hintToken = $token;

        // Direct valid terminators
        if ($token->is(TokenType::Newline, TokenType::Eof, TokenType::Comment)) {
            return;
        }

        // If whitespace, peek ahead to check what follows
        if ($token->is(TokenType::Whitespace)) {
            // Look ahead without advancing
            $peekPos = $this->pos + 1;
            $tokenCount = count($this->tokens);
            while ($peekPos < $tokenCount && $this->tokens[$peekPos]->is(TokenType::Whitespace)) {
                $peekPos++;
            }
            if ($peekPos < $tokenCount) {
                $nextToken = $this->tokens[$peekPos];
                if ($nextToken->is(TokenType::Newline, TokenType::Eof, TokenType::Comment)) {
                    return;
                }
                // Use the actual problematic token for the hint
                $hintToken = $nextToken;
            } else {
                // End of tokens
                return;
            }
        }

        $hint = $this->getKeyValueTerminatorHint($hintToken);
        $this->error('Expected newline or end of input after value', $token->span, $hint);
    }

    private function getKeyValueTerminatorHint(Token $token): ?string
    {
        // Check if it looks like another key-value pair on the same line
        if ($token->is(TokenType::BareKey, TokenType::BasicString, TokenType::LiteralString)) {
            return 'Each key-value pair must be on its own line.';
        }

        return null;
    }

    private function skipTriviaInCollection(): bool
    {
        $hasNewline = false;

        while ($this->check(TokenType::Whitespace) || $this->check(TokenType::Comment) || $this->check(TokenType::Newline)) {
            $hasNewline = $this->check(TokenType::Newline) || $hasNewline;
            $this->advance();
        }

        return $hasNewline;
    }

    /**
     * @return array<\PhpCollective\Toml\Ast\Trivia>
     */
    private function collectLeadingTrivia(): array
    {
        $trivia = [];

        while ($this->check(TokenType::Whitespace) || $this->check(TokenType::Comment) || $this->check(TokenType::Newline)) {
            $trivia[] = $this->toTrivia($this->advance());
        }

        return $trivia;
    }

    /**
     * @return array<\PhpCollective\Toml\Ast\Trivia>
     */
    private function collectTrailingTrivia(): array
    {
        $trivia = [];

        while ($this->check(TokenType::Whitespace) || $this->check(TokenType::Comment)) {
            $trivia[] = $this->toTrivia($this->advance());
        }

        if ($this->check(TokenType::Newline)) {
            $trivia[] = $this->toTrivia($this->advance());
        }

        return $trivia;
    }

    /**
     * @return array<\PhpCollective\Toml\Ast\Trivia>
     */
    private function collectCollectionTrivia(): array
    {
        $trivia = [];

        while ($this->check(TokenType::Whitespace) || $this->check(TokenType::Comment) || $this->check(TokenType::Newline)) {
            $trivia[] = $this->toTrivia($this->advance());
        }

        return $trivia;
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Trivia> $trivia
     */
    private function triviaContainsNewline(array $trivia): bool
    {
        foreach ($trivia as $item) {
            if ($item->kind === TriviaKind::Newline) {
                return true;
            }
        }

        return false;
    }

    private function toTrivia(Token $token): Trivia
    {
        $kind = match ($token->type) {
            TokenType::Whitespace => TriviaKind::Whitespace,
            TokenType::Comment => TriviaKind::Comment,
            TokenType::Newline => TriviaKind::Newline,
            default => throw new LogicException("Token {$token->type->value} is not trivia"),
        };

        return new Trivia($kind, $token->value, $token->span);
    }

    private function slice(Span $span): string
    {
        return substr($this->input, $span->start, $span->length());
    }

    private function sliceRange(int $start, int $end): string
    {
        return substr($this->input, $start, $end - $start);
    }

    private function slicePrefixTo(Span $container, Span $end): string
    {
        return substr($this->input, $container->start, $end->start - $container->start);
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Value\Value> $items
     * @param array<\PhpCollective\Toml\Ast\Trivia> $openingTrivia
     * @param array<\PhpCollective\Toml\Ast\Trivia> $closingTrivia
     * @param bool $hasTrailingComma
     *
     * @return array{opening:string, beforeComma:string, afterComma:string, closing:string, trailingComma:bool}|null
     */
    private function inferSingleLineArrayStyle(array $items, array $openingTrivia, array $closingTrivia, bool $hasTrailingComma): ?array
    {
        $opening = $this->singleLineWhitespaceTrivia($openingTrivia);
        $closing = $this->singleLineWhitespaceTrivia($closingTrivia);
        if ($opening === null || $closing === null) {
            return null;
        }

        if (count($items) < 2) {
            return null;
        }

        $beforeComma = null;
        $afterComma = null;

        for ($index = 0, $itemCount = count($items); $index < $itemCount - 1; $index++) {
            $currentBeforeComma = $this->singleLineWhitespaceTrivia($items[$index]->getTrailingTrivia());
            $currentAfterComma = $this->singleLineWhitespaceTrivia($items[$index + 1]->getLeadingTrivia());
            if ($currentBeforeComma === null || $currentAfterComma === null) {
                return null;
            }

            $beforeComma ??= $currentBeforeComma;
            $afterComma ??= $currentAfterComma;
            if ($beforeComma !== $currentBeforeComma || $afterComma !== $currentAfterComma) {
                return null;
            }
        }

        return [
            'opening' => $opening,
            'beforeComma' => $beforeComma,
            'afterComma' => $afterComma,
            'closing' => $closing,
            'trailingComma' => $hasTrailingComma,
        ];
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\KeyValue> $items
     * @param array<\PhpCollective\Toml\Ast\Trivia> $openingTrivia
     * @param array<\PhpCollective\Toml\Ast\Trivia> $closingTrivia
     *
     * @return array{opening:string, afterComma:string, closing:string}|null
     */
    private function inferSingleLineInlineTableStyle(array $items, array $openingTrivia, array $closingTrivia): ?array
    {
        $opening = $this->singleLineWhitespaceTrivia($openingTrivia);
        $closing = $this->singleLineWhitespaceTrivia($closingTrivia);
        if ($opening === null || $closing === null) {
            return null;
        }

        if (count($items) < 2) {
            return null;
        }

        $afterComma = null;

        for ($index = 1, $itemCount = count($items); $index < $itemCount; $index++) {
            $currentAfterComma = $this->singleLineWhitespaceTrivia($items[$index]->getLeadingTrivia());
            if ($currentAfterComma === null) {
                return null;
            }

            $afterComma ??= $currentAfterComma;
            if ($afterComma !== $currentAfterComma) {
                return null;
            }
        }

        return [
            'opening' => $opening,
            'afterComma' => $afterComma,
            'closing' => $closing,
        ];
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Trivia> $trivia
     */
    private function singleLineWhitespaceTrivia(array $trivia): ?string
    {
        $buffer = '';
        foreach ($trivia as $item) {
            if ($item->kind !== TriviaKind::Whitespace) {
                return null;
            }
            $buffer .= $item->value;
        }

        return $buffer;
    }

    private function error(string $message, Span $span, ?string $hint = null): void
    {
        $this->errors[] = new ParseError($message, $span, $hint);
    }

    /**
     * Synchronize parser state after an error at the top level.
     * Skips tokens until a newline or table header is found.
     */
    private function synchronize(): void
    {
        while (!$this->isAtEnd()) {
            if ($this->check(TokenType::Newline)) {
                $this->advance();

                return;
            }
            // Stop at table header start for recovery
            if ($this->check(TokenType::LeftBracket)) {
                return;
            }
            $this->advance();
        }
    }

    /**
     * Synchronize parser state after an error inside a collection (array or inline table).
     * Skips to the next comma or closing bracket/brace, allowing recovery within the collection.
     *
     * @return bool True if recovery found a comma (can continue parsing), false if hit closing bracket/brace or end
     */
    private function synchronizeInCollection(): bool
    {
        $depth = 0;

        while (!$this->isAtEnd()) {
            $token = $this->current();

            // Track nested brackets to avoid stopping at wrong level
            if ($token->is(TokenType::LeftBracket, TokenType::LeftBrace)) {
                $depth++;
                $this->advance();

                continue;
            }

            if ($token->is(TokenType::RightBracket, TokenType::RightBrace)) {
                if ($depth > 0) {
                    $depth--;
                    $this->advance();

                    continue;
                }

                // At our level's closing bracket - stop without consuming
                return false;
            }

            // At our level, comma means we can continue with next element
            if ($depth === 0 && $token->is(TokenType::Comma)) {
                $this->advance();

                return true;
            }

            $this->advance();
        }

        return false;
    }
}
