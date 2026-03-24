# Encoding

toml-php can encode PHP arrays and objects to TOML format.

## Basic Encoding

```php
use PhpCollective\Toml\Toml;

$toml = Toml::encode([
    'title' => 'My App',
    'database' => [
        'host' => 'localhost',
        'port' => 5432,
    ],
]);
```

Output:

```toml
title = "My App"

[database]
host = "localhost"
port = 5432
```

## Supported Types

| PHP Type | TOML Output |
|----------|-------------|
| `string` | Basic string `"value"` |
| `int` | Integer `42` |
| `float` | Float `3.14` |
| `bool` | Boolean `true`/`false` |
| `array` (list) | Array `[1, 2, 3]` |
| `array` (assoc) | Table `[section]` |
| `DateTimeInterface` | Datetime `2024-01-15T10:30:00Z` |
| `null` | *(skipped)* |

## Encoder Options

```php
use PhpCollective\Toml\Encoder\EncoderOptions;

$options = new EncoderOptions(
    sortKeys: true,        // Sort keys alphabetically
    inlineThreshold: 3,    // Inline tables with ≤3 keys
);

$toml = Toml::encode($data, $options);
```

### Sort Keys

```php
$toml = Toml::encode([
    'zebra' => 1,
    'apple' => 2,
    'mango' => 3,
], new EncoderOptions(sortKeys: true));
```

Output:

```toml
apple = 2
mango = 3
zebra = 1
```

### Inline Tables

Small associative arrays can be rendered as inline tables:

```php
$toml = Toml::encode([
    'point' => ['x' => 1, 'y' => 2],
], new EncoderOptions(inlineThreshold: 3));
```

Output:

```toml
point = { x = 1, y = 2 }
```

## Array of Tables

Sequential arrays of associative arrays become array of tables:

```php
$toml = Toml::encode([
    'servers' => [
        ['name' => 'alpha', 'ip' => '10.0.0.1'],
        ['name' => 'beta', 'ip' => '10.0.0.2'],
    ],
]);
```

Output:

```toml
[[servers]]
name = "alpha"
ip = "10.0.0.1"

[[servers]]
name = "beta"
ip = "10.0.0.2"
```

## DateTime Handling

```php
$toml = Toml::encode([
    'created' => new DateTimeImmutable('2024-01-15T10:30:00Z'),
    'updated' => new DateTime('2024-01-15T10:30:00', new DateTimeZone('America/New_York')),
]);
```

Output:

```toml
created = 2024-01-15T10:30:00Z
updated = 2024-01-15T10:30:00-05:00
```

## Special Float Values

```php
$toml = Toml::encode([
    'infinity' => INF,
    'negative_infinity' => -INF,
    'not_a_number' => NAN,
]);
```

Output:

```toml
infinity = inf
negative_infinity = -inf
not_a_number = nan
```

## String Escaping

Strings are automatically escaped:

```php
$toml = Toml::encode([
    'message' => "Hello\nWorld",
    'path' => 'C:\Users\name',
]);
```

Output:

```toml
message = "Hello\nWorld"
path = "C:\\Users\\name"
```

## Re-encoding from AST

After parsing and modifying an AST:

```php
$document = Toml::parse($originalToml);

// Modify the AST...

$toml = Toml::encodeDocument($document);
```

::: warning
Currently, `encodeDocument()` normalizes the document. Original formatting (whitespace, comments, key styles) is not preserved. The output is semantically equivalent but may look different.
:::

## Error Handling

Encoding throws `EncodeException` for unsupported types:

```php
use PhpCollective\Toml\Exception\EncodeException;

try {
    $toml = Toml::encode([
        'callback' => fn() => 'hello',  // Closures not supported
    ]);
} catch (EncodeException $e) {
    echo $e->getMessage();
}
```

Unsupported types:
- Closures
- Resources
- Objects without `DateTimeInterface`
- Circular references
