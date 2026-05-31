# Limitations

Known limitations of PHP Toml.

## Integer Range

Integers are parsed using PHP's native `int` type. Values exceeding `PHP_INT_MAX` (typically 9223372036854775807 on 64-bit systems) will be clamped silently.

```toml
# May be clamped on some systems
big_number = 9999999999999999999
```

**Workaround:** If you need arbitrary precision integers, post-process the AST or parsed values with GMP:

```php
$document = Toml::parse($input);
// Find integer nodes and convert with gmp_init()
```

## DateTime Precision

PHP's `DateTimeImmutable` supports microsecond precision (6 digits). Nanosecond values (9 digits) are truncated:

```toml
# Full precision
precise = 2024-01-15T10:30:00.123456Z

# Truncated to microseconds
nano = 2024-01-15T10:30:00.123456789Z
# Becomes: 2024-01-15T10:30:00.123456Z
```

## Comment Preservation

Comments can be preserved by `encodeDocument()` when the AST was parsed with trivia enabled:

```toml
# This comment can be preserved
key = "value"  # This too
```

Example:

```php
$document = Toml::parse($input, true);
$toml = Toml::encodeDocument(
    $document,
    new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware),
);
```

Plain `encode()` still emits normalized TOML and does not preserve source comments.

## Formatting Preservation

Formatting preservation is strongest when re-encoding a trivia-preserving AST in `DocumentFormattingMode::SourceAware`.

Unchanged parsed regions can round-trip losslessly. Edited regions keep local formatting where the AST carries consistent evidence and otherwise fall back to canonical local formatting.

```toml
# Original multiline array
values = [
  1,
  2,
]

# After adding item 3 - multiline format preserved
values = [
  1,
  2,
  3,
]
```

For the exact source-aware editing contract, see [Compatibility](https://php-collective.github.io/toml/reference/compatibility).

## Local DateTime Types

Local datetime types (`LocalDateTime`, `LocalDate`, `LocalTime`) are returned as strings, not `DateTimeImmutable`:

```php
$config = Toml::decode('date = 1979-05-27');
// $config['date'] is string "1979-05-27", not DateTimeImmutable
```

This is because local dates have no timezone information, making `DateTimeImmutable` semantically incorrect.

**Workaround:**

```php
$date = DateTimeImmutable::createFromFormat('Y-m-d', $config['date']);
```

For encoding local temporal TOML literals, use explicit wrappers:

```php
use PhpCollective\Toml\Value\LocalDate;

$toml = Toml::encode([
    'date' => new LocalDate('2024-03-15'),
]);
```

## Null Values

TOML has no null type. By default, PHP `null` values throw `EncodeException` during encoding:

```php
Toml::encode([
    'present' => 'value',
    'missing' => null,
]); // Throws EncodeException
```

**Workaround:** Use the `skipNulls` option to silently omit null values:

```php
use PhpCollective\Toml\Encoder\EncoderOptions;

$toml = Toml::encode([
    'present' => 'value',
    'missing' => null,
], new EncoderOptions(skipNulls: true));
// Output: present = "value"
```

This also works for nulls in arrays and inline tables.

## Object Encoding

`DateTimeInterface` objects, explicit TOML value wrappers, and `stdClass` are supported for direct object encoding. `stdClass` is treated as a table (see [Empty Tables](#empty-tables)). Any other object throws `EncodeException` and must be converted to an array first:

```php
// This throws EncodeException
Toml::encode(['obj' => new MyClass()]);

// Convert to array first
Toml::encode(['obj' => (array)$myObject]);
```

## Empty Tables

PHP arrays cannot distinguish an empty table from an empty array, so `encode()` emits an empty PHP array as an empty TOML array (`key = []`), never as an empty table (`[key]`).

```php
Toml::encode(['settings' => []]);
// Output: settings = []   (not an empty [settings] table)
```

**Workaround:** Use `stdClass` to force table output. An empty `stdClass` becomes an empty table header, and a populated one is encoded just like an associative array:

```php
Toml::encode(['settings' => new stdClass()]);
// Output: [settings]

$server = new stdClass();
$server->host = 'localhost';
Toml::encode(['server' => $server]);
// Output: [server]
//         host = "localhost"
```

Inside an inline context (an array element, or under `inlineTableThreshold`), a `stdClass` is emitted as an inline table, so an empty one becomes `{}`. A pure decode/encode round trip of an empty table is still not byte-preserving in normalized mode, because decoding yields a plain empty array; use `DocumentFormattingMode::SourceAware` to preserve the original `[]` vs `{}` form.

## Recursive Structures

Circular references throw `EncodeException`:

```php
$a = ['key' => 'value'];
$a['self'] = &$a;  // Circular reference

Toml::encode($a);  // Throws EncodeException
```

## Maximum Nesting

While there's no explicit limit, deeply nested structures may cause stack issues:

```toml
[a.b.c.d.e.f.g.h.i.j.k.l.m.n.o.p.q.r.s.t.u.v.w.x.y.z]
key = "very deep"
```

## Future Improvements

The largest remaining improvement areas are:

- Optional GMP-backed handling for very large integers
- Streaming or incremental parsing for very large files
