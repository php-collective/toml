<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Lexer\Span;

final class StringValue extends AbstractValue
{
    public function __construct(
        public string $value,
        public StringStyle $style,
        Span $span,
        public string $raw = '',
        public ?string $originalValue = null,
        public ?StringStyle $originalStyle = null,
    ) {
        parent::__construct($span);
        $this->originalValue ??= $value;
        $this->originalStyle ??= $style;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
