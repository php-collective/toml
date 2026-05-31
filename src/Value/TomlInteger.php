<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Value;

use PhpCollective\Toml\Ast\Value\IntegerBase;

final readonly class TomlInteger implements TomlValue
{
    public function __construct(
        public int $value,
        public IntegerBase $base = IntegerBase::Decimal,
    ) {
    }

    public function toTomlLiteral(): string
    {
        // TOML permits a sign only on decimal integers; hex/octal/binary literals
        // are unsigned, so negative values fall back to decimal output.
        if ($this->base === IntegerBase::Decimal || $this->value < 0) {
            return (string)$this->value;
        }

        return match ($this->base) {
            IntegerBase::Hexadecimal => '0x' . strtoupper(dechex($this->value)),
            IntegerBase::Octal => '0o' . decoct($this->value),
            default => '0b' . decbin($this->value),
        };
    }
}
