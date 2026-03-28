# Support Matrix

This page describes what the library supports today, what is partial, and what is not implemented yet.

It is intentionally narrower than a blanket "full TOML support" claim. The goal is to document observed behavior that is backed by the current code and test suite.

The sections below intentionally separate parsing/decoding, encoding, and round-trip editing so support claims stay scoped to the actual surface being described.

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
| Inline tables | Supported | Single-line and multiline |
| Nested inline tables | Supported | |
| Dotted keys inside inline tables | Supported | |
| Inline table trailing commas | Supported | TOML 1.1 |
| Multiline inline tables | Supported | TOML 1.1 |

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
- AST editing falls back to canonical local formatting when new nodes do not carry trivia or when single-line collection shape changes do not expose a consistent delimiter style to preserve.
- Fallback behavior is local rather than globally lossless: nested edited collections may normalize while outer layout stays preserved.
- Small value-only edits can preserve original key/value separator spacing.
- Inline table formatting options beyond key sorting and newline selection are not implemented.

## toml-test Compliance

Tested against [toml-test](https://github.com/toml-lang/toml-test) v2.1.0:

### TOML 1.1

| Test Type | Passed | Failed | Compliance |
|-----------|--------|--------|------------|
| Valid | 213 | 1 | 99.5% |
| Invalid | 466 | 9 | 98.1% |

### TOML 1.0

| Test Type | Passed | Failed | Compliance |
|-----------|--------|--------|------------|
| Valid | 204 | 1 | 99.5% |
| Invalid | 466 | 17 | 96.5% |

The single valid test failure is due to a PHP limitation with null byte property names.

The remaining invalid test failures are TOML 1.0 strict tests for features that TOML 1.1 relaxes (multiline inline tables, trailing commas, `\xHH` escapes, optional seconds in times).

## Recommended Use

This library is well suited for:

- parsing and validating TOML configuration files
- collecting syntax and semantic errors for IDE/tooling integration
- encoding PHP arrays into TOML
- round-trip editing with comment and formatting preservation
- AST-based TOML analysis and transformation
