<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast;

use PhpCollective\Toml\Lexer\Span;

final class Key extends AbstractNode
{
    /**
     * @param array<string> $parts
     * @param array<\PhpCollective\Toml\Ast\KeyStyle> $styles
     * @param \PhpCollective\Toml\Lexer\Span $span
     */
    public function __construct(
        public array $parts,
        public array $styles,
        Span $span,
    ) {
        parent::__construct($span);
    }

    public function toString(): string
    {
        return implode('.', $this->parts);
    }
}
