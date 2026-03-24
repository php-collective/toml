<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Value;

interface TomlValue
{
    public function toTomlLiteral(): string;
}
