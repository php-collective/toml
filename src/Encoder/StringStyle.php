<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Encoder;

enum StringStyle: string
{
    case Basic = 'basic';
    case Literal = 'literal';
    case MultiLineBasic = 'multiline_basic';
    case MultiLineLiteral = 'multiline_literal';
}
