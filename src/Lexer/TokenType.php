<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Lexer;

enum TokenType: string
{
    // Structural
    case LeftBracket = '[';
    case RightBracket = ']';
    case LeftBrace = '{';
    case RightBrace = '}';
    case Equals = '=';
    case Comma = ',';
    case Dot = '.';
    case Newline = 'newline';

    // Keys and strings
    case BareKey = 'bare_key';
    case BasicString = 'basic_string';
    case LiteralString = 'literal_string';
    case MultiLineBasicString = 'ml_basic_string';
    case MultiLineLiteralString = 'ml_literal_string';

    // Numbers
    case Integer = 'integer';
    case Float = 'float';

    // Other values
    case Boolean = 'boolean';
    case OffsetDateTime = 'offset_datetime';
    case LocalDateTime = 'local_datetime';
    case LocalDate = 'local_date';
    case LocalTime = 'local_time';

    // Trivia
    case Comment = 'comment';
    case Whitespace = 'whitespace';

    // Control
    case Eof = 'eof';
    case Invalid = 'invalid';
}
