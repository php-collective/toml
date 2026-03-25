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

Formatting is preserved with the following behavior during AST re-encoding:

**Unchanged regions** preserve their original formatting:
- Leading and trailing trivia (comments, whitespace)
- Key quoting style
- String style (basic, literal, multiline)
- Table ordering
- Collection layout

**Edited regions** use source-aware local fallback rules:
- Multiline arrays preserve their indentation style
- Compatible key and value edits can preserve original separator spacing
- Single-line arrays and inline tables preserve local delimiter style when the parsed collection exposes a consistent style
- When no consistent local style is available, single-line arrays and inline tables fall back to canonical formatting

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

Only `DateTimeInterface` objects and explicit TOML value wrappers are supported for direct object encoding. Other objects must be converted to arrays:

```php
// This throws EncodeException
Toml::encode(['obj' => new MyClass()]);

// Convert to array first
Toml::encode(['obj' => (array)$myObject]);
```

## Recursive Structures

Circular references throw `EncodeException`:

```php
$a = ['key' => 'value'];
$a['self'] = &$a;  // Circular reference

Toml::encode($a);  // Throws EncodeException
```

## Control Characters

Control characters (except tab) must be escaped in basic strings per TOML spec. Invalid control characters will cause parse errors:

```toml
# Invalid - contains literal control character
invalid = "hello^Aworld"  # ^A = 0x01
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
