<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Lexer\Span;

final class BoolValue extends AbstractValue
{
    public function __construct(public bool $value, Span $span)
    {
        parent::__construct($span);
    }

    public function getValue(): bool
    {
        return $this->value;
    }
}
