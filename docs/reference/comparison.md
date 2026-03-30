# PHP TOML Library Comparison

This page gives a high-level ecosystem snapshot and documents the local benchmark harness used for spot checks.

For exact current support, round-trip guarantees, and `toml-test` numbers for this library, use the [Support Matrix](https://php-collective.github.io/toml/reference/support-matrix) and [Compatibility](https://php-collective.github.io/toml/reference/compatibility) pages.

## Positioning

`php-collective/toml` is strongest when you need:

- strict parsing and semantic validation
- AST access
- collected parse errors for tooling workflows
- normalized encoding by default
- source-aware and format-preserving encoding as explicit opt-in

It is not positioned as a full TOML formatter. In `DocumentFormattingMode::SourceAware`, the encoder aims for minimal diffs where the AST preserves enough local formatting evidence.

## Quick Snapshot

This is intentionally a coarse product-level snapshot, not a replacement for the [Support Matrix](https://php-collective.github.io/toml/reference/support-matrix).

| Capability                                | php-collective | PetalBranch | internal/toml | devium | yosymfony |
|-------------------------------------------|----------------|-------------|---------------|--------|-----------|
| Modern TOML focus                         | ✅              | ✅           | ✅             | ✅      | ❌         |
| AST access                                | ✅              | ✅           | ✅             | ❌      | ❌         |
| Format-preserving workflow (minimal diff) | ✅              | ✅           | ✅             | ❌      | ❌         |
| Canonical/normalized mode                 | ✅              | ❌           | ❌             | ❌      | ❌         |
| Rich error diagnostics                    | ✅              | ✅           | ❌             | ❌      | ❌         |
| Multi-error collection                    | ✅              | ❌           | ❌             | ❌      | ❌         |

## Ecosystem Notes

This table keeps the more descriptive comparison:

| Package | Publicly visible strengths | Main tradeoff relative to this library |
|---------|----------------------------|----------------------------------------|
| `php-collective/toml` | Strict validation, AST access, collected diagnostics, source-aware re-encoding | Source-aware editing is strong, but still not a full formatter |
| `petalbranch/toml` | Strong TOML 1.1 positioning, lossless redump claims, stronger published compliance claims | Requires newer PHP and is more dumper-oriented than normalized-by-default |
| `internal/toml` | Format-preserving and round-trip oriented API | Less tooling-oriented diagnostics publicly |
| `devium/toml` | Simple encode/decode API and temporal helper types | No public AST/editing workflow |
| `yosymfony/toml` | Mature legacy parser and builder API | Older TOML scope and legacy project status |

Treat competitor compliance and preservation claims as vendor-reported unless you verify them independently.

## Reproducible Benchmarks

The repo includes a local comparison script at [`benchmarks/compare-libraries.php`](https://github.com/php-collective/toml/blob/master/benchmarks/compare-libraries.php).

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

### Current Local Snapshot

Latest local run on PHP `8.4.18`:

#### decode-baseline

| Library        | Ops/s | Median ms |
|----------------|------:|----------:|
| php-collective |  4900 |       612 |
| yosymfony      |  4500 |       667 |
| internal       |  4400 |       682 |
| petalbranch    |  4100 |       732 |
| devium         |  2000 |      1500 |

#### decode-modern

| Library        | Ops/s | Median ms |
|----------------|------:|----------:|
| internal       |  5100 |       490 |
| php-collective |  4800 |       521 |
| petalbranch    |  4200 |       595 |
| devium         |  2200 |      1136 |

#### encode-baseline

| Library        | Ops/s | Median ms |
|----------------|------:|----------:|
| php-collective | 84000 |        30 |
| devium         | 77500 |        32 |
| internal       | 34000 |        74 |
| petalbranch    | 28000 |        89 |

The values are now rounded averages, which better represent typical performance than any single run.

### Caveats

- These are local microbenchmarks, not authoritative published performance claims.
- Results depend on PHP version, CPU, extensions, and payload shape.
- Older libraries may not support the same TOML surface as modern ones, so not every case is equally meaningful for every package.
- The benchmark harness is best used for local regression checks and directional comparisons, not marketing claims.

## Compliance Note

Exact `toml-test` results for `php-collective/toml` live in the [Support Matrix](https://php-collective.github.io/toml/reference/support-matrix). Keeping the precise numbers there avoids repeating compliance tables across multiple pages.

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
