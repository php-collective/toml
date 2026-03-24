<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

enum StringStyle: string
{
    case Basic = 'basic';
    case Literal = 'literal';
    case MultiLineBasic = 'ml_basic';
    case MultiLineLiteral = 'ml_literal';
}
