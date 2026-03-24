<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Lexer;

final readonly class Span
{
    public function __construct(
        public int $start,
        public int $end,
        public int $line,
        public int $column,
    ) {
    }

    public function length(): int
    {
        return $this->end - $this->start;
    }

    public function merge(Span $other): self
    {
        return new self(
            min($this->start, $other->start),
            max($this->end, $other->end),
            min($this->line, $other->line),
            $this->start <= $other->start ? $this->column : $other->column,
        );
    }
}
