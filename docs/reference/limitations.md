# Limitations

Known limitations of toml-php.

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
$toml = Toml::encodeDocument($document);
```

Plain `encode()` still emits normalized TOML and does not preserve source comments.

## Formatting Preservation

Original formatting is only partially preserved during AST re-encoding:

- Leading and trailing trivia on document items and table entries can be preserved
- Key quoting style can be preserved
- String style can be preserved
- Table ordering can be preserved
- Collection-local layout for parsed arrays and inline tables can now be preserved
- Unedited documents can still lose formatting in unsupported cases such as delimiter-adjacent trivia that is not represented in the AST

```toml
# Original
"my-key" = { x = 1, y = 2 }
```

```toml
# After re-encode (usually preserved for parsed inline tables)
"my-key" = { x = 1, y = 2 }
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

TOML has no null type. PHP `null` values throw `EncodeException` during encoding:

```php
Toml::encode([
    'present' => 'value',
    'missing' => null,
]); // Throws EncodeException
```

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

- Full formatting preservation rather than partial round-trip editing
- Optional GMP-backed handling for very large integers
- Streaming or incremental parsing for very large files
- Broader conformance-style fixture coverage at larger scale, especially for edited-document workflows
