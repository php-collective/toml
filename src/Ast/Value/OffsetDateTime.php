<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use DateTimeImmutable;
use PhpCollective\Toml\Lexer\Span;

final class OffsetDateTime extends AbstractValue
{
    public function __construct(
        public DateTimeImmutable $value,
        public string $raw,
        Span $span,
    ) {
        parent::__construct($span);
    }

    public function getValue(): DateTimeImmutable
    {
        return $this->value;
    }
}
