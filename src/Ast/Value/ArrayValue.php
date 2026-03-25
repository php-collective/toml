<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Lexer\Span;

final class ArrayValue extends AbstractValue
{
    /**
     * @param array<\PhpCollective\Toml\Ast\Value\Value> $items
     * @param \PhpCollective\Toml\Lexer\Span $span
     * @param array<\PhpCollective\Toml\Ast\Trivia> $openingTrivia
     * @param array<\PhpCollective\Toml\Ast\Trivia> $closingTrivia
     * @param bool $hasTrailingComma
     * @param int|null $originalItemCount
     * @param string $raw
     */
    public function __construct(
        public array $items,
        Span $span,
        public array $openingTrivia = [],
        public array $closingTrivia = [],
        public bool $hasTrailingComma = false,
        public ?int $originalItemCount = null,
        public string $raw = '',
    ) {
        parent::__construct($span);
    }

    /**
     * @return array<mixed>
     */
    public function getValue(): array
    {
        return array_map(fn (Value $v) => $v->getValue(), $this->items);
    }
}
