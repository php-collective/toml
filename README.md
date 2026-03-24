# PHP TOML

A TOML (v1.0 and v1.1) parser and encoder for PHP with AST access and collected parse errors.

## Features

- Strict validation for malformed keys, tables, strings, numbers, and datetimes
- Error recovery with multiple error collection for tooling workflows
- Clean architecture with separate Lexer, Parser, and AST
- Zero required extensions (optional php-ds for performance)
- AST access for analysis or editor integrations

## Requirements

- PHP 8.2 or higher

## Installation

```bash
composer require php-collective/toml
```

## Quick Start

```php
use PhpCollective\Toml\Toml;

// Decode TOML to PHP array
$config = Toml::decode(<<<'TOML'
[database]
host = "localhost"
port = 5432
TOML);

echo $config['database']['host']; // "localhost"

// Encode PHP array to TOML
$toml = Toml::encode([
    'server' => [
        'host' => '0.0.0.0',
        'port' => 8080,
    ],
]);
```

## API Reference

### Decoding

```php
// Decode string - throws ParseException on error
$array = Toml::decode($tomlString);

// Decode file
$array = Toml::decodeFile('/path/to/config.toml');

// Parse without throwing - for tooling
$result = Toml::tryParse($tomlString);
if ($result->isValid()) {
    $array = $result->getValue();
} else {
    foreach ($result->getErrors() as $error) {
        echo $error->format($tomlString);
    }
}

// Parse to AST for analysis
$document = Toml::parse($tomlString);
```

### Encoding

```php
// Encode to TOML
$toml = Toml::encode($array);

// With options
$toml = Toml::encode($array, new EncoderOptions(sortKeys: true));

// Re-encode a parsed document
// Note: comments and original formatting are not preserved yet.
$document = Toml::parse($tomlString);
$document->items[0]->value = new StringValue('new value');
$toml = Toml::encodeDocument($document);
```

## Error Handling

The parser provides detailed error messages:

```
Parse error: unterminated string

  3 | name = "value
    |        ^
  4 | other = 123

Hint: Did you forget to close the string with "?
```

For tooling, use `tryParse()` to collect all errors:

```php
$result = Toml::tryParse($input);
foreach ($result->getErrors() as $error) {
    // $error->message - Error description
    // $error->span    - Position (line, column, offset)
    // $error->hint    - Optional suggestion
}
```

## Supported Syntax

The library supports TOML 1.0 and 1.1 features including:

- All string types (basic, literal, multi-line)
- Integers (decimal, hex, octal, binary)
- Floats (including inf, nan)
- Booleans
- Dates and times (offset, local datetime, local date, local time)
- Arrays (with trailing commas allowed)
- Inline tables (trailing commas rejected per spec)
- Tables and array of tables
- Dotted keys

### TOML 1.1 Features

- Optional seconds in datetime/time values
- `\xHH` escape sequences (2-digit hex)
- `\e` escape sequence (escape character)
- Space as datetime separator (alongside 'T')

## Limitations

- **Integer range**: Integers are parsed using PHP's native `int` type. Values exceeding `PHP_INT_MAX` (typically 9223372036854775807 on 64-bit systems) will be silently clamped. If you need arbitrary precision integers, consider post-processing with GMP.
- **Round-trip preservation**: Comments and original formatting are not yet preserved when re-encoding.

## Comparison with Other PHP Libraries

| Feature | php-collective/toml | Others |
|---------|---------------------|--------|
| TOML Version | 1.1 | 1.0 or older |
| Error Recovery | Yes | No |
| Multiple Errors | Yes | No |
| AST Access | Yes | Limited/No |
| Round-trip formatting preservation | Not yet | Varies |
| PHP 8.2+ Features | Yes | Varies |

## License

MIT License. See [LICENSE](LICENSE) for details.
