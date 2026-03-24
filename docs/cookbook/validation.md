# Schema Validation

Strategies for validating TOML configuration against expected schemas.

## Basic Type Checking

```php
function validateType(mixed $value, string $expected): bool
{
    return match ($expected) {
        'string' => is_string($value),
        'int', 'integer' => is_int($value),
        'float', 'double' => is_float($value) || is_int($value),
        'bool', 'boolean' => is_bool($value),
        'array' => is_array($value),
        default => true,
    };
}
```

## Schema Definition

Define expected structure as an array:

```php
$schema = [
    'app' => [
        'type' => 'table',
        'required' => true,
        'fields' => [
            'name' => ['type' => 'string', 'required' => true],
            'version' => ['type' => 'string', 'pattern' => '/^\d+\.\d+\.\d+$/'],
            'debug' => ['type' => 'boolean', 'default' => false],
        ],
    ],
    'server' => [
        'type' => 'table',
        'fields' => [
            'host' => ['type' => 'string', 'default' => 'localhost'],
            'port' => ['type' => 'integer', 'min' => 1, 'max' => 65535],
            'ssl' => ['type' => 'boolean', 'default' => false],
        ],
    ],
    'database' => [
        'type' => 'table',
        'required' => true,
        'fields' => [
            'driver' => ['type' => 'string', 'enum' => ['mysql', 'pgsql', 'sqlite']],
            'host' => ['type' => 'string'],
            'port' => ['type' => 'integer'],
            'name' => ['type' => 'string', 'required' => true],
            'pool' => [
                'type' => 'table',
                'fields' => [
                    'min' => ['type' => 'integer', 'min' => 1],
                    'max' => ['type' => 'integer', 'min' => 1],
                ],
            ],
        ],
    ],
];
```

## Validation Implementation

```php
class ConfigValidator
{
    private array $errors = [];

    public function validate(array $config, array $schema): bool
    {
        $this->errors = [];
        $this->validateTable($config, $schema, '');
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function validateTable(array $data, array $schema, string $path): void
    {
        foreach ($schema as $key => $rules) {
            $fieldPath = $path ? "$path.$key" : $key;
            $value = $data[$key] ?? null;

            // Check required
            if (($rules['required'] ?? false) && $value === null) {
                $this->errors[] = "Missing required field: $fieldPath";
                continue;
            }

            if ($value === null) {
                continue;
            }

            // Check type
            $type = $rules['type'] ?? 'any';
            if ($type === 'table') {
                if (!is_array($value) || array_is_list($value)) {
                    $this->errors[] = "Expected table at $fieldPath";
                    continue;
                }
                if (isset($rules['fields'])) {
                    $this->validateTable($value, $rules['fields'], $fieldPath);
                }
            } elseif (!$this->checkType($value, $type)) {
                $this->errors[] = "Invalid type at $fieldPath: expected $type";
                continue;
            }

            // Check constraints
            $this->validateConstraints($value, $rules, $fieldPath);
        }
    }

    private function checkType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'integer' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'boolean' => is_bool($value),
            'array' => is_array($value) && array_is_list($value),
            'table' => is_array($value) && !array_is_list($value),
            'any' => true,
            default => false,
        };
    }

    private function validateConstraints(mixed $value, array $rules, string $path): void
    {
        // Min/Max for numbers
        if (isset($rules['min']) && $value < $rules['min']) {
            $this->errors[] = "$path must be >= {$rules['min']}";
        }
        if (isset($rules['max']) && $value > $rules['max']) {
            $this->errors[] = "$path must be <= {$rules['max']}";
        }

        // Pattern for strings
        if (isset($rules['pattern']) && is_string($value)) {
            if (!preg_match($rules['pattern'], $value)) {
                $this->errors[] = "$path does not match pattern {$rules['pattern']}";
            }
        }

        // Enum
        if (isset($rules['enum']) && !in_array($value, $rules['enum'], true)) {
            $allowed = implode(', ', $rules['enum']);
            $this->errors[] = "$path must be one of: $allowed";
        }

        // Min/Max length for strings and arrays
        if (isset($rules['minLength'])) {
            $length = is_string($value) ? strlen($value) : count($value);
            if ($length < $rules['minLength']) {
                $this->errors[] = "$path length must be >= {$rules['minLength']}";
            }
        }
        if (isset($rules['maxLength'])) {
            $length = is_string($value) ? strlen($value) : count($value);
            if ($length > $rules['maxLength']) {
                $this->errors[] = "$path length must be <= {$rules['maxLength']}";
            }
        }
    }
}
```

## Usage

```php
$config = Toml::decodeFile('config.toml');

$validator = new ConfigValidator();
if (!$validator->validate($config, $schema)) {
    foreach ($validator->getErrors() as $error) {
        echo "Config error: $error\n";
    }
    exit(1);
}

// Config is valid, proceed
```

## Applying Defaults

```php
function applyDefaults(array $config, array $schema, string $path = ''): array
{
    foreach ($schema as $key => $rules) {
        if (!isset($config[$key]) && isset($rules['default'])) {
            $config[$key] = $rules['default'];
        }

        if (isset($config[$key]) && ($rules['type'] ?? '') === 'table' && isset($rules['fields'])) {
            $config[$key] = applyDefaults($config[$key], $rules['fields']);
        }
    }

    return $config;
}

// Usage
$config = applyDefaults($config, $schema);
```

## JSON Schema Alternative

For complex validation, consider using JSON Schema with a library:

```php
use JsonSchema\Validator;

$config = Toml::decodeFile('config.toml');

$validator = new Validator();
$validator->validate(
    json_decode(json_encode($config)),  // Convert to stdClass
    (object)['$ref' => 'file://' . realpath('schema.json')]
);

if (!$validator->isValid()) {
    foreach ($validator->getErrors() as $error) {
        echo sprintf("[%s] %s\n", $error['property'], $error['message']);
    }
}
```

## IDE Support with JSON Schema

Create a JSON Schema for IDE autocompletion:

```json
{
  "$schema": "https://json-schema.org/draft-07/schema#",
  "type": "object",
  "properties": {
    "server": {
      "type": "object",
      "properties": {
        "host": { "type": "string", "default": "localhost" },
        "port": { "type": "integer", "minimum": 1, "maximum": 65535 }
      },
      "required": ["port"]
    }
  }
}
```

Some IDEs (VS Code with Even Better TOML) can use JSON Schema for TOML validation.
