<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast;

use PhpCollective\Toml\Lexer\Span;

abstract class AbstractNode implements Node
{
    /**
     * @var array<\PhpCollective\Toml\Ast\Trivia>
     */
    protected array $leadingTrivia = [];

    /**
     * @var array<\PhpCollective\Toml\Ast\Trivia>
     */
    protected array $trailingTrivia = [];

    public function __construct(protected Span $span)
    {
    }

    public function getSpan(): Span
    {
        return $this->span;
    }

    public function getLeadingTrivia(): array
    {
        return $this->leadingTrivia;
    }

    public function getTrailingTrivia(): array
    {
        return $this->trailingTrivia;
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Trivia> $trivia
     */
    public function setLeadingTrivia(array $trivia): void
    {
        $this->leadingTrivia = $trivia;
    }

    /**
     * @param array<\PhpCollective\Toml\Ast\Trivia> $trivia
     */
    public function setTrailingTrivia(array $trivia): void
    {
        $this->trailingTrivia = $trivia;
    }
}
