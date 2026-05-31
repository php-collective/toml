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
| `PhpCollective\Toml\Value\TomlInteger` | Integer in a chosen base, e.g. `0xFF` |
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
use PhpCollective\Toml\Encoder\ArrayStyle;

$options = new EncoderOptions(
    sortKeys: true,
    arrayStyle: ArrayStyle::Multiline,
);

$toml = Toml::encode($data, $options);
```

See [API Reference](/reference/api#encoderoptions) for the full options list.

### Diff-Friendly Preset

For files under version control, use the `diffFriendly()` preset to minimize diffs when making changes:

```php
$toml = Toml::encode($data, EncoderOptions::diffFriendly());
```

This preset enables:
- **Trailing commas** in arrays (adding items doesn't modify the previous line)
- **Auto multiline arrays** (arrays with more than 3 items use one item per line)

Example output:

```toml
small = [1, 2,]
large = [
    1,
    2,
    3,
    4,
]
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

### Skip Null Values

By default, `null` values throw `EncodeException`. Use `skipNulls` to silently omit them:

```php
$toml = Toml::encode([
    'name' => 'Alice',
    'email' => null,  // Will be omitted
    'age' => 30,
], new EncoderOptions(skipNulls: true));
```

Output:

```toml
name = "Alice"
age = 30
```

This also filters nulls from arrays and inline tables.

### Multiline String Threshold

`multilineThreshold` is an opt-in `?int` option. The default `null` keeps all scalar strings in the current single-line basic string form. When set, strings longer than the threshold use TOML multiline basic strings:

```php
$toml = Toml::encode([
    'description' => 'a longer block of text',
], new EncoderOptions(multilineThreshold: 10));
```

Output:

```toml
description = """
a longer block of text"""
```

### Inline Table Threshold

`inlineTableThreshold` is an opt-in `?int` option. The default `null` keeps nested associative arrays as table sections. When set, flat nested arrays with at most that many keys encode on the parent key line as inline tables:

```php
$toml = Toml::encode([
    'point' => [
        'x' => 1,
        'y' => 2,
    ],
], new EncoderOptions(inlineTableThreshold: 3));
```

Output:

```toml
point = { x = 1, y = 2 }
```

Nested tables and arrays of tables still use table headers.

### Integer Grouping

Add underscores to large integers for readability:

```php
$toml = Toml::encode([
    'population' => 1000000,
], new EncoderOptions(integerGrouping: true));
```

Output:

```toml
population = 1_000_000
```

### Integer Base

`integerBase` controls the radix used for integers emitted by `encode()` (and normalized `encodeDocument()` output). The default is `IntegerBase::Decimal`; `Hexadecimal`, `Octal`, and `Binary` produce `0x`/`0o`/`0b` literals:

```php
use PhpCollective\Toml\Ast\Value\IntegerBase;

$toml = Toml::encode([
    'mask' => 255,
    'mode' => 493,
], new EncoderOptions(integerBase: IntegerBase::Hexadecimal));
```

Output:

```toml
mask = 0xFF
mode = 0x1ED
```

::: tip
TOML permits a sign only on decimal integers, so negative values always fall back to decimal regardless of `integerBase`. Source-aware document re-encoding preserves each integer's original source base instead of applying this option.
:::

`integerBase` applies one radix to every integer. For per-value control — e.g. a hexadecimal mask next to a decimal count — wrap individual values in `TomlInteger`:

```php
use PhpCollective\Toml\Ast\Value\IntegerBase;
use PhpCollective\Toml\Value\TomlInteger;

$toml = Toml::encode([
    'mask' => new TomlInteger(255, IntegerBase::Hexadecimal),
    'count' => 10,
]);
```

Output:

```toml
mask = 0xFF
count = 10
```

### Trailing Commas

Add trailing commas to inline arrays:

```php
$toml = Toml::encode([
    'items' => [1, 2, 3],
], new EncoderOptions(trailingComma: true));
```

Output:

```toml
items = [1, 2, 3,]
```

### Dotted Keys

Use dotted keys instead of table sections:

```php
$toml = Toml::encode([
    'database' => [
        'host' => 'localhost',
        'port' => 5432,
    ],
], new EncoderOptions(dottedKeys: true));
```

Output:

```toml
database.host = "localhost"
database.port = 5432
```

### Array Style

Control array formatting with `ArrayStyle`:

```php
use PhpCollective\Toml\Encoder\ArrayStyle;

// Multiline arrays (one item per line)
$toml = Toml::encode([
    'ports' => [8080, 8081, 8082],
], new EncoderOptions(arrayStyle: ArrayStyle::Multiline));
```

Output:

```toml
ports = [
    8080,
    8081,
    8082,
]
```

Use `ArrayStyle::Auto` to automatically switch to multiline when arrays exceed a threshold:

```php
$toml = Toml::encode([
    'few' => [1, 2],
    'many' => [1, 2, 3, 4, 5],
], new EncoderOptions(
    arrayStyle: ArrayStyle::Auto,
    arrayAutoThreshold: 3,
));
```

Output:

```toml
few = [1, 2]
many = [
    1,
    2,
    3,
    4,
    5,
]
```

Customize indentation with `indent`:

```php
$toml = Toml::encode([
    'items' => [1, 2, 3],
], new EncoderOptions(
    arrayStyle: ArrayStyle::Multiline,
    indent: '  ',  // 2 spaces
));
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

In `SourceAware` mode, unchanged parsed regions can round-trip losslessly and edited regions follow local fallback rules. Compatible key, value, dotted-key, and table-header edits can often keep their original separator spacing, and single-line collections preserve local delimiter style when the AST has consistent local formatting evidence. If not, the encoder canonicalizes locally.

For the full editing contract and compatibility boundary, see [Compatibility](https://php-collective.github.io/toml/reference/compatibility).

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
