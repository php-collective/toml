<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Lexer\Span;

final class IntegerValue extends AbstractValue
{
    public function __construct(
        public int $value,
        public IntegerBase $base,
        Span $span,
    ) {
        parent::__construct($span);
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
