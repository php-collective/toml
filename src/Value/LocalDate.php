<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Value;

use InvalidArgumentException;
use PhpCollective\Toml\Support\TemporalValidator;

final class LocalDate implements TomlValue
{
    public function __construct(public readonly string $value)
    {
        if (!TemporalValidator::isValidLocalDate($value)) {
            throw new InvalidArgumentException("Invalid TOML local date: `{$value}`");
        }
    }

    public function toTomlLiteral(): string
    {
        return $this->value;
    }
}
