<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast;

use PhpCollective\Toml\Lexer\Span;

interface Node
{
    public function getSpan(): Span;

    /**
     * @return array<\PhpCollective\Toml\Ast\Trivia>
     */
    public function getLeadingTrivia(): array;

    /**
     * @return array<\PhpCollective\Toml\Ast\Trivia>
     */
    public function getTrailingTrivia(): array;
}
