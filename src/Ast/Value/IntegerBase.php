<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

enum IntegerBase: string
{
    case Decimal = 'decimal';
    case Hexadecimal = 'hex';
    case Octal = 'octal';
    case Binary = 'binary';
}
