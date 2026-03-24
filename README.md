# PHP TOML

A TOML (v1.0 and v1.1) parser and encoder for PHP with AST access and collected parse errors.

## Features

- Strict validation for malformed keys, tables, strings, numbers, and datetimes
- Error recovery with multiple error collection for tooling workflows
- Clean architecture with separate Lexer, Parser, and AST
- Zero required extensions (optional php-ds for performance)
- AST access for analysis or editor integrations
- Explicit local date/time/datetime value objects for encoding

## Requirements

- PHP 8.2 or higher

## Installation

```bash
composer require php-collective/toml
```

See [docs/reference/support-matrix.md](docs/reference/support-matrix.md) for the current support matrix and known gaps.

For explicit local temporal encoding, use:

- `PhpCollective\Toml\Value\LocalDate`
- `PhpCollective\Toml\Value\LocalTime`
- `PhpCollective\Toml\Value\LocalDateTime`

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

// Parse without exceptions and keep diagnostics + partial AST
$result = Toml::tryParse($tomlString);
$document = $result->getDocument();
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

The library currently supports:

- All string types (basic, literal, multi-line)
- Integers (decimal, hex, octal, binary)
- Floats (including inf, nan)
- Booleans
- Dates and times (offset, local datetime, local date, local time)
- Arrays (including multiline arrays and trailing commas)
- Inline tables (single-line only; trailing commas rejected)
- Tables and array of tables
- Dotted keys

## Limitations

- **Integer range**: Integers are parsed using PHP's native `int` type. Values exceeding `PHP_INT_MAX` (typically 9223372036854775807 on 64-bit systems) will be silently clamped. If you need arbitrary precision integers, consider post-processing with GMP.
- **Round-trip preservation**: `encodeDocument()` can now preserve parsed comments, blank lines, key styles, string styles, and collection-local layout for parsed arrays and inline tables when trivia is available, but it is not yet a full lossless formatter.
- **Temporal encode asymmetry**: Offset datetimes encode from `DateTimeInterface`, but local date/time/datetime values require explicit wrappers instead of plain strings.
- **Support breadth**: See [docs/reference/support-matrix.md](docs/reference/support-matrix.md) for partially supported and not-yet-implemented TOML features.
- **Compatibility**: See [docs/reference/compatibility.md](docs/reference/compatibility.md) and [UPGRADING.md](UPGRADING.md) for API and parser-strictness expectations.

## Comparison with Other PHP Libraries

| Feature | php-collective/toml | Others |
|---------|---------------------|--------|
| Support matrix | Yes | Varies |
| Error Recovery | Yes | No |
| Multiple Errors | Yes | No |
| AST Access | Yes | Limited/No |
| Round-trip formatting preservation | Partial | Varies |
| PHP 8.2+ Features | Yes | Varies |

## License

MIT License. See [LICENSE](LICENSE) for details.
