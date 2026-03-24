# Changelog

All notable changes to this project should be documented in this file.

The format is based on Keep a Changelog and the project aims to follow Semantic Versioning for public API changes.

## Unreleased

### Added

- Explicit support matrix and limitations documentation.
- Local temporal encoder wrapper types for TOML local date/time/datetime values.
- Partial AST trivia preservation for document items, table entries, arrays, and inline tables.
- Partial round-trip encoding support via `Toml::encodeDocument()` for parsed documents with trivia.
- Fixture-style conformance tests for valid, invalid, semantic, and round-trip cases.

### Changed

- `encodeDocument()` now preserves more parsed source structure instead of always normalizing output.
- Parser and normalizer validation are stricter for duplicate keys, duplicate tables, key/table collisions, invalid escapes, invalid numeric literals, and malformed datetime input.

### Notes

- Parser strictness has increased. Invalid TOML that may have been accepted before is now rejected.
- Round-trip preservation is still partial and should not be treated as a formatter guarantee.
