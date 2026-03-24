# Configuration Files

Best practices for using TOML as application configuration.

## File Organization

### Single File

For simple applications:

```
myapp/
├── config.toml
└── src/
```

```toml
# config.toml
[app]
name = "My Application"
debug = false

[server]
host = "0.0.0.0"
port = 8080

[database]
driver = "mysql"
host = "localhost"
port = 3306
```

### Multiple Files

For complex applications:

```
myapp/
├── config/
│   ├── app.toml
│   ├── database.toml
│   ├── cache.toml
│   └── services.toml
└── src/
```

```php
function loadAllConfig(string $configDir): array
{
    $config = [];

    foreach (glob("$configDir/*.toml") as $file) {
        $key = basename($file, '.toml');
        $config[$key] = Toml::decodeFile($file);
    }

    return $config;
}
```

### Environment Overrides

```
config/
├── app.toml           # Base config
├── app.development.toml
├── app.staging.toml
└── app.production.toml
```

```php
function loadConfig(string $env = 'development'): array
{
    $base = Toml::decodeFile('config/app.toml');

    $envFile = "config/app.$env.toml";
    if (file_exists($envFile)) {
        $envConfig = Toml::decodeFile($envFile);
        return array_replace_recursive($base, $envConfig);
    }

    return $base;
}
```

## Common Patterns

### Feature Flags

```toml
[features]
new_dashboard = false
beta_api = true
experimental_cache = false

[features.rollout]
new_dashboard = 0.1  # 10% of users
beta_api = 1.0       # All users
```

```php
function isFeatureEnabled(string $feature, ?string $userId = null): bool
{
    $config = getConfig();

    // Check simple boolean flag
    if (isset($config['features'][$feature])) {
        $value = $config['features'][$feature];
        if (is_bool($value)) {
            return $value;
        }
    }

    // Check percentage rollout
    if (isset($config['features']['rollout'][$feature]) && $userId) {
        $percentage = $config['features']['rollout'][$feature];
        $hash = crc32($userId . $feature) / 4294967295;
        return $hash < $percentage;
    }

    return false;
}
```

### Database Configuration

```toml
[database]
driver = "mysql"
charset = "utf8mb4"
collation = "utf8mb4_unicode_ci"

[database.connections.default]
host = "localhost"
port = 3306
database = "myapp"
username = "root"
password = ""

[database.connections.readonly]
host = "replica.db.local"
port = 3306
database = "myapp"
username = "readonly"
password = ""

[database.pool]
min = 5
max = 20
idle_timeout = 300
```

### Logging Configuration

```toml
[logging]
level = "info"
format = "json"

[[logging.handlers]]
type = "file"
path = "/var/log/app.log"
level = "debug"

[[logging.handlers]]
type = "stderr"
level = "error"

[[logging.handlers]]
type = "syslog"
facility = "local0"
level = "warning"
```

### API Configuration

```toml
[api]
base_url = "https://api.example.com"
version = "v2"
timeout = 30

[api.auth]
type = "bearer"
token_env = "API_TOKEN"  # Read from environment

[api.retry]
max_attempts = 3
backoff_ms = [100, 500, 2000]

[api.endpoints]
users = "/users"
orders = "/orders"
products = "/products"
```

## Validation

### Schema Validation

```php
function validateSchema(array $config, array $schema): array
{
    $errors = [];

    foreach ($schema as $path => $rules) {
        $value = getByPath($config, $path);

        if (isset($rules['required']) && $rules['required'] && $value === null) {
            $errors[] = "Missing required field: $path";
            continue;
        }

        if ($value !== null && isset($rules['type'])) {
            $type = gettype($value);
            if ($type !== $rules['type']) {
                $errors[] = "Invalid type for $path: expected {$rules['type']}, got $type";
            }
        }

        if (isset($rules['min']) && $value < $rules['min']) {
            $errors[] = "$path must be at least {$rules['min']}";
        }

        if (isset($rules['max']) && $value > $rules['max']) {
            $errors[] = "$path must be at most {$rules['max']}";
        }
    }

    return $errors;
}

// Usage
$schema = [
    'server.port' => ['required' => true, 'type' => 'integer', 'min' => 1, 'max' => 65535],
    'server.host' => ['required' => true, 'type' => 'string'],
    'database.pool.max' => ['type' => 'integer', 'min' => 1],
];

$errors = validateSchema($config, $schema);
```

### Environment Variable Substitution

```toml
[database]
host = "${DB_HOST:localhost}"
password = "${DB_PASSWORD}"
```

```php
function substituteEnvVars(array $config): array
{
    array_walk_recursive($config, function (&$value) {
        if (is_string($value) && preg_match('/^\$\{(\w+)(?::([^}]*))?\}$/', $value, $matches)) {
            $envVar = $matches[1];
            $default = $matches[2] ?? null;

            $value = getenv($envVar) ?: $default;

            if ($value === null) {
                throw new RuntimeException("Missing environment variable: $envVar");
            }
        }
    });

    return $config;
}
```

## Caching

### File-Based Cache

```php
function loadConfigCached(string $path, string $cacheDir): array
{
    $cacheFile = $cacheDir . '/' . md5($path) . '.php';
    $configMtime = filemtime($path);

    if (file_exists($cacheFile) && filemtime($cacheFile) > $configMtime) {
        return require $cacheFile;
    }

    $config = Toml::decodeFile($path);

    $export = '<?php return ' . var_export($config, true) . ';';
    file_put_contents($cacheFile, $export);

    return $config;
}
```

### APCu Cache

```php
function loadConfigApcCached(string $path): array
{
    $cacheKey = 'toml:' . md5($path);
    $mtime = filemtime($path);

    $cached = apcu_fetch($cacheKey);
    if ($cached !== false && $cached['mtime'] === $mtime) {
        return $cached['config'];
    }

    $config = Toml::decodeFile($path);
    apcu_store($cacheKey, ['mtime' => $mtime, 'config' => $config]);

    return $config;
}
```
