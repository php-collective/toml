<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Lexer\Span;

final class FloatValue extends AbstractValue
{
    public function __construct(
        public float $value,
        Span $span,
        public string $raw = '',
        public ?float $originalValue = null,
    ) {
        parent::__construct($span);
        $this->originalValue ??= $value;
    }

    public function getValue(): float
    {
        return $this->value;
    }

    public function isSpecial(): bool
    {
        return is_infinite($this->value) || is_nan($this->value);
    }
}
