<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Lexer\Span;

final class LocalTime extends AbstractValue
{
    public function __construct(
        public string $value,
        Span $span,
        public string $raw = '',
        public ?string $originalValue = null,
    ) {
        parent::__construct($span);
        $this->originalValue ??= $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
