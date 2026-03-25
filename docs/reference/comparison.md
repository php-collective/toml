# PHP TOML Library Comparison

A comparison of PHP TOML libraries as of March 2025.

## Feature Comparison

| Feature | php-collective | PetalBranch | internal/toml | vanodevium |
|---------|---------------|-------------|---------------|------------|
| TOML 1.0 | ✅ | ✅ | ✅ | ✅ |
| TOML 1.1 escapes (`\e`, `\xHH`) | ✅ | ✅ | ❌ | ❌ |
| Multiline inline tables | ✅ | ✅ | ❌ | ❌ |
| PHP version | 8.2+ | 8.3+ | 8.1+ | 8.x |
| AST access | ✅ | ✅ | ✅ | ❌ |
| Comment preservation | ✅ | ✅ | ✅ | ❌ |
| Error recovery | ✅ | ? | ? | ❌ |
| File loading | ✅ | ✅ | ✅ | ✅ |

## Library Details

### php-collective/toml (this library)

- **TOML Version**: 1.0 + 1.1 features
- **PHP Version**: 8.2+
- **License**: MIT
- **Key Features**:
  - Full AST with span information
  - Trivia (comments, whitespace) preservation
  - Error recovery for IDE/tooling integration
  - Source-aware re-encoding
  - Zero required dependencies

### PetalBranch/toml

- **TOML Version**: 1.1.0 (latest)
- **PHP Version**: 8.3+
- **License**: Unknown
- **Key Features**:
  - Full TOML 1.1 support
  - O(1) memory lexer
  - PHPStan Level 9 compliance
  - Claims 100%/99.5% toml-test compliance

### internal/toml

- **TOML Version**: 1.0.0
- **PHP Version**: 8.1+
- **License**: BSD-3-Clause
- **Downloads**: ~48,000
- **Key Features**:
  - Format preservation
  - JsonSerializable integration
  - Round-trip support

### vanodevium/toml

- **TOML Version**: 1.0.0
- **PHP Version**: PHP 8.x
- **License**: MIT
- **Key Features**:
  - Specialized DateTime classes
  - Simple API (`toml_decode()`, `toml_encode()`)
  - Listed on official TOML wiki

### yosymfony/toml (legacy)

- **TOML Version**: 0.4.0 (outdated)
- **PHP Version**: 7.1+
- **License**: MIT
- **Stars**: 208 (most popular)
- **Status**: Stale since 2018
- **Key Features**:
  - TomlBuilder fluent API
  - PSR-2 compliant

## Competitive Position

| Capability | php-collective | vs Others |
|------------|---------------|-----------|
| TOML 1.1 support | ✅ Full | Only PetalBranch matches |
| PHP 8.2 compatible | ✅ | PetalBranch requires 8.3+ |
| Error recovery | ✅ Multiple errors | Most fail on first error |
| AST access | ✅ Full spans | internal/toml, PetalBranch only |
| Trivia preservation | ✅ Comments + whitespace | Rare in PHP ecosystem |
| Source-aware encoding | ✅ Preserves formatting | Unique feature |
| Zero dependencies | ✅ Pure PHP | Common |
| toml-test compliance | 99.5% valid / 90.3% invalid | PetalBranch claims higher |

**Unique strengths:**
- Only library with error recovery for IDE/tooling workflows
- Source-aware encoding preserves original formatting in unchanged regions
- Balanced PHP version support (8.2+) with modern TOML 1.1 features

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

### Current Local Snapshot

Latest local run on PHP `8.4.18`:

#### decode-baseline

| Library | Ops/s | Median ms |
|---------|------:|----------:|
| php-collective | 4736 | 633.51 |
| yosymfony | 4552 | 659.01 |
| petalbranch | 4379 | 685.09 |
| devium | 1620 | 1852.14 |
| internal | 1464 | 2049.42 |

#### decode-modern

| Library | Ops/s | Median ms |
|---------|------:|----------:|
| php-collective | 5350 | 467.25 |
| internal | 5294 | 472.25 |
| petalbranch | 4595 | 544.08 |
| devium | 1022 | 2445.91 |

#### encode-baseline

| Library | Ops/s | Median ms |
|---------|------:|----------:|
| devium | 93465 | 26.75 |
| php-collective | 80863 | 30.92 |
| internal | 34735 | 71.97 |
| petalbranch | 28996 | 86.22 |

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
