<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast;

enum KeyStyle: string
{
    case Bare = 'bare';
    case Basic = 'basic';
    case Literal = 'literal';
}
