<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast;

use PhpCollective\Toml\Lexer\Span;

final class Trivia
{
    public function __construct(
        public TriviaKind $kind,
        public string $value,
        public Span $span,
    ) {
    }
}
