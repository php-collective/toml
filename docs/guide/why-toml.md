# Why TOML?

TOML (Tom's Obvious Minimal Language) is a configuration file format designed to be easy to read and write, with clear semantics.

## TOML vs Other Formats

| Feature | TOML | JSON | YAML | INI |
|---------|------|------|------|-----|
| Comments | ✅ `# comment` | ❌ | ✅ `# comment` | ✅ `; comment` |
| Types | ✅ Strong | ✅ Strong | ⚠️ Implicit | ❌ All strings |
| Date/Time | ✅ Native | ❌ Strings | ✅ Native | ❌ |
| Nested Tables | ✅ Clean | ✅ Verbose | ✅ Indentation | ⚠️ Limited |
| Human Readable | ✅ Excellent | ⚠️ Good | ⚠️ Whitespace-sensitive | ✅ Simple |
| Trailing Commas | ✅ Arrays only | ❌ | N/A | N/A |

## Key Advantages

### 1. Explicit Types

TOML has clear, unambiguous types:

```toml
string = "hello"
integer = 42
float = 3.14
boolean = true
datetime = 2024-01-15T10:30:00Z
array = [1, 2, 3]
```

Unlike YAML where `yes`, `no`, `on`, `off` are booleans and `1.0` might be a string.

### 2. Readable Table Syntax

Complex nested structures are easy to read:

```toml
[database]
host = "localhost"
port = 5432

[database.pool]
min = 5
max = 20

[[servers]]
name = "alpha"
ip = "10.0.0.1"

[[servers]]
name = "beta"
ip = "10.0.0.2"
```

### 3. Comments

First-class comment support:

```toml
# Database configuration
[database]
host = "localhost"  # Primary host
port = 5432         # PostgreSQL default
```

### 4. No Indentation Sensitivity

Unlike YAML, whitespace doesn't affect semantics:

```toml
[servers]
    host = "localhost"  # Indentation is optional
port = 8080             # This is equally valid
```

### 5. Native Date/Time

Built-in support for ISO 8601 dates:

```toml
created = 2024-01-15T10:30:00Z      # Offset datetime
updated = 2024-01-15T10:30:00       # Local datetime
date = 2024-01-15                    # Local date
time = 10:30:00                      # Local time
```

## When to Use TOML

**Best for:**
- Application configuration files
- Settings and preferences
- Simple structured data
- Human-edited files

**Consider alternatives for:**
- Data interchange (use JSON)
- Complex document structures (use YAML)
- Simple key-value pairs (use INI or .env)

## Real-World Usage

TOML is used by:

- **Cargo** (Rust package manager) - `Cargo.toml`
- **Python** (PEP 518) - `pyproject.toml`
- **Hugo** (static site generator) - configuration
- **Composer 2.x** - supports `composer.toml` alongside JSON
- **Many CLI tools** - configuration files
