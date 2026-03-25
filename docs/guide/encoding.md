# Encoding

PHP Toml encodes PHP arrays to TOML and supports explicit value wrappers for TOML local date/time/datetime literals.

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

## Supported Input Types

| PHP Type | TOML Output |
|----------|-------------|
| `string` | Basic string `"value"` |
| `int` | Integer `42` |
| `float` | Float `3.14` |
| `bool` | Boolean `true` / `false` |
| `array` (list) | Array `[1, 2, 3]` |
| `array` (assoc) | Table or inline table depending on position |
| `DateTimeInterface` | Offset datetime |
| `PhpCollective\Toml\Value\LocalDate` | Local date |
| `PhpCollective\Toml\Value\LocalTime` | Local time |
| `PhpCollective\Toml\Value\LocalDateTime` | Local datetime |

`null` is not supported and throws `EncodeException`.

## Encoder Options

```php
use PhpCollective\Toml\Encoder\EncoderOptions;

$options = new EncoderOptions(
    sortKeys: true,
    newline: "\n",
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

### Custom Newlines

```php
$toml = Toml::encode([
    'name' => 'test',
    'count' => 42,
], new EncoderOptions(newline: "\r\n"));
```

## Date and Time Encoding

### Offset Datetime

Use `DateTimeInterface` for TOML offset datetimes:

```php
$toml = Toml::encode([
    'created' => new DateTimeImmutable('2024-01-15T10:30:00Z'),
]);
```

Output:

```toml
created = 2024-01-15T10:30:00.000000+00:00
```

### Local Date, Time, and DateTime

Use explicit wrappers for TOML local temporal values:

```php
use PhpCollective\Toml\Value\LocalDate;
use PhpCollective\Toml\Value\LocalDateTime;
use PhpCollective\Toml\Value\LocalTime;

$toml = Toml::encode([
    'date' => new LocalDate('2024-03-15'),
    'time' => new LocalTime('10:30:45'),
    'timestamp' => new LocalDateTime('2024-03-15T10:30:45'),
]);
```

Output:

```toml
date = 2024-03-15
time = 10:30:45
timestamp = 2024-03-15T10:30:45
```

Plain PHP strings are always encoded as TOML strings, not temporal literals.

## Array of Tables

Sequential arrays of associative arrays become array-of-tables:

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

Strings are emitted as basic strings with escapes:

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

```php
$document = Toml::parse($originalToml, true);

// Modify the AST...

$toml = Toml::encodeDocument(
    $document,
    new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware),
);
```

::: warning
`encodeDocument()` defaults to normalized output. Use `DocumentFormattingMode::SourceAware` when you want minimal-diff, source-aware behavior for trivia-preserving ASTs.
:::

In `SourceAware` mode, unchanged parsed regions can round-trip losslessly and edited regions follow explicit local fallback rules. Nodes without preserved trivia fall back to canonical local formatting. Compatible key or value edits can still preserve original key/value separator spacing, and compatible dotted-key or table-header edits can keep their original separator spacing even when an individual segment changes style. Inserted inline-table entries encode with single spaces, inserted items in multiline parsed arrays reuse inferred indentation when possible, and edited single-line collections normalize their local delimiter spacing when their shape changes or synthetic replacements are introduced. That fallback is local to the edited collection, so an outer multiline structure can still preserve its layout while a nested single-line collection normalizes.

## Error Handling

Encoding throws `EncodeException` for unsupported values:

```php
use PhpCollective\Toml\Exception\EncodeException;

try {
    $toml = Toml::encode([
        'callback' => fn() => 'hello',
    ]);
} catch (EncodeException $e) {
    echo $e->getMessage();
}
```

Common unsupported values:

- `null`
- closures
- resources
- arbitrary objects that do not implement the TOML value contract
- circular references
