<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Lexer\Span;

final class LocalTime extends AbstractValue
{
    public function __construct(public string $value, Span $span)
    {
        parent::__construct($span);
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
