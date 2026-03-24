# Upgrading

## Upgrading to the current unreleased branch

Recent roadmap work tightened validation and made `encodeDocument()` more source-aware. The most important downstream changes are:

## Stricter parse and semantic validation

The library now rejects several inputs that older versions may have accepted or mishandled:

- duplicate keys
- duplicate table declarations
- scalar/table and array-table collisions
- invalid string escapes
- malformed numeric literals
- malformed datetime literals

If your application previously relied on permissive parsing, expect more `ParseException` results and update fixtures accordingly.

## `encodeDocument()` behavior

`encodeDocument()` no longer behaves like a pure normalize-and-reencode path for parsed documents with trivia. When the document comes from `Toml::parse($input, true)`, it can preserve:

- comments
- blank lines
- key quoting style
- string style
- table order
- some array and inline-table layout

If you need fully normalized output, prefer `Toml::encode((new Normalizer())->normalize($document))` or parse without trivia preservation.

## Local temporal encoding

Encoding TOML local date, local time, and local datetime values now has an explicit API. Use:

- `PhpCollective\Toml\Value\LocalDate`
- `PhpCollective\Toml\Value\LocalTime`
- `PhpCollective\Toml\Value\LocalDateTime`

Do not rely on plain PHP strings to be emitted as TOML temporal literals.
