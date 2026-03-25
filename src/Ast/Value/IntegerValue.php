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
        public string $raw = '',
        public ?int $originalValue = null,
        public ?IntegerBase $originalBase = null,
    ) {
        parent::__construct($span);
        $this->originalValue ??= $value;
        $this->originalBase ??= $base;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
