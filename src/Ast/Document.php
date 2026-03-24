<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast;

use PhpCollective\Toml\Lexer\Span;

final class Document extends AbstractNode
{
    /**
     * @var array<\PhpCollective\Toml\Ast\Table|\PhpCollective\Toml\Ast\KeyValue>
     */
    public array $items = [];

    public function __construct(?Span $span = null)
    {
        parent::__construct($span ?? new Span(0, 0, 1, 0));
    }
}
