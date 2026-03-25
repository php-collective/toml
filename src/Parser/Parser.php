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

    private bool $preserveTrivia;

    private string $input = '';

    public function __construct(bool $preserveTrivia = false)
    {
        $this->preserveTrivia = $preserveTrivia;
    }

    public function parse(string $input): Document
    {
        $this->input = $input;
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
                    if ($this->preserveTrivia) {
                        $table->setLeadingTrivia($leadingTrivia);
                        $table->setTrailingTrivia($this->collectTrailingTrivia());
                    }
                    $doc->items[] = $table;
                    $currentTable = $table;
                }
            } elseif ($token->is(TokenType::BareKey, TokenType::BasicString, TokenType::LiteralString)) {
                $kv = $this->parseKeyValue();
                if ($kv !== null) {
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
                $this->error("Invalid token: `{$token->value}`", $token->span);
                $this->synchronize();
            } else {
                $this->error("Unexpected token: `{$token->type->value}`", $token->span);
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
                $this->error("Invalid token: `{$token->value}`", $token->span);
            } else {
                $this->error('Expected value', $token->span);
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
            } else {
                $this->error('Expected key', $token->span);

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
            if ($value !== null) {
                if ($this->preserveTrivia) {
                    $value->setLeadingTrivia($nextLeadingTrivia);
                }
                $items[] = $value;
            }

            $trailingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
            if (!$this->preserveTrivia) {
                $this->skipTriviaInCollection();
            }

            if (!$this->check(TokenType::RightBracket)) {
                if (!$this->match(TokenType::Comma)) {
                    break;
                }

                if ($value !== null && $this->preserveTrivia) {
                    $value->setTrailingTrivia($trailingTrivia);
                }

                $nextLeadingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
                if ($this->check(TokenType::RightBracket)) {
                    $hasTrailingComma = true;
                    $closingTrivia = $nextLeadingTrivia;

                    break;
                }
            } elseif ($value !== null && $this->preserveTrivia) {
                $value->setTrailingTrivia($trailingTrivia);
            }
        }

        $this->expect(TokenType::RightBracket);
        $span = $start->merge($this->previous()->span);

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
        $start = $this->current()->span;
        $this->advance(); // skip {

        $items = [];
        $openingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
        $closingTrivia = [];
        $hasTrailingComma = false;
        $nextLeadingTrivia = $openingTrivia;

        while (!$this->check(TokenType::RightBrace) && !$this->isAtEnd()) {
            if (!$this->preserveTrivia) {
                $this->skipTriviaInCollection();
            }

            if ($this->check(TokenType::RightBrace)) {
                if ($items === []) {
                    $closingTrivia = $nextLeadingTrivia;
                }

                break;
            }

            $kv = $this->parseKeyValue();
            if ($kv !== null) {
                if ($this->preserveTrivia) {
                    $kv->setLeadingTrivia($nextLeadingTrivia);
                }
                $items[] = $kv;
            }

            $trailingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
            if (!$this->preserveTrivia) {
                $this->skipTriviaInCollection();
            }

            if (!$this->check(TokenType::RightBrace)) {
                if (!$this->match(TokenType::Comma)) {
                    break;
                }
                if ($kv !== null && $this->preserveTrivia) {
                    $kv->setTrailingTrivia($trailingTrivia);
                }

                $nextLeadingTrivia = $this->preserveTrivia ? $this->collectCollectionTrivia() : [];
                if ($this->check(TokenType::RightBrace)) {
                    $hasTrailingComma = true;
                    $closingTrivia = $nextLeadingTrivia;

                    break;
                }
            } elseif ($kv !== null && $this->preserveTrivia) {
                $kv->setTrailingTrivia($trailingTrivia);
            }
        }

        $this->expect(TokenType::RightBrace);
        $span = $start->merge($this->previous()->span);

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
