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
| Local datetime/date/time typed output | Not Yet | Plain strings are emitted as quoted strings |
| Null values | Supported | Rejected with `EncodeException` |
| Original lexical style preservation | Not Yet | Encoder normalizes output |

## AST and Round-Trip Editing

| Feature | Status | Notes |
|---------|--------|-------|
| AST node access | Supported | |
| Span information | Supported | |
| Trivia preservation | Not Yet | Public API shape exists, behavior is not implemented |
| Comment preservation on re-encode | Not Yet | |
| Formatting preservation on re-encode | Not Yet | |
| `encodeDocument()` round-trip fidelity | Partial | Re-encodes normalized content, not original formatting |

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
- `encodeDocument()` does not preserve comments or original formatting.
- `EncoderOptions::inlineTableMaxKeys` is currently unused.
- `EncoderOptions::preserveTrivia` is currently unused.

## Recommended Use Today

This library is a reasonable fit for:

- parsing and validating common TOML configuration files
- collecting syntax and semantic errors for tooling
- encoding standard PHP arrays into TOML

It is not yet a strong fit for:

- lossless TOML editing
- comment-preserving rewrites
- formatter-style round-trip transformations
- conformance-grade claims without a larger corpus
