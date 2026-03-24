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

### Editing While Preserving Layout

```php
use PhpCollective\Toml\Ast\Key;
use PhpCollective\Toml\Ast\KeyStyle;
use PhpCollective\Toml\Ast\KeyValue;
use PhpCollective\Toml\Ast\Value\IntegerBase;
use PhpCollective\Toml\Ast\Value\IntegerValue;
use PhpCollective\Toml\Lexer\Span;

$document = Toml::parse("point = { x = 1 }\n", true);
$inlineTable = $document->items[0]->value;

$inlineTable->items[] = new KeyValue(
    new Key(['y'], [KeyStyle::Bare], new Span(0, 0, 1, 1)),
    new IntegerValue(2, IntegerBase::Decimal, new Span(0, 0, 1, 1)),
    new Span(0, 0, 1, 1),
);

echo Toml::encodeDocument($document);
// point = { x = 1, y = 2 }
```

When inserted nodes do not carry trivia, `encodeDocument()` falls back to canonical local formatting instead of trying to guess arbitrary whitespace.

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

### Encoding Local Temporal Values

Use explicit wrappers for TOML local date/time literals:

```php
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\Value\LocalDate;
use PhpCollective\Toml\Value\LocalDateTime;
use PhpCollective\Toml\Value\LocalTime;

$toml = Toml::encode([
    'event' => [
        'date' => new LocalDate('2024-12-25'),
        'start_time' => new LocalTime('09:00:00'),
        'created' => new LocalDateTime('2024-01-15T10:30:00'),
    ],
]);
```

Output:

```toml
[event]
date = 2024-12-25
start_time = 09:00:00
created = 2024-01-15T10:30:00
```

### Round-Trip with Comment Preservation

Parse with trivia to preserve comments during re-encoding:

```php
$input = <<<'TOML'
# Server configuration
[server]
host = "localhost"  # Change for production
port = 8080
TOML;

// Parse with trivia preservation enabled
$document = Toml::parse($input, true);

// Modify a value in the AST (public properties can be changed directly)
foreach ($document->items as $item) {
    if ($item instanceof \PhpCollective\Toml\Ast\Table) {
        foreach ($item->items as $kv) {
            if ($kv->key->parts === ['port'] && $kv->value instanceof \PhpCollective\Toml\Ast\Value\IntegerValue) {
                $kv->value->value = 9000;
            }
        }
    }
}

// Re-encode preserves comments
$output = Toml::encodeDocument($document);
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

### CakePHP ConfigEngine

CakePHP's `Configure` class supports pluggable engines. Create a TOML engine to load `.toml` config files:

```php
// src/Core/Configure/Engine/TomlConfigEngine.php
namespace App\Core\Configure\Engine;

use Cake\Core\Configure\ConfigEngineInterface;
use Cake\Core\Exception\CakeException;
use PhpCollective\Toml\Toml;

class TomlConfigEngine implements ConfigEngineInterface
{
    protected string $path;

    public function __construct(string $path = CONFIG)
    {
        $this->path = $path;
    }

    public function read(string $key): array
    {
        $file = $this->path . $key . '.toml';

        if (!is_file($file)) {
            throw new CakeException("Could not load configuration file: {$file}");
        }

        return Toml::decodeFile($file);
    }

    public function dump(string $key, array $data): bool
    {
        $file = $this->path . $key . '.toml';

        return file_put_contents($file, Toml::encode($data)) !== false;
    }
}
```

Register it in `config/bootstrap.php`:

```php
use App\Core\Configure\Engine\TomlConfigEngine;
use Cake\Core\Configure;

Configure::config('toml', new TomlConfigEngine());
```

Now you can use TOML config files:

```php
// Load config/app_local.toml
Configure::load('app_local', 'toml');

// Access values
$debug = Configure::read('debug');
```

Example `config/app_local.toml`:

```toml
debug = true

[database]
host = "localhost"
username = "app_user"
database = "my_app"

[email]
transport = "smtp"
host = "smtp.example.com"
port = 587
```

### CakePHP Feature Flags

Use TOML for readable feature toggles:

```toml
# config/features.toml
[features]
new_dashboard = true
beta_api = false
dark_mode = true

[rollout]
# Percentage rollout (0.0 to 1.0)
new_checkout = 0.25
```

```php
// src/Utility/FeatureFlags.php
namespace App\Utility;

use Cake\Core\Configure;

class FeatureFlags
{
    public static function isEnabled(string $feature): bool
    {
        return (bool)Configure::read("features.{$feature}", false);
    }

    public static function isEnabledForUser(string $feature, int $userId): bool
    {
        $rollout = Configure::read("rollout.{$feature}");

        if ($rollout === null) {
            return static::isEnabled($feature);
        }

        // Deterministic rollout based on user ID
        return ($userId % 100) < ($rollout * 100);
    }
}
```

Usage in controllers or templates:

```php
if (FeatureFlags::isEnabled('new_dashboard')) {
    // Show new dashboard
}

if (FeatureFlags::isEnabledForUser('new_checkout', $user->id)) {
    // Show new checkout flow for this user
}
```
