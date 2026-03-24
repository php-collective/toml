<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast;

use PhpCollective\Toml\Ast\Value\Value;
use PhpCollective\Toml\Lexer\Span;

final class KeyValue extends AbstractNode
{
    public function __construct(
        public Key $key,
        public Value $value,
        Span $span,
    ) {
        parent::__construct($span);
    }
}
