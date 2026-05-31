<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Parser;

/**
 * Stable, machine-readable classification for parse and semantic errors.
 *
 * The string values are part of the public contract: tooling may switch on them
 * instead of matching against human-readable messages, which are not stable.
 */
enum ParseErrorCode: string
{
    // Generic fallback when no more specific code applies.
    case SyntaxError = 'syntax_error';

    // Syntax (parser).
    case ExpectedKey = 'expected_key';
    case ExpectedValue = 'expected_value';
    case ExpectedToken = 'expected_token';
    case UnexpectedToken = 'unexpected_token';
    case UnterminatedStatement = 'unterminated_statement';
    case UnsupportedVersion = 'unsupported_version';

    // Lexical (token level).
    case InvalidToken = 'invalid_token';
    case InvalidEncoding = 'invalid_encoding';
    case UnterminatedString = 'unterminated_string';
    case InvalidNumber = 'invalid_number';
    case IntegerOutOfRange = 'integer_out_of_range';
    case InvalidDateTime = 'invalid_datetime';

    // Semantic (normalizer).
    case DuplicateKey = 'duplicate_key';
    case DuplicateTable = 'duplicate_table';
    case KeyRedefinition = 'key_redefinition';
    case TableRedefinition = 'table_redefinition';
    case ArrayTableConflict = 'array_table_conflict';
    case ExtendStaticArray = 'extend_static_array';
    case ExtendInlineTable = 'extend_inline_table';
    case DottedKeyConflict = 'dotted_key_conflict';

    /**
     * Classifies an error from its message. Keyed on the stable message stems
     * produced by the parser and normalizer; pinned by tests. Lexer "Invalid
     * token" errors are coded via {@see self::fromInvalidLexeme()} instead,
     * because that message alone does not carry the specific cause.
     */
    public static function fromMessage(string $message): self
    {
        return match (true) {
            str_starts_with($message, 'Duplicate key') => self::DuplicateKey,
            str_starts_with($message, 'Duplicate table') => self::DuplicateTable,
            str_starts_with($message, 'Cannot redefine array table') => self::ArrayTableConflict,
            str_starts_with($message, 'Cannot redefine key') => self::KeyRedefinition,
            str_starts_with($message, 'Cannot redefine table') => self::TableRedefinition,
            str_starts_with($message, 'Cannot extend inline table') => self::ExtendInlineTable,
            str_starts_with($message, 'Cannot extend static array') => self::ExtendStaticArray,
            str_starts_with($message, 'Cannot extend values in static array') => self::ExtendStaticArray,
            str_starts_with($message, 'Cannot add keys to explicitly defined table') => self::DottedKeyConflict,
            str_starts_with($message, 'Cannot define table') => self::DottedKeyConflict,
            str_starts_with($message, 'Invalid UTF-8') => self::InvalidEncoding,
            str_starts_with($message, 'Invalid token') => self::InvalidToken,
            str_starts_with($message, 'Expected key') => self::ExpectedKey,
            str_starts_with($message, 'Expected value') => self::ExpectedValue,
            str_starts_with($message, 'Expected =') => self::ExpectedToken,
            str_starts_with($message, 'Unexpected token') => self::UnexpectedToken,
            str_contains($message, 'TOML 1.0') || str_contains($message, 'TOML 1.1') => self::UnsupportedVersion,
            str_contains($message, 'terminated by newline') || str_starts_with($message, 'Expected newline') => self::UnterminatedStatement,
            str_starts_with($message, 'Expected') => self::ExpectedToken,
            default => self::SyntaxError,
        };
    }

    /**
     * Classifies a lexer "Invalid token" error from the rejected lexeme. The lexer
     * only marks an otherwise-valid integer as invalid on out-of-range overflow,
     * so a bare integer lexeme maps to {@see self::IntegerOutOfRange}.
     */
    public static function fromInvalidLexeme(string $lexeme): self
    {
        if ($lexeme !== '' && ($lexeme[0] === '"' || $lexeme[0] === "'")) {
            // A closed quote means the string terminated but its content was invalid
            // (e.g. a bad escape); only an unclosed quote is genuinely unterminated.
            $terminated = strlen($lexeme) >= 2 && $lexeme[-1] === $lexeme[0];

            return $terminated ? self::InvalidToken : self::UnterminatedString;
        }

        return match (true) {
            // Only a syntactically valid decimal integer (no leading zero, no
            // doubled/edge underscores) that the lexer still rejected is an overflow;
            // malformed numbers like `+01` fall through to InvalidNumber.
            preg_match('/^[+-]?[1-9](_?[0-9])*$/', $lexeme) === 1 => self::IntegerOutOfRange,
            preg_match('/^\d{4}-\d{2}-\d{2}/', $lexeme) === 1 || preg_match('/^\d{2}:\d{2}/', $lexeme) === 1 => self::InvalidDateTime,
            preg_match('/^[+-]?(0[xobXOB])?[0-9a-fA-F._eE+-]+$/', $lexeme) === 1 => self::InvalidNumber,
            default => self::InvalidToken,
        };
    }
}
