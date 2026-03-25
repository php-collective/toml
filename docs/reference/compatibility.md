# Compatibility

This page documents the current compatibility expectations for `toml-php`.

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
- `encodeDocument()` provides partial round-trip preservation only when the document carries trivia.

## Round-Trip Editing Contract

For `Toml::parse($input, true)` followed by `Toml::encodeDocument($document)`:

- unchanged parsed nodes keep preserved trivia where the AST represents it
- comments and blank lines can survive re-encoding
- key and string lexical style can survive re-encoding
- arrays and inline tables can preserve collection-local layout
- edited nodes without trivia fall back to canonical local formatting

Canonical local formatting currently means:

- arrays without preserved local trivia encode as `[1, 2]`
- inserted items in multiline parsed arrays reuse inferred indentation when possible
- single-line parsed arrays with synthetic inserted items canonicalize to `[1, 2, 3]` style output
- inline tables without preserved local trivia encode as `{ x = 1, y = 2 }`
- inline tables with synthetic inserted items canonicalize local delimiter spacing

This is an editing aid, not a full formatting engine.

## Upgrade Guidance

When parser strictness changes, consult the project's changelog before rolling a release into downstream systems with existing TOML fixtures.
