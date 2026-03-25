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
     * @param string $raw
     * @param array<string>|null $originalParts
     * @param array<\PhpCollective\Toml\Ast\KeyStyle>|null $originalStyles
     */
    public function __construct(
        public array $parts,
        public array $styles,
        Span $span,
        public string $raw = '',
        public ?array $originalParts = null,
        public ?array $originalStyles = null,
    ) {
        parent::__construct($span);
        $this->originalParts ??= $parts;
        $this->originalStyles ??= $styles;
    }

    public function toString(): string
    {
        return implode('.', $this->parts);
    }
}
