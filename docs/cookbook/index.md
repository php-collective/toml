# Cookbook

Common patterns and recipes for working with toml-php.

## Configuration Files

### Loading with Defaults

```php
use PhpCollective\Toml\Toml;

function loadConfig(string $path, array $defaults = []): array
{
    if (!file_exists($path)) {
        return $defaults;
    }

    $config = Toml::decodeFile($path);
    return array_replace_recursive($defaults, $config);
}

// Usage
$config = loadConfig('config.toml', [
    'server' => [
        'host' => '0.0.0.0',
        'port' => 8080,
    ],
    'database' => [
        'driver' => 'sqlite',
    ],
]);
```

### Environment-Specific Config

```php
function loadEnvironmentConfig(string $baseDir): array
{
    $env = getenv('APP_ENV') ?: 'development';

    // Load base config
    $config = Toml::decodeFile("$baseDir/config.toml");

    // Merge environment-specific config
    $envFile = "$baseDir/config.$env.toml";
    if (file_exists($envFile)) {
        $envConfig = Toml::decodeFile($envFile);
        $config = array_replace_recursive($config, $envConfig);
    }

    return $config;
}
```

### Config Validation

```php
function validateConfig(array $config): void
{
    $required = ['database.host', 'database.port'];

    foreach ($required as $key) {
        $parts = explode('.', $key);
        $value = $config;
        foreach ($parts as $part) {
            if (!isset($value[$part])) {
                throw new InvalidArgumentException("Missing required config: $key");
            }
            $value = $value[$part];
        }
    }
}
```

## Error Handling

### IDE/Linter Integration

```php
use PhpCollective\Toml\Toml;

function lintTomlFile(string $path): array
{
    $content = file_get_contents($path);
    $result = Toml::tryParse($content);

    $diagnostics = [];
    foreach ($result->getErrors() as $error) {
        $diagnostics[] = [
            'file' => $path,
            'line' => $error->span->line,
            'column' => $error->span->column,
            'severity' => 'error',
            'message' => $error->message,
        ];
    }

    return $diagnostics;
}
```

### User-Friendly Error Messages

```php
function parseConfigWithFriendlyErrors(string $path): array
{
    $content = file_get_contents($path);
    $result = Toml::tryParse($content);

    if (!$result->isValid()) {
        $messages = [];
        foreach ($result->getErrors() as $error) {
            $messages[] = sprintf(
                "Config error in %s (line %d): %s",
                basename($path),
                $error->span->line,
                $error->message
            );
        }
        throw new ConfigException(implode("\n", $messages));
    }

    return $result->getValue();
}
```

## AST Manipulation

### Finding Keys by Path

```php
use PhpCollective\Toml\Ast\Document;
use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Table;
use PhpCollective\Toml\Ast\Value\Value;

function findValue(Document $doc, string $path): ?Value
{
    $parts = explode('.', $path);
    $items = $doc->items;

    while (count($parts) > 1) {
        $key = array_shift($parts);

        // Find table with this key
        foreach ($items as $item) {
            if ($item instanceof Table && $item->key->parts === [$key]) {
                $items = $item->items;
                continue 2;
            }
        }
        return null; // Table not found
    }

    // Find the final key-value
    $key = $parts[0];
    foreach ($items as $item) {
        if ($item instanceof KeyValue && $item->key->parts === [$key]) {
            return $item->value;
        }
    }

    return null;
}
```

### Listing All Keys

```php
function listAllKeys(Document $doc, string $prefix = ''): array
{
    $keys = [];

    foreach ($doc->items as $item) {
        if ($item instanceof KeyValue) {
            $keyPath = $prefix . implode('.', $item->key->parts);
            $keys[] = $keyPath;
        } elseif ($item instanceof Table) {
            $tablePath = $prefix . implode('.', $item->key->parts);
            foreach ($item->items as $kv) {
                $keys[] = $tablePath . '.' . implode('.', $kv->key->parts);
            }
        }
    }

    return $keys;
}
```

## Type Conversion

### DateTime Handling

```php
function normalizeDateTime(array $config): array
{
    array_walk_recursive($config, function (&$value) {
        if (is_string($value)) {
            // Convert local date strings to DateTime
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $value = DateTimeImmutable::createFromFormat('Y-m-d', $value)
                    ->setTime(0, 0, 0);
            }
            // Convert local datetime strings
            elseif (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $value)) {
                $value = new DateTimeImmutable($value);
            }
        }
    });

    return $config;
}
```

### Duration Strings

TOML doesn't have a duration type. Parse them manually:

```toml
[timeouts]
connect = "30s"
read = "5m"
idle = "1h"
```

```php
function parseDuration(string $duration): int
{
    if (preg_match('/^(\d+)(s|m|h|d)$/', $duration, $matches)) {
        $value = (int)$matches[1];
        return match ($matches[2]) {
            's' => $value,
            'm' => $value * 60,
            'h' => $value * 3600,
            'd' => $value * 86400,
        };
    }
    throw new InvalidArgumentException("Invalid duration: $duration");
}

$config = Toml::decodeFile('config.toml');
$connectTimeout = parseDuration($config['timeouts']['connect']); // 30
```

## Encoding Patterns

### Generating Config Files

```php
function generateDefaultConfig(): string
{
    return Toml::encode([
        'app' => [
            'name' => 'My Application',
            'version' => '1.0.0',
            'debug' => false,
        ],
        'server' => [
            'host' => '0.0.0.0',
            'port' => 8080,
        ],
        'database' => [
            'driver' => 'mysql',
            'host' => 'localhost',
            'port' => 3306,
            'name' => 'myapp',
        ],
    ]);
}

// Write to file
file_put_contents('config.toml', generateDefaultConfig());
```

### Merging and Saving

```php
function updateConfig(string $path, array $updates): void
{
    $config = file_exists($path)
        ? Toml::decodeFile($path)
        : [];

    $config = array_replace_recursive($config, $updates);

    file_put_contents($path, Toml::encode($config));
}

// Usage
updateConfig('config.toml', [
    'server' => ['port' => 9000],
]);
```

## Framework Integration

### Laravel Service Provider

```php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PhpCollective\Toml\Toml;

class TomlConfigServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('toml', fn() => new class {
            public function load(string $path): array
            {
                return Toml::decodeFile($path);
            }
        });
    }

    public function boot(): void
    {
        // Load additional TOML config
        $tomlConfig = config_path('app.toml');
        if (file_exists($tomlConfig)) {
            config(Toml::decodeFile($tomlConfig));
        }
    }
}
```

### Symfony Bundle

```php
namespace App\DependencyInjection;

use PhpCollective\Toml\Toml;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

class TomlExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configFile = $container->getParameter('kernel.project_dir') . '/config/app.toml';

        if (file_exists($configFile)) {
            $tomlConfig = Toml::decodeFile($configFile);
            foreach ($tomlConfig as $key => $value) {
                $container->setParameter("app.$key", $value);
            }
        }
    }
}
```
