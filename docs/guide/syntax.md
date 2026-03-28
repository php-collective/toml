# TOML Syntax Reference

A complete reference for TOML syntax supported by PHP Toml.

## Comments

```toml
# This is a full-line comment
key = "value"  # This is an inline comment
```

## Keys

### Bare Keys

```toml
key = "value"
bare_key = "value"
bare-key = "value"
1234 = "value"
```

Bare keys may only contain ASCII letters, digits, underscores, and dashes.

### Quoted Keys

```toml
"127.0.0.1" = "value"
"character encoding" = "value"
'key with single quotes' = "value"
"ʎǝʞ" = "value"  # Unicode allowed
```

### Dotted Keys

```toml
# These are equivalent:
physical.color = "orange"

[physical]
color = "orange"
```

Dotted keys create nested tables:

```toml
fruit.apple.color = "red"
# Creates: { "fruit": { "apple": { "color": "red" } } }
```

## Strings

### Basic Strings

```toml
str = "I'm a string"
str_with_quote = "She said \"hello\""
```

**Escape sequences:**

| Sequence | Character |
|----------|-----------|
| `\b` | Backspace (U+0008) |
| `\t` | Tab (U+0009) |
| `\n` | Line feed (U+000A) |
| `\f` | Form feed (U+000C) |
| `\r` | Carriage return (U+000D) |
| `\"` | Quote (U+0022) |
| `\\` | Backslash (U+005C) |
| `\e` | Escape (U+001B) - TOML 1.1 |
| `\xHH` | Unicode (U+00HH) - TOML 1.1 |
| `\uHHHH` | Unicode (U+HHHH) |
| `\UHHHHHHHH` | Unicode (U+HHHHHHHH) |

### Multi-line Basic Strings

```toml
str = """
Roses are red
Violets are blue"""
```

Line-ending backslash trims whitespace:

```toml
str = """\
    The quick brown \
    fox jumps over \
    the lazy dog."""
# Result: "The quick brown fox jumps over the lazy dog."
```

### Literal Strings

No escaping - what you see is what you get:

```toml
path = 'C:\Users\nodejs\templates'
regex = '<\i\c*\s*>'
```

### Multi-line Literal Strings

```toml
regex = '''I [dw]on't need \d{2} apples'''
```

## Numbers

### Integers

```toml
int1 = +99
int2 = 42
int3 = 0
int4 = -17
int5 = 1_000_000     # Underscores for readability
```

**Other bases:**

```toml
hex = 0xDEADBEEF
oct = 0o755
bin = 0b11010110
```

### Floats

```toml
flt1 = +1.0
flt2 = 3.1415
flt3 = -0.01
flt4 = 5e+22
flt5 = 1e06
flt6 = -2E-2
flt7 = 6.626e-34
```

**Special values:**

```toml
inf1 = inf      # Positive infinity
inf2 = +inf     # Positive infinity
inf3 = -inf     # Negative infinity
nan1 = nan      # Not a number
nan2 = +nan     # Not a number
nan3 = -nan     # Not a number
```

## Booleans

```toml
bool1 = true
bool2 = false
```

## Dates and Times

### Offset Date-Time

```toml
odt1 = 1979-05-27T07:32:00Z
odt2 = 1979-05-27T00:32:00-07:00
odt3 = 1979-05-27T00:32:00.999999-07:00
odt4 = 1979-05-27 07:32:00Z  # Space separator (TOML 1.1)
```

### Local Date-Time

```toml
ldt1 = 1979-05-27T07:32:00
ldt2 = 1979-05-27T00:32:00.999999
```

### Local Date

```toml
ld1 = 1979-05-27
```

### Local Time

```toml
lt1 = 07:32:00
lt2 = 07:32:00.999999
lt3 = 07:32           # Optional seconds (TOML 1.1)
```

## Arrays

```toml
integers = [1, 2, 3]
colors = ["red", "yellow", "green"]
nested = [[1, 2], [3, 4, 5]]
mixed_types = ["string", 123, true]  # Mixed types allowed

# Multi-line
hosts = [
    "alpha",
    "beta",
    "gamma",  # Trailing comma allowed
]
```

## Tables

### Standard Tables

```toml
[table]
key1 = "value1"
key2 = "value2"

[table.subtable]
key = "value"
```

### Inline Tables

```toml
point = { x = 1, y = 2 }
animal = { type.name = "pug" }
```

TOML 1.1 also allows multiline inline tables and trailing commas:

```toml
point = {
  x = 1,
  y = 2,
}
```

::: warning
Inline tables cannot be extended after definition:
```toml
# INVALID
point = { x = 1 }
point.y = 2  # Error!
```
:::

### Array of Tables

```toml
[[products]]
name = "Hammer"
price = 9.99

[[products]]
name = "Nail"
price = 0.05

[[products.reviews]]
author = "Alice"
rating = 5

[[products.reviews]]
author = "Bob"
rating = 4
```

Result:

```php
[
    'products' => [
        [
            'name' => 'Hammer',
            'price' => 9.99,
        ],
        [
            'name' => 'Nail',
            'price' => 0.05,
            'reviews' => [
                ['author' => 'Alice', 'rating' => 5],
                ['author' => 'Bob', 'rating' => 4],
            ],
        ],
    ],
]
```

## Super Tables

Define sub-tables before their parent:

```toml
[x.y.z.w]  # Creates implicit tables x, x.y, x.y.z

[x]  # Now define x explicitly
key = "value"
```

## Key/Value Separation

Keys and values must be on the same line:

```toml
# Valid
key = "value"

# Invalid
key =
"value"
```
