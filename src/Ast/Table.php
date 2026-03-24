<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast;

use PhpCollective\Toml\Lexer\Span;

final class Table extends AbstractNode
{
    /**
     * @var array<\PhpCollective\Toml\Ast\KeyValue>
     */
    public array $items = [];

    public function __construct(
        public Key $key,
        public bool $isArrayTable,
        Span $span,
    ) {
        parent::__construct($span);
    }
}
