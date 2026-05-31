# API Reference

## Toml (Facade)

The main entry point for all TOML operations.

```php
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\TomlVersion;
```

### decode()

```php
public static function decode(string $input, TomlVersion $version = TomlVersion::V11): array
```

Decodes a TOML string to a PHP array. Throws `ParseException` on error.

```php
$config = Toml::decode('[server]\nhost = "localhost"');
// ['server' => ['host' => 'localhost']]

$strict = Toml::decode('time = 07:32:00', TomlVersion::V10);
```

### decodeFile()

```php
public static function decodeFile(string $path, TomlVersion $version = TomlVersion::V11): array
```

Decodes a TOML file to a PHP array. Throws `ParseException` on parse error or unreadable files.

```php
$config = Toml::decodeFile('/path/to/config.toml');
```

### parse()

```php
public static function parse(
    string $input,
    bool $preserveTrivia = false,
    TomlVersion $version = TomlVersion::V11,
): Document
```

Parses a TOML string to an AST `Document`.

- `$preserveTrivia`: when `true`, attaches leading and trailing trivia to parsed document items and table entries
- `$version`: opt into strict TOML 1.0 parsing rules with `TomlVersion::V10`

```php
$document = Toml::parse($tomlString);
foreach ($document->items as $item) {
    // Process AST nodes
}
```

### tryParse()

```php
public static function tryParse(string $input, TomlVersion $version = TomlVersion::V11): ParseResult
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

The default parser/decoder mode is TOML 1.1-compatible. Use `TomlVersion::V10` when you need strict TOML 1.0 rejection of 1.1-only features such as `\xHH`, `\e`, multiline inline-table layout, inline-table trailing commas, or local times without seconds.

### encode()

```php
public static function encode(array $data, ?EncoderOptions $options = null): string
```

Encodes a PHP array to a TOML string.

```php
$toml = Toml::encode(['key' => 'value']);
// key = "value"

$strict = Toml::encode(
    ['time' => new \PhpCollective\Toml\Value\LocalTime('07:32')],
    new EncoderOptions(version: TomlVersion::V10),
);
// time = 07:32:00
```

### encodeDocument()

```php
public static function encodeDocument(Document $document, ?EncoderOptions $options = null): string
```

Encodes an AST Document to a TOML string.

```php
$document = Toml::parse($original, true);
// Modify document...
$toml = Toml::encodeDocument(
    $document,
    new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware),
);
```

`encodeDocument()` defaults to normalized output. With `DocumentFormattingMode::SourceAware`, it can preserve parsed comments, blank lines, lexical styles, and collection-local layout for trivia-preserving ASTs.

### encodeFile()

```php
public static function encodeFile(string $path, array $data, ?EncoderOptions $options = null): void
```

Encodes a PHP array to TOML and writes it to `$path`. Throws `EncodeException` on an encoding error or if the file cannot be written.

```php
Toml::encodeFile('/path/to/config.toml', ['server' => ['port' => 8080]]);
```

### encodeDocumentFile()

```php
public static function encodeDocumentFile(string $path, Document $document, ?EncoderOptions $options = null): void
```

Encodes an AST Document to TOML and writes it to `$path`. Throws `EncodeException` on an encoding error or if the file cannot be written.

---

## ParseResult

Result object from `Toml::tryParse()`.

### isValid()

```php
public function isValid(): bool
```

Returns `true` if parsing succeeded without errors.

### getValue()

```php
public function getValue(): ?array
```

Returns the parsed array, or `null` if parsing failed completely.

### getDocument()

```php
public function getDocument(): ?Document
```

Returns the AST Document. May be available even with errors (partial parse).

### getErrors()

```php
public function getErrors(): array
```

Returns an array of `ParseError` objects.

---

## ParseError

Represents a parse error with position information.

### Properties

```php
public readonly string $message;        // Error description
public readonly Span $span;             // Position information
public readonly ?string $hint;          // Optional fix suggestion
public readonly ParseErrorCode $code;   // Stable, machine-readable classification
```

The `code` is a `ParseErrorCode` enum (string-backed). Prefer switching on it over matching the human-readable `message`, which is not a stable contract. See [Error Handling](../guide/error-handling).

### format()

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
    DocumentFormattingMode $documentFormatting = DocumentFormattingMode::Normalized,
    bool $skipNulls = false,
    TomlVersion $version = TomlVersion::V11,
    bool $integerGrouping = false,
    IntegerBase $integerBase = IntegerBase::Decimal,
    bool $trailingComma = false,
    bool $dottedKeys = false,
    ArrayStyle $arrayStyle = ArrayStyle::Inline,
    int $arrayAutoThreshold = 3,
    ?int $multilineThreshold = null,
    ?int $inlineTableThreshold = null,
    string $indent = '    ',
)
```

### diffFriendly()

```php
public static function diffFriendly(): self
```

Returns options optimized for minimal diffs in version control:
- `trailingComma: true` - adding array items doesn't modify previous line
- `arrayStyle: ArrayStyle::Auto` - larger arrays use multiline format

```php
$toml = Toml::encode($data, EncoderOptions::diffFriendly());
```

### Options Reference

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `sortKeys` | `bool` | `false` | Sort keys alphabetically in output |
| `newline` | `string` | `"\n"` | Newline sequence to use (`"\n"` or `"\r\n"`) |
| `documentFormatting` | `DocumentFormattingMode` | `Normalized` | `Normalized` or `SourceAware` for `encodeDocument()` |
| `skipNulls` | `bool` | `false` | Omit `null` values instead of throwing `EncodeException` |
| `version` | `TomlVersion` | `V11` | TOML version for output rules |
| `integerGrouping` | `bool` | `false` | Add underscores to large integers (e.g., `1_000_000`) |
| `integerBase` | `IntegerBase` | `Decimal` | Radix for integers in `encode()` output (`Hexadecimal`/`Octal`/`Binary` emit `0x`/`0o`/`0b`; negatives stay decimal) |
| `trailingComma` | `bool` | `false` | Add trailing commas to inline arrays |
| `dottedKeys` | `bool` | `false` | Use dotted keys instead of table sections |
| `arrayStyle` | `ArrayStyle` | `Inline` | Array formatting style (see below) |
| `arrayAutoThreshold` | `int` | `3` | Item count threshold for `ArrayStyle::Auto` |
| `multilineThreshold` | `?int` | `null` | Opt-in string length threshold for multiline basic strings in `encode()` |
| `inlineTableThreshold` | `?int` | `null` | Opt-in key count threshold for small flat nested arrays to encode as inline tables |
| `indent` | `string` | `'    '` | Indentation string for multiline arrays |

### ArrayStyle

Controls how arrays are formatted in output.

```php
use PhpCollective\Toml\Encoder\ArrayStyle;
```

| Style | Description |
|-------|-------------|
| `ArrayStyle::Inline` | Arrays on a single line: `[1, 2, 3]` |
| `ArrayStyle::Multiline` | One item per line with indentation |
| `ArrayStyle::Auto` | Multiline if items exceed `arrayAutoThreshold` |

**Examples:**

```php
// Inline (default)
$toml = Toml::encode(['ports' => [8080, 8081, 8082]]);
// ports = [8080, 8081, 8082]

// Multiline
$toml = Toml::encode(
    ['ports' => [8080, 8081, 8082]],
    new EncoderOptions(arrayStyle: ArrayStyle::Multiline),
);
// ports = [
//     8080,
//     8081,
//     8082,
// ]

// Auto (multiline when > 3 items)
$toml = Toml::encode(
    ['ports' => [8080, 8081, 8082, 8083, 8084]],
    new EncoderOptions(arrayStyle: ArrayStyle::Auto, arrayAutoThreshold: 3),
);
// ports = [
//     8080,
//     8081,
//     8082,
//     8083,
//     8084,
// ]

// Custom indent (2 spaces)
$toml = Toml::encode(
    ['items' => [1, 2, 3]],
    new EncoderOptions(arrayStyle: ArrayStyle::Multiline, indent: '  '),
);
// items = [
//   1,
//   2,
//   3,
// ]
```

### Encoder Thresholds

`multilineThreshold` is `?int` and defaults to `null`. When set, `encode()` emits scalar strings longer than the threshold as multiline basic strings.

```php
$toml = Toml::encode(
    ['description' => 'a longer block of text'],
    new EncoderOptions(multilineThreshold: 10),
);
// description = """
// a longer block of text"""
```

`inlineTableThreshold` is `?int` and defaults to `null`. When set, `encode()` emits flat nested arrays with at most that many keys as inline tables; nested tables and arrays of tables still use table headers.

```php
$toml = Toml::encode(
    ['point' => ['x' => 1, 'y' => 2]],
    new EncoderOptions(inlineTableThreshold: 3),
);
// point = { x = 1, y = 2 }
```

### Integer Grouping

```php
$toml = Toml::encode(
    ['large' => 1000000],
    new EncoderOptions(integerGrouping: true),
);
// large = 1_000_000
```

### Dotted Keys

```php
$toml = Toml::encode(
    ['database' => ['host' => 'localhost', 'port' => 5432]],
    new EncoderOptions(dottedKeys: true),
);
// database.host = "localhost"
// database.port = 5432
```

### TOML 1.0 Mode

In strict TOML 1.0 mode, `encode()` normalizes local times and local datetimes to include seconds where possible. `encodeDocument()` in `DocumentFormattingMode::SourceAware` throws `EncodeException` if preserving the parsed source would keep TOML 1.1-only syntax.

## TomlVersion

Controls version-specific parser and encoder behavior.

- `TomlVersion::V11`
  Default behavior. Accepts TOML 1.1 syntax and emits TOML 1.1-compatible output.
- `TomlVersion::V10`
  Strict TOML 1.0 mode for parsing, decoding, and encoding.

## DocumentFormattingMode

Controls how `encodeDocument()` emits AST documents.

- `DocumentFormattingMode::Normalized`
  Produces normalized TOML output. This is the default.
- `DocumentFormattingMode::SourceAware`
  Reuses preserved source formatting where possible and falls back locally for edited regions.

### Null Handling

By default, `encode()` throws `EncodeException` for `null` values. With `new EncoderOptions(skipNulls: true)`, `null` values are omitted from tables, arrays, and inline tables.

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
