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
| toml-test compliance | 76% valid / 89% invalid | PetalBranch claims higher |

**Unique strengths:**
- Only library with error recovery for IDE/tooling workflows
- Source-aware encoding preserves original formatting in unchanged regions
- Balanced PHP version support (8.2+) with modern TOML 1.1 features

## Choosing a Library

**Choose php-collective/toml when you need:**
- TOML 1.1 features with PHP 8.2 compatibility
- Error recovery for IDE/tooling integration
- AST access with comment preservation
- Source-aware round-trip editing

**Choose PetalBranch/toml when you need:**
- Maximum TOML 1.1 compliance
- PHP 8.3+ is acceptable

**Choose internal/toml when you need:**
- Stable TOML 1.0 support
- PHP 8.1 compatibility

**Choose vanodevium/toml when you need:**
- Simple decode/encode API
- No AST or comment preservation needed
