# Architecture

toml-php follows a clean pipeline architecture separating concerns into distinct phases.

## Overview

```
┌─────────────────────────────────────────────────────────────┐
│                         Input                               │
│                    (TOML String)                            │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                        Lexer                                │
│                                                             │
│  • Tokenizes input into Token stream                        │
│  • Handles strings, numbers, dates, structure               │
│  • Reports lexical errors (unterminated strings, etc.)      │
│                                                             │
│  Output: Generator<Token>                                   │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                        Parser                               │
│                                                             │
│  • Consumes tokens, builds AST                              │
│  • Validates syntax structure                               │
│  • Reports structural errors (missing values, etc.)         │
│                                                             │
│  Output: Document (AST)                                     │
└─────────────────────────────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────┐
│                      Normalizer                             │
│                                                             │
│  • Converts AST to PHP array                                │
│  • Validates semantics (duplicates, redefinitions)          │
│  • Reports semantic errors                                  │
│                                                             │
│  Output: array<string, mixed>                               │
└─────────────────────────────────────────────────────────────┘
```

## Components

### Lexer

The lexer (`src/Lexer/Lexer.php`) converts input text into a stream of tokens using a generator:

```php
$lexer = new Lexer($input);
foreach ($lexer->tokenize() as $token) {
    // Process token
}
```

**Key characteristics:**
- Generator-based for memory efficiency
- Handles all TOML value types
- Provides position tracking (line, column, offset)
- Immediate error reporting for invalid tokens

**Token types:**
- Structural: `LeftBracket`, `RightBracket`, `LeftBrace`, `RightBrace`, `Equals`, `Dot`, `Comma`
- Values: `BareKey`, `BasicString`, `LiteralString`, `Integer`, `Float`, `Boolean`
- DateTime: `OffsetDateTime`, `LocalDateTime`, `LocalDate`, `LocalTime`
- Control: `Newline`, `Whitespace`, `Comment`, `Eof`, `Invalid`

### Parser

The parser (`src/Parser/Parser.php`) builds an AST from the token stream:

```php
$parser = new Parser($input);
$document = $parser->parse();
```

**Key characteristics:**
- Recursive descent parser
- Error recovery for multiple error reporting
- Preserves position information on all nodes
- Handles all TOML constructs

**AST structure:**
```
Document
├── items: array<KeyValue|Table>
│   ├── KeyValue
│   │   ├── key: Key
│   │   └── value: Value
│   └── Table
│       ├── key: Key
│       ├── isArrayTable: bool
│       └── items: array<KeyValue>
```

### Normalizer

The normalizer (`src/Normalizer.php`) converts AST to PHP values:

```php
$normalizer = new Normalizer();
$array = $normalizer->normalize($document);
$errors = $normalizer->getErrors();
```

**Key characteristics:**
- Semantic validation
- Duplicate key detection
- Table redefinition detection
- Inline table immutability enforcement
- Path tracking for error messages

### Encoder

The encoder (`src/Encoder/Encoder.php`) converts PHP values to TOML:

```php
$encoder = new Encoder($options);
$toml = $encoder->encode($array);
```

**Key characteristics:**
- Type-appropriate formatting
- Table structure detection
- Array of tables handling
- Special float value support

## Error Handling

Errors flow through the pipeline with position information:

```
Lexer → Token with Invalid type + error message
Parser → ParseError collected in errors array
Normalizer → ParseError collected in errors array
```

The `Toml` facade coordinates error handling:
- `decode()` / `parse()` - throw on first error
- `tryParse()` - collect all errors

## Data Flow

### Decoding

```php
Toml::decode($input)
    → Lexer::tokenize()          // string → Token*
    → Parser::parse()            // Token* → Document
    → Normalizer::normalize()    // Document → array
    → Result: array
```

### Encoding

```php
Toml::encode($array)
    → Encoder::encode()          // array → string
    → Result: TOML string
```

### Round-trip (with AST)

```php
$doc = Toml::parse($input);      // string → Document
// Modify $doc...
$toml = Toml::encodeDocument($doc); // Document → string
```

## Extension Points

### Custom Error Handling

```php
$result = Toml::tryParse($input);
foreach ($result->getErrors() as $error) {
    // Custom error formatting
    $formatted = myFormatter($error, $input);
}
```

### AST Analysis

```php
$document = Toml::parse($input);
// Walk the AST for analysis
analyzeDocument($document);
```

## Performance Considerations

- **Generator-based lexer**: Memory efficient for large files
- **Single-pass parsing**: No backtracking
- **Lazy normalization**: AST available without full conversion
- **Minimal allocations**: Reuse of structures where possible

## File Organization

```
src/
├── Toml.php                    # Public facade
├── Normalizer.php              # AST to array conversion
├── Ast/                        # AST node classes
│   ├── Document.php
│   ├── Table.php
│   ├── KeyValue.php
│   ├── Key.php
│   └── Value/                  # Value node types
│       ├── StringValue.php
│       ├── IntegerValue.php
│       └── ...
├── Lexer/                      # Tokenization
│   ├── Lexer.php
│   ├── Token.php
│   ├── TokenType.php
│   └── Span.php
├── Parser/                     # Parsing
│   ├── Parser.php
│   ├── ParseError.php
│   └── ParseResult.php
├── Encoder/                    # Encoding
│   ├── Encoder.php
│   └── EncoderOptions.php
└── Exception/                  # Exceptions
    ├── ParseException.php
    └── EncodeException.php
```
