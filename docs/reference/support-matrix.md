# Support Matrix

This page describes what the library supports today, what is partial, and what is not implemented yet.

It is intentionally narrower than a blanket "full TOML support" claim. The goal is to document observed behavior that is backed by the current code and test suite.

The sections below intentionally separate parsing/decoding, encoding, and round-trip editing so support claims stay scoped to the actual surface being described.

The default API behavior is TOML 1.1-compatible. Strict TOML 1.0 parsing/decoding and encoding are available through `TomlVersion::V10` and `EncoderOptions(version: TomlVersion::V10)`.

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
| `\xHH` escapes | Supported | TOML 1.1; rejected in strict TOML 1.0 mode |
| `\e` escape | Supported | TOML 1.1; rejected in strict TOML 1.0 mode |
| Invalid escape rejection | Supported | |
| Integers | Supported | Decimal, hex, octal, binary |
| Float values | Supported | Includes exponent form |
| `inf`, `-inf`, `nan` | Supported | |
| Boolean values | Supported | |
| Offset datetime | Supported | |
| Local datetime | Supported | Optional-seconds forms are rejected in strict TOML 1.0 mode |
| Local date | Supported | |
| Local time | Supported | Optional-seconds forms are rejected in strict TOML 1.0 mode |

### Collections

| Feature | Status | Notes |
|---------|--------|-------|
| Arrays | Supported | |
| Multiline arrays | Supported | |
| Array trailing commas | Supported | |
| Inline tables | Supported | Single-line and multiline |
| Nested inline tables | Supported | |
| Dotted keys inside inline tables | Supported | |
| Inline table trailing commas | Supported | TOML 1.1; rejected in strict TOML 1.0 mode |
| Multiline inline-table layout | Supported | TOML 1.1; rejected in strict TOML 1.0 mode. TOML 1.0 still allows multiline array and string values inside inline tables |

## Encoding

| Feature | Status | Notes |
|---------|--------|-------|
| Strings | Supported | Encoded as basic strings; control characters are escaped (`\uXXXX` / shorthand) so output stays valid TOML |
| Integers | Supported | |
| Floats | Supported | Round-tripped at full `double` precision (shortest exact representation) |
| Booleans | Supported | |
| Arrays | Supported | |
| Nested tables | Supported | |
| Array of tables | Supported | |
| Quoted keys when needed | Supported | |
| `DateTimeInterface` | Partial | Encoded as offset datetime; zero fractional seconds are omitted and a `+00:00` offset is emitted as `Z` |
| `stdClass` as table | Supported | A `stdClass` encodes as a table (`[key]`), or an inline table in inline contexts; an empty one emits an empty table |
| Empty tables | Partial | An empty PHP array encodes as `[]`; use an empty `stdClass` to emit an empty `[table]` |
| `PhpCollective\Toml\Value\LocalDate` | Supported | Encoded as local date literal |
| `PhpCollective\Toml\Value\LocalTime` | Supported | Encoded as local time literal; strict TOML 1.0 mode normalizes missing seconds |
| `PhpCollective\Toml\Value\LocalDateTime` | Supported | Encoded as local datetime literal; strict TOML 1.0 mode normalizes missing seconds |
| Plain string to local temporal literal coercion | Not Yet | Plain strings are emitted as quoted strings |
| Null values | Supported | Rejected with `EncodeException` |
| Original lexical style preservation | Partial | `encodeDocument()` can preserve parsed key and string styles; strict TOML 1.0 source-aware output rejects preserved 1.1-only syntax |

## AST and Round-Trip Editing

| Feature | Status | Notes |
|---------|--------|-------|
| AST node access | Supported | |
| Span information | Supported | |
| Trivia preservation on document items and table entries | Partial | Available through `Toml::parse($input, true)` for leading/trailing trivia on parsed items |
| Trivia preservation inside parsed arrays and inline tables | Partial | Collection-local item spacing and comments are preserved where represented in the AST |
| Comment preservation on re-encode | Partial | Preserved for parsed document items, table entries, and collection items when trivia is available |
| Formatting preservation on re-encode | Partial | Available in `DocumentFormattingMode::SourceAware` for trivia-preserving ASTs, including local spacing preservation for common single-line collection edits |
| `encodeDocument()` round-trip fidelity | Partial | Normalized by default; source-aware mode is lossless for unchanged parsed regions, while edited regions preserve local style when inferable and otherwise canonicalize locally |

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

Tested against [toml-test](https://github.com/toml-lang/toml-test) v2.2.0:

### TOML 1.1

| Test Type | Passed | Failed | Compliance |
|-----------|--------|--------|------------|
| Valid | 213 | 0 | 100% |
| Invalid | 467 | 0 | 100% |

### TOML 1.0

| Test Type | Passed | Failed | Compliance |
|-----------|--------|--------|------------|
| Valid | 204 | 0 | 100% |
| Invalid | 474 | 0 | 100% |

These results were measured against the library's `bin/toml-decoder` adapter for the `toml-test` tagged JSON format. TOML 1.1 results use the default adapter mode; TOML 1.0 results use `TOML_VERSION=1.0` so the decoder runs in strict TOML 1.0 mode.

Strict TOML 1.0 mode closes the previously documented invalid-case gaps for syntax that TOML 1.1 relaxes: multiline inline-table layout, inline-table trailing commas, `\xHH` byte escapes, and optional seconds in local times/datetimes.

A leading UTF-8 BOM (`U+FEFF`) at the very start of a document is accepted and skipped, matching the `toml-test` corpus and common parsers.

A matching `bin/toml-encoder` adapter implements the `toml-test` encoder protocol (tagged JSON to TOML). It is exercised by `TomlTestEncoderTest`, which encodes each fixture and decodes the result back to confirm valid, semantically equivalent output. Both adapters are skipped automatically when the `toml-test` corpus is not present locally; CI clones the pinned corpus version so these checks gate every pull request.

## Recommended Use

This library is well suited for:

- parsing and validating TOML configuration files
- collecting syntax and semantic errors for IDE/tooling integration
- encoding PHP arrays into TOML
- round-trip editing with comment and formatting preservation
- AST-based TOML analysis and transformation
