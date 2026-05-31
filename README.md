# PHP Toml

[![CI](https://img.shields.io/github/actions/workflow/status/php-collective/toml/ci.yml?branch=master&style=flat-square)](https://github.com/php-collective/toml/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/php-collective/toml?style=flat-square)](https://packagist.org/packages/php-collective/toml)
[![Total Downloads](https://img.shields.io/packagist/dt/php-collective/toml?style=flat-square)](https://packagist.org/packages/php-collective/toml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat-square)](https://phpstan.org/)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.2-8892BF.svg?style=flat-square)](https://php.net)
[![Software License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE)

A [TOML](https://toml.io/) (v1.0 and v1.1) parser and encoder for PHP with AST access and collected parse errors.

**[Documentation](https://php-collective.github.io/toml/)**

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

## Demo

- [Interactive Playground](https://sandbox.dereuromark.de/sandbox/toml) - Full-featured sandbox with all options.

## Quick Start

```php
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\TomlVersion;

// Decode TOML to PHP array
$config = Toml::decode(<<<'TOML'
[database]
host = "localhost"
port = 5432
TOML);

echo $config['database']['host']; // "localhost"

// Opt into strict TOML 1.0 parsing/decoding
$strict = Toml::decode('time = 07:32:00', TomlVersion::V10);

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
use PhpCollective\Toml\Encoder\DocumentFormattingMode;
use PhpCollective\Toml\Encoder\EncoderOptions;
use PhpCollective\Toml\Ast\Value\StringStyle;
use PhpCollective\Toml\Ast\Value\StringValue;
use PhpCollective\Toml\TomlVersion;

// Encode to TOML string
$toml = Toml::encode($array);

// Encode directly to file
Toml::encodeFile('/path/to/config.toml', $array);

// With options - e.g. omit nulls instead of throwing
$toml = Toml::encode($array, new EncoderOptions(skipNulls: true));

// Opt into multiline strings for long scalar strings
$toml = Toml::encode($array, new EncoderOptions(multilineThreshold: 80));

// Opt into inline tables for small flat nested arrays
$toml = Toml::encode($array, new EncoderOptions(inlineTableThreshold: 3));

// Strict TOML 1.0 output
$toml = Toml::encode($array, new EncoderOptions(version: TomlVersion::V10));

// Emit integers in hexadecimal (or Octal / Binary)
use PhpCollective\Toml\Ast\Value\IntegerBase;
$toml = Toml::encode(['mask' => 255], new EncoderOptions(integerBase: IntegerBase::Hexadecimal));
// mask = 0xFF

// encodeDocument() is normalized by default
$document = Toml::parse($tomlString, true);
$toml = Toml::encodeDocument($document);

// Encode document directly to file
Toml::encodeDocumentFile('/path/to/output.toml', $document);

// Opt into source-aware formatting for minimal-diff AST re-encoding
$document = Toml::parse($tomlString, true);
$document->items[0]->value = new StringValue('new value');
$toml = Toml::encodeDocument(
    $document,
    new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware),
);
```

`DocumentFormattingMode::SourceAware` is lossless for unchanged parsed regions and uses local fallback rules for edited ones. See the [Compatibility](https://php-collective.github.io/toml/reference/compatibility) page for the exact editing contract.
`skipNulls` lets `encode()` omit nulls instead of throwing.
`multilineThreshold` (`?int`, default `null`) keeps current single-line strings when unset; when set, scalar strings longer than the threshold are encoded as multiline basic strings.
`inlineTableThreshold` (`?int`, default `null`) keeps nested tables as table sections when unset; when set, flat nested arrays with at most that many keys are encoded inline, for example `point = { x = 1, y = 2 }`.
`integerBase` (`IntegerBase`, default `IntegerBase::Decimal`) controls the radix for integers produced by `encode()` (and normalized `encodeDocument()` output). `Hexadecimal`, `Octal`, and `Binary` emit `0x`/`0o`/`0b` literals. Since TOML allows a sign only on decimal integers, negative values always fall back to decimal. Source-aware document re-encoding preserves each integer's original source base regardless of this option.

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
- Inline tables (including multiline inline tables and trailing commas in TOML 1.1)
- Tables and array of tables
- Dotted keys

See the [Support Matrix](https://php-collective.github.io/toml/reference/support-matrix) for current coverage and known gaps.

Versioned behavior is available through `TomlVersion` and `EncoderOptions(version: ...)`. The default remains TOML 1.1-compatible; use `TomlVersion::V10` when you need strict TOML 1.0 parsing or output rules.

For explicit local temporal encoding, use:

- `PhpCollective\Toml\Value\LocalDate`
- `PhpCollective\Toml\Value\LocalTime`
- `PhpCollective\Toml\Value\LocalDateTime`

For per-value integer bases, wrap values in `PhpCollective\Toml\Value\TomlInteger` (e.g. `new TomlInteger(255, IntegerBase::Hexadecimal)` → `0xFF`).

## Comparison with Other PHP Libraries

| Feature | php-collective/toml | Others |
|---------|---------------------|--------|
| Error Recovery | Yes | No |
| Multiple Errors | Yes | No |
| AST Access | Yes | Limited/No |
| Round-trip formatting preservation | Partial | Varies |

See [Limitations](https://php-collective.github.io/toml/reference/limitations) for details.
