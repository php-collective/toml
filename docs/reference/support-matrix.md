# Support Matrix

This page describes what the library supports today, what is partial, and what is not implemented yet.

It is intentionally narrower than a blanket "full TOML support" claim. The goal is to document observed behavior that is backed by the current code and test suite.

## Status Legend

- Supported: implemented and covered by tests
- Partial: implemented in a limited way, or decoder and encoder differ
- Not Yet: not implemented, not preserved, or intentionally out of scope today

## Parsing and Decoding

### Keys and Tables

| Feature | Status | Notes |
|---------|--------|-------|
| Bare keys | Supported | |
| Quoted keys | Supported | Basic and literal quoted keys are accepted |
| Empty quoted keys | Supported | Example: `"" = 1` |
| Dotted keys | Supported | |
| Standard tables | Supported | |
| Array of tables | Supported | Nested array-of-tables is covered by tests |
| Duplicate key detection | Supported | Rejected as semantic errors |
| Duplicate table detection | Supported | Rejected as semantic errors |
| Key/table redefinition conflicts | Supported | Rejected as semantic errors |

### Values

| Feature | Status | Notes |
|---------|--------|-------|
| Basic strings | Supported | |
| Literal strings | Supported | |
| Multiline basic strings | Supported | |
| Multiline literal strings | Supported | |
| `\u` and `\U` escapes | Supported | |
| `\xHH` escapes | Supported | TOML 1.1 |
| `\e` escape | Supported | TOML 1.1 |
| Invalid escape rejection | Supported | |
| Integers | Supported | Decimal, hex, octal, binary |
| Float values | Supported | Includes exponent form |
| `inf`, `-inf`, `nan` | Supported | |
| Boolean values | Supported | |
| Offset datetime | Supported | |
| Local datetime | Supported | |
| Local date | Supported | |
| Local time | Supported | Optional seconds supported |

### Collections

| Feature | Status | Notes |
|---------|--------|-------|
| Arrays | Supported | |
| Multiline arrays | Supported | |
| Array trailing commas | Supported | |
| Inline tables | Supported | Single-line inline tables |
| Nested inline tables | Supported | |
| Dotted keys inside inline tables | Supported | |
| Inline table trailing commas | Supported | Rejected per current parser behavior |
| Multiline inline tables | Not Yet | Current parser rejects them |

## Encoding

| Feature | Status | Notes |
|---------|--------|-------|
| Strings | Supported | Encoded as basic strings |
| Integers | Supported | |
| Floats | Supported | |
| Booleans | Supported | |
| Arrays | Supported | |
| Nested tables | Supported | |
| Array of tables | Supported | |
| Quoted keys when needed | Supported | |
| `DateTimeInterface` | Partial | Encoded as offset datetime with microseconds and offset |
| `PhpCollective\Toml\Value\LocalDate` | Supported | Encoded as local date literal |
| `PhpCollective\Toml\Value\LocalTime` | Supported | Encoded as local time literal |
| `PhpCollective\Toml\Value\LocalDateTime` | Supported | Encoded as local datetime literal |
| Plain string to local temporal literal coercion | Not Yet | Plain strings are emitted as quoted strings |
| Null values | Supported | Rejected with `EncodeException` |
| Original lexical style preservation | Partial | `encodeDocument()` can preserve parsed key and string styles |

## AST and Round-Trip Editing

| Feature | Status | Notes |
|---------|--------|-------|
| AST node access | Supported | |
| Span information | Supported | |
| Trivia preservation on document items and table entries | Partial | Available through `Toml::parse($input, true)` for leading/trailing trivia on parsed items |
| Trivia preservation inside parsed arrays and inline tables | Partial | Collection-local item spacing and comments are preserved where represented in the AST |
| Comment preservation on re-encode | Partial | Preserved for parsed document items, table entries, and collection items when trivia is available |
| Formatting preservation on re-encode | Partial | Available in `DocumentFormattingMode::SourceAware` for trivia-preserving ASTs |
| `encodeDocument()` round-trip fidelity | Partial | Normalized by default; source-aware mode is lossless for unchanged parsed documents and local-fallback for edited regions |

## Tooling and Errors

| Feature | Status | Notes |
|---------|--------|-------|
| `Toml::decode()` | Supported | Throws `ParseException` on invalid input |
| `Toml::tryParse()` | Supported | Returns collected parse and semantic errors |
| Multiple error collection | Partial | Recovery exists, but is line-oriented and not conformance-grade |
| Error spans and formatting | Supported | |

## Known Gaps

- The decoder supports more TOML temporal forms than the encoder can emit.
- Multiline inline tables are not currently supported.
- `encodeDocument()` still does not preserve all original formatting details.
- AST editing falls back to canonical local formatting when new nodes do not carry trivia or when single-line collection shape changes invalidate preserved delimiter layout.
- Fallback behavior is local rather than globally lossless: nested edited collections may normalize while outer layout stays preserved.
- Small value-only edits can preserve original key/value separator spacing.
- Edited-document fixture coverage includes value edits, multiline string edits, quoted and literal key edits, table and array-table header edits, dotted-key edits, style-changing key-segment edits, and nested collection mutations.
- Inline table formatting options beyond key sorting and newline selection are not implemented.

## Recommended Use Today

This library is a reasonable fit for:

- parsing and validating common TOML configuration files
- collecting syntax and semantic errors for tooling
- encoding standard PHP arrays into TOML

It is not yet a strong fit for:

- lossless TOML editing
- partial comment-preserving rewrites
- formatter-style round-trip transformations
