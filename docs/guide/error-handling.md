# Error Handling

PHP Toml provides two error handling modes: throwing exceptions (default) and collecting errors (for tooling).

## Throwing Mode (Default)

By default, parse errors throw a `ParseException`:

```php
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\Exception\ParseException;

try {
    $config = Toml::decode($invalidToml);
} catch (ParseException $e) {
    echo $e->getMessage();
    // Parse error: unterminated string at line 3, column 8
}
```

The exception includes:
- Error message
- Line and column number
- Source context (if available)

## Collecting Mode (Tooling)

For IDEs, linters, or other tooling that needs to report multiple errors, use `tryParse()`:

```php
$result = Toml::tryParse($tomlString);

if ($result->isValid()) {
    $config = $result->getValue();
} else {
    foreach ($result->getErrors() as $error) {
        echo "Line {$error->span->line}: {$error->message}\n";
    }
}
```

### ParseResult API

```php
$result = Toml::tryParse($input);

// Check validity
$result->isValid();    // bool - true if no errors

// Get parsed value (null if invalid)
$result->getValue();   // array|null

// Get all errors
$result->getErrors();  // array<ParseError>

// Get the AST (available even with errors)
$result->getDocument(); // Document|null
```

In the current implementation, `getDocument()` is still available for invalid input, which makes `tryParse()` suitable for tooling that needs partial structure plus diagnostics.

### ParseError Structure

Each error contains:

```php
$error->message;     // string - Error description
$error->span;        // Span - Position information
$error->hint;        // string|null - Suggestion for fixing
```

### Span Information

```php
$span = $error->span;

$span->line;         // int - 1-based line number
$span->column;       // int - 1-based column number
$span->start;        // int - 0-based byte offset
$span->end;          // int - End position
```

## Formatted Error Output

For human-readable error output:

```php
foreach ($result->getErrors() as $error) {
    echo $error->format($originalInput);
}
```

Output:

```
Parse error: unterminated string

  3 | name = "value
    |        ^
  4 | other = 123

Hint: Did you forget to close the string with "?
```

## Error Categories

### Lexer Errors

Low-level tokenization errors:

- Unterminated strings
- Invalid escape sequences
- Malformed numbers
- Invalid datetime formats

```toml
# Unterminated string
name = "hello

# Invalid escape
path = "C:\new"

# Malformed number
value = 1.
```

### Parser Errors

Structural errors:

- Missing values
- Unexpected tokens
- Invalid table headers

```toml
# Missing value
key =

# Invalid table
[table.]
key = 1
```

### Semantic Errors

Validation errors:

- Duplicate keys
- Table redefinition
- Inline table extension

```toml
# Duplicate key
name = "first"
name = "second"

# Inline table extension (not allowed)
point = { x = 1 }
point.y = 2
```

## Error Recovery

The parser attempts to recover from errors to find additional issues:

```toml
name = "unterminated
age = 30
city = "missing quote
zip = 12345
```

With `tryParse()`, this reports both string errors rather than stopping at the first.

## Encoding Errors

When encoding to TOML, `EncodeException` is thrown for:

- Circular references
- Unserializable types (resources, closures)
- Unsupported objects
- `null` values

```php
use PhpCollective\Toml\Exception\EncodeException;

try {
    $toml = Toml::encode($data);
} catch (EncodeException $e) {
    echo $e->getMessage();
}
```
