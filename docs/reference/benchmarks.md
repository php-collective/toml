# Benchmarks

A reproducible comparison of PHP TOML libraries for decode and encode throughput.

::: warning These are indicative numbers
The figures below come from a single machine and a single run on small documents. They are useful for relative comparison, not as absolute guarantees. Re-run them on your own hardware and workload before relying on any of it — see [Reproduce](#reproduce).
:::

## Method

- The harness builds a throwaway Composer project that installs every library, then times each one over several rounds and reports the median.
- Two decode documents are used: a *baseline* TOML 1.0 document and a *modern* document exercising TOML 1.1 features (inline tables, offset/local date-times). Encoding uses the baseline PHP array.
- Metric: operations per second (higher is better), median wall-clock across 5 rounds.
- Environment for the numbers shown: PHP 8.4, `php-collective/toml` against the other current PHP libraries.

## Results

### Decode — baseline document

| Library | Ops/s |
|---------|------:|
| internal | 1031 |
| petalbranch | 1029 |
| **php-collective** | **1019** |
| yosymfony | 777 |
| devium | 435 |

### Decode — modern document (TOML 1.1 features)

| Library | Ops/s | Notes |
|---------|------:|-------|
| internal | 1418 | |
| petalbranch | 954 | |
| **php-collective** | **848** | |
| devium | 505 | |
| yosymfony | — | does not parse the TOML 1.1 syntax used here |

### Encode — baseline document

| Library | Ops/s | Notes |
|---------|------:|-------|
| **php-collective** | **17504** | |
| devium | 15448 | |
| internal | 5832 | |
| petalbranch | 5653 | |
| yosymfony | — | fluent builder only; no array-to-TOML encode |

## Takeaways

::: tip Encoding
`php-collective/toml` is the fastest encoder in this comparison — roughly 3x the throughput of `internal` and `petalbranch`, and a little ahead of `devium`.
:::

- **Decoding is competitive, not the outright leader.** On the baseline document it lands within about one percent of the fastest libraries. On the modern document the leaner pure-decoders (`internal`, `petalbranch`) pull ahead.
- That gap is the cost of what the decoder produces: a full AST with trivia, collected diagnostics, and strict validation — work the fastest decoders skip. For analysis, tooling, and source-aware editing this is a deliberate trade; for raw decode-only throughput a leaner library wins.

## Reproduce

```bash
composer bench:compare
```

This installs each library into a temporary project, runs the benchmark, and prints a Markdown report. Results vary by host, PHP build, and document shape — always benchmark your own workload before drawing conclusions.
