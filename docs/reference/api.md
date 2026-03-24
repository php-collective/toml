# API Reference

## Toml (Facade)

The main entry point for all TOML operations.

```php
use PhpCollective\Toml\Toml;
```

### decode

```php
public static function decode(string $input): array
```

Decodes a TOML string to a PHP array. Throws `ParseException` on error.

```php
$config = Toml::decode('[server]\nhost = "localhost"');
// ['server' => ['host' => 'localhost']]
```

### decodeFile

```php
public static function decodeFile(string $path): array
```

Decodes a TOML file to a PHP array. Throws `ParseException` on parse error or unreadable files.

```php
$config = Toml::decodeFile('/path/to/config.toml');
```

### parse

```php
public static function parse(string $input, bool $preserveTrivia = false): Document
```

Parses a TOML string to an AST `Document`.

- `$preserveTrivia`: when `true`, attaches leading and trailing trivia to parsed document items and table entries

```php
$document = Toml::parse($tomlString);
foreach ($document->items as $item) {
    // Process AST nodes
}
```

### tryParse

```php
public static function tryParse(string $input): ParseResult
```

Parses a TOML string without throwing. Returns a `ParseResult` that may contain errors.

```php
$result = Toml::tryParse($input);
if ($result->isValid()) {
    $config = $result->getValue();
} else {
    $errors = $result->getErrors();
}
```

Use `tryParse()` when you need diagnostics and a partial AST instead of exception-driven control flow.

### encode

```php
public static function encode(array $data, ?EncoderOptions $options = null): string
```

Encodes a PHP array to a TOML string.

```php
$toml = Toml::encode(['key' => 'value']);
// key = "value"
```

### encodeDocument

```php
public static function encodeDocument(Document $document, ?EncoderOptions $options = null): string
```

Encodes an AST Document to a TOML string.

```php
$document = Toml::parse($original, true);
// Modify document...
$toml = Toml::encodeDocument($document);
```

When the document carries preserved trivia, `encodeDocument()` can preserve parsed comments, blank lines, some lexical styles, and some collection-local layout. Without trivia, it falls back to normalized output.

---

## ParseResult

Result object from `Toml::tryParse()`.

### isValid

```php
public function isValid(): bool
```

Returns `true` if parsing succeeded without errors.

### getValue

```php
public function getValue(): ?array
```

Returns the parsed array, or `null` if parsing failed completely.

### getDocument

```php
public function getDocument(): ?Document
```

Returns the AST Document. May be available even with errors (partial parse).

### getErrors

```php
public function getErrors(): array
```

Returns an array of `ParseError` objects.

---

## ParseError

Represents a parse error with position information.

### Properties

```php
public readonly string $message;    // Error description
public readonly Span $span;         // Position information
public readonly ?string $hint;      // Optional fix suggestion
```

### format

```php
public function format(string $source): string
```

Formats the error with source context for display.

```php
echo $error->format($originalToml);
// Parse error: unterminated string
//
//   3 | name = "value
//     |        ^
//   4 | other = 123
//
// Hint: Did you forget to close the string with "?
```

---

## Span

Position information for tokens and AST nodes.

### Properties

```php
public readonly int $start;      // 0-based byte offset from start
public readonly int $end;        // End byte offset
public readonly int $line;       // 1-based line number
public readonly int $column;     // 1-based column number
```

---

## EncoderOptions

Options for TOML encoding.

### Constructor

```php
public function __construct(
    bool $sortKeys = false,
    string $newline = "\n",
)
```

- `$sortKeys`: Sort keys alphabetically in output
- `$newline`: Newline sequence to use during encoding

---

## Explicit Encoder Value Types

Use these when you need TOML local temporal literals during encoding instead of quoted strings.

### LocalDate

```php
public function __construct(string $value)
```

Encodes as a TOML local date literal like `2024-03-15`.

### LocalTime

```php
public function __construct(string $value)
```

Encodes as a TOML local time literal like `10:30:45`.

### LocalDateTime

```php
public function __construct(string $value)
```

Encodes as a TOML local datetime literal like `2024-03-15T10:30:45`.

---

## Document

AST root node.

### Properties

```php
/** @var array<KeyValue|Table> */
public array $items;
```

---

## Table

AST node for table headers (`[name]` or `[[name]]`).

### Properties

```php
public Key $key;              // Table name
public bool $isArrayTable;    // true for [[name]]

/** @var array<KeyValue> */
public array $items;          // Key-value pairs in this table
```

### Methods

```php
public function getSpan(): Span;
```

---

## KeyValue

AST node for key-value pairs.

### Properties

```php
public Key $key;      // The key
public Value $value;  // The value
```

### Methods

```php
public function getSpan(): Span;
```

---

## Key

AST node for keys (bare, quoted, or dotted).

### Properties

```php
/** @var array<string> */
public array $parts;           // Key parts (e.g., ["a", "b"] for "a.b")

/** @var array<KeyStyle> */
public array $styles;          // Style for each part
```

---

## KeyStyle

Enum for key styles.

```php
enum KeyStyle: string
{
    case Bare = 'bare';       // key
    case Basic = 'basic';     // "key"
    case Literal = 'literal'; // 'key'
}
```

---

## Value Types

All value nodes implement the `Value` interface and extend `AbstractValue`.

### Common Methods

```php
public function getValue(): mixed;  // Get the PHP value
public function getSpan(): Span;    // Get position info
```

### StringValue

```php
public StringStyle $style;
```

`StringStyle` enum: `Basic`, `Literal`, `MultiLineBasic`, `MultiLineLiteral`

### IntegerValue

```php
public IntegerBase $base;
```

`IntegerBase` enum: `Decimal`, `Hexadecimal`, `Octal`, `Binary`

### FloatValue

Standard float value.

### BoolValue

Boolean value.

### OffsetDateTime

DateTime with timezone. `getValue()` returns `DateTimeImmutable`.

### LocalDateTime

DateTime without timezone. `getValue()` returns the original string.

### LocalDate

Date only. `getValue()` returns the original string.

### LocalTime

Time only. `getValue()` returns the original string.

### ArrayValue

```php
/** @var array<Value> */
public array $items;
```

### InlineTable

```php
/** @var array<KeyValue> */
public array $items;
```

---

## Exceptions

### ParseException

Thrown when decoding fails or when a TOML file cannot be read.

```php
use PhpCollective\Toml\Exception\ParseException;

try {
    Toml::decode($invalid);
} catch (ParseException $e) {
    echo $e->getMessage();
}
```

### EncodeException

Thrown when encoding fails because a PHP value cannot be represented as TOML.

```php
use PhpCollective\Toml\Exception\EncodeException;

try {
    Toml::encode($unsupported);
} catch (EncodeException $e) {
    echo $e->getMessage();
}
```
