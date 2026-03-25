# AST Access

PHP Toml provides full access to the Abstract Syntax Tree (AST) for advanced use cases like analysis, transformation, or editor integrations.

## Parsing to AST

```php
use PhpCollective\Toml\Toml;

$document = Toml::parse(<<<'TOML'
[server]
host = "localhost"
port = 8080
TOML);
```

## Document Structure

The AST is a tree of nodes:

```
Document
└── items: array<KeyValue|Table>
    ├── Table (header: [server])
    │   └── items: array<KeyValue>
    │       ├── KeyValue (key: host, value: StringValue)
    │       └── KeyValue (key: port, value: IntegerValue)
```

## Node Types

### Document

The root node containing all top-level items:

```php
$document->items; // array<KeyValue|Table>
```

### Table

A table header (`[name]` or `[[name]]`):

```php
$table->key;          // Key - table name parts
$table->key->parts;   // array<string> - ["server", "database"]
$table->isArrayTable; // bool - true for [[name]]
$table->items;        // array<KeyValue> - key-value pairs in this table
```

### KeyValue

A key-value pair:

```php
$kv->key;        // Key - the key
$kv->key->parts; // array<string> - ["a", "b"] for "a.b = value"
$kv->value;      // Value - the value node
```

### Key

A key (bare, quoted, or dotted):

```php
$key->parts;   // array<string> - key parts
$key->styles;  // array<KeyStyle> - style for each part
```

`KeyStyle` enum:
- `Bare` - unquoted key
- `Basic` - `"quoted"`
- `Literal` - `'quoted'`

## Value Types

All value nodes extend `AbstractValue`:

### StringValue

```php
$value->getValue();  // string
$value->style;       // StringStyle enum
```

`StringStyle` enum:
- `Basic` - `"string"`
- `Literal` - `'string'`
- `MultiLineBasic` - `"""string"""`
- `MultiLineLiteral` - `'''string'''`

### IntegerValue

```php
$value->getValue();  // int
$value->base;        // IntegerBase enum
```

`IntegerBase` enum:
- `Decimal` - `42`
- `Hexadecimal` - `0xDEAD`
- `Octal` - `0o755`
- `Binary` - `0b1010`

### FloatValue

```php
$value->getValue();  // float
```

### BoolValue

```php
$value->getValue();  // bool
```

### DateTime Values

```php
// OffsetDateTime - 1979-05-27T07:32:00Z
$value->getValue();  // DateTimeImmutable

// LocalDateTime - 1979-05-27T07:32:00
$value->getValue();  // string (no timezone info)

// LocalDate - 1979-05-27
$value->getValue();  // string

// LocalTime - 07:32:00
$value->getValue();  // string
```

### ArrayValue

```php
$value->items;  // array<Value>
```

### InlineTable

```php
$value->items;  // array<KeyValue>
```

## Position Information

Every node has position information via `getSpan()`:

```php
$span = $node->getSpan();
$span->line;    // 1-based line number
$span->column;  // 1-based column number
$span->start;   // 0-based byte offset
$span->end;     // End byte offset
```

## Trivia Preservation

You can ask the parser to retain leading and trailing trivia for document items and table entries:

```php
$document = Toml::parse($tomlString, true);

$item = $document->items[0];
$leading = $item->getLeadingTrivia();
$trailing = $item->getTrailingTrivia();
```

Trivia currently includes:

- whitespace
- comments
- line endings attached to parsed items

Each `Trivia` object has:

```php
$trivia->kind;   // TriviaKind::Whitespace, Comment, or Newline
$trivia->value;  // The raw text (e.g., "# my comment\n")
$trivia->span;   // Source location
```

This is useful for editor tooling and source-aware analysis. When you re-encode with `Toml::encodeDocument()`, preserved trivia is included in the output.

Arrays and inline tables can also retain collection-local trivia used for round-trip encoding:

```php
$document = Toml::parse($tomlString, true);
$array = $document->items[0]->value;

$array->openingTrivia;      // trivia after '[' when available
$array->closingTrivia;      // trivia before ']' when available
$array->hasTrailingComma;   // whether the parsed array ended with a trailing comma
```

That collection-local trivia is primarily intended for editor-style round-trip encoding rather than for semantic analysis.

## Walking the AST

### Simple Iteration

```php
foreach ($document->items as $item) {
    if ($item instanceof \PhpCollective\Toml\Ast\Table) {
        echo "Table: " . implode('.', $item->key->parts) . "\n";
        foreach ($item->items as $kv) {
            echo "  Key: " . implode('.', $kv->key->parts) . "\n";
        }
    } elseif ($item instanceof \PhpCollective\Toml\Ast\KeyValue) {
        echo "Root key: " . implode('.', $item->key->parts) . "\n";
    }
}
```

### Finding Specific Keys

```php
function findKey(Document $doc, string $path): ?Value
{
    $parts = explode('.', $path);
    // ... traverse the AST
}
```

## Re-encoding

After modifying the AST, encode back to TOML:

```php
use PhpCollective\Toml\Ast\Value\StringStyle;
use PhpCollective\Toml\Ast\Value\StringValue;
use PhpCollective\Toml\Lexer\Span;

// Modify a value
$document->items[0]->value = new StringValue('new value', StringStyle::Basic, new Span(0, 0, 1, 1));

// Re-encode with source-aware formatting
$toml = Toml::encodeDocument(
    $document,
    new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware),
);
```

::: warning
`encodeDocument()` normalizes by default. Use `DocumentFormattingMode::SourceAware` when you want preserved formatting for unchanged parsed regions and local fallback rules for edited ones.
:::

For the exact round-trip and editing guarantees, see [Compatibility](https://php-collective.github.io/toml/reference/compatibility).

## Use Cases

### Configuration Validation

```php
$document = Toml::parse($config);

foreach ($document->items as $item) {
    if ($item instanceof Table && $item->key->parts === ['database']) {
        // Validate database section
        foreach ($item->items as $kv) {
            if ($kv->key->parts === ['port']) {
                $port = $kv->value->getValue();
                if ($port < 1 || $port > 65535) {
                    throw new Exception("Invalid port at line {$kv->getSpan()->line}");
                }
            }
        }
    }
}
```

### Editor Integration

```php
// Get position for a key
$span = $keyValue->getSpan();
echo "Definition at line {$span->line}, column {$span->column}";

// Highlight value range
$valueSpan = $keyValue->value->getSpan();
$start = $valueSpan->start;
$end = $valueSpan->end;
```

### Schema Generation

```php
function generateSchema(Document $doc): array
{
    $schema = [];
    foreach ($doc->items as $item) {
        if ($item instanceof KeyValue) {
            $schema[$item->key->parts[0]] = getType($item->value);
        }
    }
    return $schema;
}
```
