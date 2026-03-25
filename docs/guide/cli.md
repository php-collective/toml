# CLI Tools

Command-line utilities for working with TOML files.

## toml-validate

Validate TOML file syntax from the command line. Useful for CI/CD pipelines, pre-commit hooks, and editor integrations.

### Installation

The validator is included with the package:

```bash
composer require php-collective/toml
```

After installation, the command is available at `vendor/bin/toml-validate`.

### Usage

```bash
# Validate a file
vendor/bin/toml-validate config.toml

# Pipe from stdin
cat config.toml | vendor/bin/toml-validate

# Quiet mode (no output on success)
vendor/bin/toml-validate config.toml -q

# With JSON Schema validation
vendor/bin/toml-validate config.toml --schema=schema.json
```

### Exit Codes

| Code | Meaning |
|------|---------|
| 0 | Valid TOML |
| 1 | Invalid TOML (syntax errors) |
| 2 | Schema validation failed |
| 3 | File not found or read error |

### Error Output

When validation fails, the tool displays formatted error messages with context:

```bash
$ vendor/bin/toml-validate broken.toml
Syntax errors in broken.toml:

Parse error: Expected value

  3 | [server]
  4 | host = "localhost"
  5 | port =
    |        ^

Hint: A value is required after '='
```

### CI/CD Integration

#### GitHub Actions

```yaml
- name: Validate TOML configs
  run: |
    for file in config/*.toml; do
      vendor/bin/toml-validate "$file"
    done
```

#### GitLab CI

```yaml
validate-config:
  script:
    - vendor/bin/toml-validate config.toml
```

#### Pre-commit Hook

Add to `.git/hooks/pre-commit`:

```bash
#!/bin/bash
for file in $(git diff --cached --name-only --diff-filter=ACM | grep '\.toml$'); do
    if ! vendor/bin/toml-validate "$file"; then
        echo "TOML validation failed for $file"
        exit 1
    fi
done
```

### Schema Validation

For structural validation beyond syntax, use the `--schema` flag with a JSON Schema file:

```bash
vendor/bin/toml-validate config.toml --schema=config-schema.json
```

::: warning
Schema validation requires the `justinrainbow/json-schema` package:

```bash
composer require justinrainbow/json-schema
```
:::

Example schema (`config-schema.json`):

```json
{
  "$schema": "https://json-schema.org/draft-07/schema#",
  "type": "object",
  "required": ["database"],
  "properties": {
    "database": {
      "type": "object",
      "required": ["host", "port"],
      "properties": {
        "host": { "type": "string" },
        "port": { "type": "integer", "minimum": 1, "maximum": 65535 }
      }
    }
  }
}
```

### Scripting Examples

Check validity in scripts:

```bash
# Exit on invalid TOML
vendor/bin/toml-validate config.toml || exit 1

# Conditional logic
if vendor/bin/toml-validate config.toml -q; then
    echo "Config is valid"
else
    echo "Config has errors"
fi
```

Validate multiple files:

```bash
# Validate all TOML files in a directory
find . -name "*.toml" -exec vendor/bin/toml-validate {} \;

# Stop on first error
find . -name "*.toml" | while read -r file; do
    vendor/bin/toml-validate "$file" || exit 1
done
```
