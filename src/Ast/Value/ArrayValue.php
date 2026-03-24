<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Lexer\Span;

final class ArrayValue extends AbstractValue
{
    /**
     * @param array<\PhpCollective\Toml\Ast\Value\Value> $items
     * @param \PhpCollective\Toml\Lexer\Span $span
     */
    public function __construct(public array $items, Span $span)
    {
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
