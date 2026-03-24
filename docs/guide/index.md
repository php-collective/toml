# Getting Started

toml-php is a PHP parser and encoder for [TOML](https://toml.io/), a minimal configuration file format that's easy to read and write.

## Installation

Install via Composer:

```bash
composer require php-collective/toml
```

**Requirements:** PHP 8.2+

## Quick Start

### Decoding TOML

```php
use PhpCollective\Toml\Toml;

$config = Toml::decode(<<<'TOML'
[database]
host = "localhost"
port = 5432
enabled = true

[database.credentials]
username = "admin"
password = "secret"
TOML);

echo $config['database']['host'];        // "localhost"
echo $config['database']['port'];        // 5432
echo $config['database']['credentials']['username']; // "admin"
```

### Decoding from File

```php
$config = Toml::decodeFile('/path/to/config.toml');
```

### Encoding to TOML

```php
$toml = Toml::encode([
    'server' => [
        'host' => '0.0.0.0',
        'port' => 8080,
        'ssl' => true,
    ],
    'limits' => [
        'max_connections' => 100,
        'timeout' => 30.5,
    ],
]);
```

Output:

```toml
[server]
host = "0.0.0.0"
port = 8080
ssl = true

[limits]
max_connections = 100
timeout = 30.5
```

## Error Handling

### Throwing on Errors (Default)

```php
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\Exception\ParseException;

try {
    $config = Toml::decode($invalidToml);
} catch (ParseException $e) {
    echo $e->getMessage();
    // Includes formatted error with line/column info
}
```

### Collecting Multiple Errors (Tooling)

For IDE integrations or linters, use `tryParse()` to collect all errors:

```php
$result = Toml::tryParse($tomlString);

if ($result->isValid()) {
    $config = $result->getValue();
} else {
    foreach ($result->getErrors() as $error) {
        echo $error->format($tomlString);
        // Parse error: unterminated string
        //
        //   3 | name = "value
        //     |        ^
        //   4 | other = 123
        //
        // Hint: Did you forget to close the string with "?
    }
}
```

## AST Access

For advanced use cases, parse to an AST:

```php
$document = Toml::parse($tomlString);

foreach ($document->items as $item) {
    if ($item instanceof \PhpCollective\Toml\Ast\Table) {
        echo "Table: " . implode('.', $item->key->parts) . "\n";
    }
}
```

## TOML 1.1 Features

This library supports TOML 1.1 enhancements:

- **Optional seconds** in time values: `07:32` instead of `07:32:00`
- **Hex escapes**: `\xHH` for 2-digit hex codes
- **Escape character**: `\e` for the escape character (0x1B)
- **Space separator** in datetime: `1979-05-27 07:32:00` alongside `T`

## Next Steps

- [Syntax Reference](./syntax) - Learn TOML syntax
- [Error Handling](./error-handling) - Detailed error handling guide
- [AST Access](./ast) - Working with the abstract syntax tree
- [Encoding](./encoding) - Encoding PHP arrays to TOML
