<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Lexer;

final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public mixed $parsed,
        public Span $span,
    ) {
    }

    public function is(TokenType ...$types): bool
    {
        return in_array($this->type, $types, true);
    }
}
