<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Lexer\Span;

final class BoolValue extends AbstractValue
{
    public function __construct(
        public bool $value,
        Span $span,
        public string $raw = '',
        public ?bool $originalValue = null,
    ) {
        parent::__construct($span);
        $this->originalValue ??= $value;
    }

    public function getValue(): bool
    {
        return $this->value;
    }
}
