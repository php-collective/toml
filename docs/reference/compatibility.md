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
- fallback decisions are local to the edited collection, so outer preserved layout can survive while nested edited collections normalize

Canonical local formatting currently means:

- arrays without preserved local trivia encode as `[1, 2]`
- inserted items in multiline parsed arrays reuse inferred indentation when possible
- single-line parsed arrays with inserted, removed, or synthetic replaced items canonicalize to `[1, 2, 3]` style output
- inline tables without preserved local trivia encode as `{ x = 1, y = 2 }`
- inline tables with inserted, removed, or synthetic replaced items canonicalize local delimiter spacing

This is an editing aid, not a full formatting engine.

The repository includes edited-document fixture tests for common mutation paths, but that corpus is still curated and should not be read as full formatter-level coverage.

## Upgrade Guidance

When parser strictness changes, consult the project's changelog before rolling a release into downstream systems with existing TOML fixtures.
