<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Exception;

use PhpCollective\Toml\Lexer\Span;

class ParseException extends TomlException
{
    public function __construct(
        string $message,
        public readonly ?Span $span = null,
        public readonly ?string $hint = null,
    ) {
        parent::__construct($message);
    }
}
