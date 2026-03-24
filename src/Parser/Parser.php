<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Parser;

use PhpCollective\Toml\Ast\Document;
use PhpCollective\Toml\Ast\Key;
use PhpCollective\Toml\Ast\KeyStyle;
use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Table;
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

final class Parser
{
    /**
     * @var array<\PhpCollective\Toml\Parser\ParseError>
     */
    private array $errors = [];

    /**
     * @var array<\PhpCollective\Toml\Lexer\Token>
     */
    private array $tokens = [];

    private int $pos = 0;

    /**
     * @phpstan-ignore-next-line Reserved for trivia preservation
     */
    private bool $preserveTrivia;

    public function __construct(bool $preserveTrivia = false)
    {
        $this->preserveTrivia = $preserveTrivia;
    }

    public function parse(string $input): Document
    {
        $lexer = new Lexer($input);
        $this->tokens = iterator_to_array($lexer->tokenize());
        $this->pos = 0;
        $this->errors = [];

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
            $this->skipTrivia();

            if ($this->isAtEnd()) {
                break;
            }

            $token = $this->current();

            if ($token->is(TokenType::LeftBracket)) {
                $table = $this->parseTableHeader();
                if ($table !== null) {
                    $doc->items[] = $table;
                    $currentTable = $table;
                }
            } elseif ($token->is(TokenType::BareKey, TokenType::BasicString, TokenType::LiteralString)) {
                $kv = $this->parseKeyValue();
                if ($kv !== null) {
                    if ($currentTable !== null) {
                        $currentTable->items[] = $kv;
                    } else {
                        $doc->items[] = $kv;
                    }
                }
            } elseif ($token->is(TokenType::Newline)) {
                $this->advance();
            } elseif ($token->is(TokenType::Invalid)) {
                $this->error("Invalid token: {$token->value}", $token->span);
                $this->synchronize();
            } else {
                $this->error("Unexpected token: {$token->type->value}", $token->span);
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

        return new Table($key, $isArrayTable, $start->merge($end));
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
                $this->error("Invalid token: {$token->value}", $token->span);
            } else {
                $this->error('Expected value', $token->span);
            }
            $this->synchronize();

            return null;
        }

        return new KeyValue($key, $value, $start->merge($value->getSpan()));
    }

    private function parseKey(): ?Key
    {
        $parts = [];
        $styles = [];
        $start = $this->current()->span;

        do {
            $this->skipWhitespace();
            $token = $this->current();

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
            } else {
                $this->error('Expected key', $token->span);

                return null;
            }

            $this->skipWhitespace();
        } while ($this->match(TokenType::Dot));

        $end = $this->previous()->span;

        return new Key($parts, $styles, $start->merge($end));
    }

    private function parseValue(): ?Value
    {
        $this->skipWhitespace();
        $token = $this->current();

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

        return new StringValue($token->parsed, $style, $token->span);
    }

    private function parseIntegerValue(): IntegerValue
    {
        $token = $this->advance();
        $base = IntegerBase::Decimal;

        if (str_starts_with($token->value, '0x') || str_starts_with($token->value, '0X')) {
            $base = IntegerBase::Hexadecimal;
        } elseif (str_starts_with($token->value, '0o') || str_starts_with($token->value, '0O')) {
            $base = IntegerBase::Octal;
        } elseif (str_starts_with($token->value, '0b') || str_starts_with($token->value, '0B')) {
            $base = IntegerBase::Binary;
        }

        return new IntegerValue($token->parsed, $base, $token->span);
    }

    private function parseFloatValue(): FloatValue
    {
        $token = $this->advance();

        return new FloatValue($token->parsed, $token->span);
    }

    private function parseBoolValue(): BoolValue
    {
        $token = $this->advance();

        return new BoolValue($token->parsed, $token->span);
    }

    private function parseOffsetDateTime(): OffsetDateTime
    {
        $token = $this->advance();

        return new OffsetDateTime($token->parsed, $token->value, $token->span);
    }

    private function parseLocalDateTime(): LocalDateTime
    {
        $token = $this->advance();

        return new LocalDateTime($token->value, $token->span);
    }

    private function parseLocalDate(): LocalDate
    {
        $token = $this->advance();

        return new LocalDate($token->value, $token->span);
    }

    private function parseLocalTime(): LocalTime
    {
        $token = $this->advance();

        return new LocalTime($token->value, $token->span);
    }

    private function parseArray(): ArrayValue
    {
        $start = $this->current()->span;
        $this->advance(); // skip [

        $items = [];

        while (!$this->check(TokenType::RightBracket) && !$this->isAtEnd()) {
            $this->skipTriviaInCollection();

            if ($this->check(TokenType::RightBracket)) {
                break;
            }

            $value = $this->parseValue();
            if ($value !== null) {
                $items[] = $value;
            }

            $this->skipTriviaInCollection();

            if (!$this->check(TokenType::RightBracket)) {
                if (!$this->match(TokenType::Comma)) {
                    break;
                }
            }
        }

        $this->expect(TokenType::RightBracket);

        return new ArrayValue($items, $start->merge($this->previous()->span));
    }

    private function parseInlineTable(): InlineTable
    {
        $start = $this->current()->span;
        $this->advance(); // skip {

        $items = [];

        while (!$this->check(TokenType::RightBrace) && !$this->isAtEnd()) {
            $this->skipWhitespace(); // Only whitespace allowed, not newlines

            if ($this->check(TokenType::RightBrace)) {
                break;
            }

            $kv = $this->parseKeyValue();
            if ($kv !== null) {
                $items[] = $kv;
            }

            $this->skipWhitespace();

            if (!$this->check(TokenType::RightBrace)) {
                if (!$this->match(TokenType::Comma)) {
                    break;
                }
                // Check for trailing comma - not allowed in inline tables
                $this->skipWhitespace();
                if ($this->check(TokenType::RightBrace)) {
                    $this->error('Trailing comma not allowed in inline table', $this->current()->span);
                }
            }
        }

        $this->expect(TokenType::RightBrace);

        return new InlineTable($items, $start->merge($this->previous()->span));
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

        $this->error("Expected {$type->value}", $this->current()->span);

        return false;
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

    private function skipTriviaInCollection(): void
    {
        while ($this->check(TokenType::Whitespace) || $this->check(TokenType::Comment) || $this->check(TokenType::Newline)) {
            $this->advance();
        }
    }

    private function error(string $message, Span $span, ?string $hint = null): void
    {
        $this->errors[] = new ParseError($message, $span, $hint);
    }

    private function synchronize(): void
    {
        while (!$this->isAtEnd()) {
            if ($this->check(TokenType::Newline)) {
                $this->advance();

                return;
            }
            if ($this->check(TokenType::LeftBracket)) {
                return;
            }
            $this->advance();
        }
    }
}
