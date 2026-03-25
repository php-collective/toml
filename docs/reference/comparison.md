# PHP TOML Library Comparison

This page compares `php-collective/toml` with a few other PHP TOML libraries and documents the local benchmark harness used for spot checks.

## Positioning

`php-collective/toml` is strongest when you need:

- strict parsing and semantic validation
- AST access
- collected parse errors for tooling
- normalized encoding by default
- source-aware encoding as explicit opt-in

It is not positioned as a full formatter. The source-aware encoder aims for minimal diffs where the AST preserves enough local formatting evidence.

## Feature Snapshot

| Capability | php-collective | PetalBranch | internal/toml | devium | yosymfony |
|------------|----------------|-------------|---------------|--------|-----------|
| Modern TOML support | Strong | Strong | Strong | Good | Legacy |
| AST access | Yes | Yes | Yes | No | No |
| Source-aware editing | Yes | Publicly claims stronger round-trip preservation | Publicly claims format preservation | No | No |
| Collected diagnostics | Yes | Less clear publicly | No public equivalent | No | No |
| Normalized encode by default | Yes | Dumper-oriented | Encode-focused | Encode-focused | Builder-oriented |

## Reproducible Benchmarks

The repo includes a local comparison script at [`benchmarks/compare-libraries.php`](/media/mark/data/work/git/toml/benchmarks/compare-libraries.php).

It builds a temporary Composer workspace, installs:

- `php-collective/toml`
- `petalbranch/toml`
- `devium/toml`
- `internal/toml`
- `yosymfony/toml`

and runs three microbenchmarks:

- `decode-baseline`: conservative TOML payload all of them should handle
- `decode-modern`: modern TOML payload for the modern libraries
- `encode-baseline`: encode the same PHP array

Run it with:

```bash
php benchmarks/compare-libraries.php
```

If you prefer Composer:

```bash
composer bench:compare
```

### Current Local Result Shape

The script prints a Markdown report with:

- ops/sec
- median wall-clock time
- per-case notes if a library cannot run a given case

### Caveats

- These are local microbenchmarks, not authoritative published performance claims.
- Results depend on PHP version, CPU, extensions, and payload shape.
- Older libraries may not support the same TOML surface as modern ones, so not every case is equally meaningful for every package.

## toml-test Compliance

Tested against [toml-test](https://github.com/toml-lang/toml-test) v2.1.0:

| Capability | php-collective | PetalBranch |
|------------|---------------|-------------|
| TOML 1.1 valid tests | 99.5% (213/214) | Claims 100% |
| TOML 1.1 invalid tests | 90.3% (421/466) | Claims 99.5% |
| TOML 1.0 valid tests | 99.5% (204/205) | - |
| TOML 1.0 invalid tests | 89.0% (421/473) | - |

The single valid test failure is due to a PHP limitation with null byte property names. Invalid test "failures" are mostly TOML 1.0 strict tests that TOML 1.1 relaxes (multiline inline tables, trailing commas, hex escapes).

## Interpretation

Use the benchmark harness for:

- relative direction
- regression checks after parser/encoder changes
- validating or rejecting performance claims before making them publicly

Do not use it alone for:

- marketing claims like "fastest PHP TOML library"
- broad compliance claims
- memory-footprint claims

Those need a larger benchmark matrix and a more formal write-up.
