<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Value;

use InvalidArgumentException;
use PhpCollective\Toml\Support\TemporalValidator;

final readonly class LocalTime implements TomlValue
{
    public function __construct(public string $value)
    {
        if (!TemporalValidator::isValidLocalTime($value)) {
            throw new InvalidArgumentException("Invalid TOML local time: {$value}");
        }
    }

    public function toTomlLiteral(): string
    {
        return $this->value;
    }
}
