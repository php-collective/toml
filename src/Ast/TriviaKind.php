<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast;

enum TriviaKind: string
{
    case Whitespace = 'whitespace';
    case Comment = 'comment';
    case Newline = 'newline';
}
