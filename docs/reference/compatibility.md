# Compatibility

This page documents the current compatibility expectations for `PHP Toml`.

## Public API Stability

The following surface should be treated as public API:

- the `Toml` facade
- documented exception types
- `EncoderOptions`
- AST node classes used in the public docs
- explicit encoder value wrappers under `PhpCollective\Toml\Value`

Behavior may still tighten for invalid TOML when parser or semantic bugs are fixed.

## Compatibility Guarantees

- Supported PHP versions follow `composer.json`.
- Valid TOML accepted today should remain valid unless the current behavior is itself a bug.
- Invalid TOML may become rejected more consistently over time.
- `encode()` produces normalized output, not formatting-preserving output.
- `encodeDocument()` defaults to normalized output.
- `encodeDocument()` provides lossless round-trip preservation for unchanged parsed documents only when the document carries trivia and `DocumentFormattingMode::SourceAware` is selected.

## Round-Trip Editing Contract

For `Toml::parse($input, true)` followed by `Toml::encodeDocument($document, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware))`:

- unchanged parsed nodes keep preserved trivia where the AST represents it
- comments and blank lines can survive re-encoding
- key and string lexical style can survive re-encoding
- table-header spacing and dotted-key separator spacing can survive compatible key edits
- arrays and inline tables can preserve collection-local layout
- edited nodes without trivia fall back to canonical local formatting
- fallback decisions are local to the edited collection, so outer preserved layout can survive while nested edited collections normalize

Canonical local formatting currently means:

- unchanged key/value structure can keep original separator spacing for compatible key-only and value-only edits
- dotted-key and table-header edits can keep original separator spacing, including compatible segment style changes
- arrays without preserved local trivia encode as `[1, 2]`
- inserted items in multiline parsed arrays reuse inferred indentation when possible
- single-line parsed arrays with shape-changing edits preserve consistent delimiter style when it can be inferred; otherwise they canonicalize to `[1, 2, 3]` style output
- inline tables without preserved local trivia encode as `{ x = 1, y = 2 }`
- inline tables with shape-changing edits preserve consistent delimiter style when it can be inferred; otherwise they canonicalize local spacing

This gives lossless preservation for unchanged parsed regions, but edited regions still follow editing rules rather than full formatter semantics.

The repository includes broad edited-document fixture coverage across value replacement, multiline string replacement, quoted and literal key edits, table and array-table header edits, dotted-key edits, style-changing key-segment edits, and nested collection mutations. That still does not make the encoder a general-purpose TOML formatter.

## Upgrade Guidance

When parser strictness changes, consult the project's release notes before rolling a release into downstream systems with existing TOML fixtures.
