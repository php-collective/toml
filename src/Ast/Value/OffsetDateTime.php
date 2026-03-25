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
        public ?string $originalComparable = null,
    ) {
        parent::__construct($span);
        $this->originalComparable ??= $value->format('Y-m-d\TH:i:s.uP');
    }

    public function getValue(): DateTimeImmutable
    {
        return $this->value;
    }
}
