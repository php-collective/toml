<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Lexer;

final class Token
{
    public function __construct(
        public readonly TokenType $type,
        public readonly string $value,
        public readonly mixed $parsed,
        public readonly Span $span,
    ) {
    }

    public function is(TokenType ...$types): bool
    {
        return in_array($this->type, $types, true);
    }
}
