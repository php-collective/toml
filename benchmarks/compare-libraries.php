<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$tempDir = sys_get_temp_dir() . '/php-toml-bench-' . bin2hex(random_bytes(4));

$baselineToml = <<<'TOML'
title = "TOML Example"

[database]
server = "192.168.1.1"
ports = [8000, 8001, 8002]
enabled = true

[servers.alpha]
ip = "10.0.0.1"
dc = "eqdc10"

[[products]]
name = "Hammer"
sku = 738594937

[[products]]
name = "Nail"
sku = 284758393
TOML;

$modernToml = <<<'TOML'
quoted = "hello\nworld"
literal = 'abc'
when = 1979-05-27T07:32:00Z
local_date = 1979-05-27
local_time = 07:32:00
point = { x = 1, y = 2 }
items = [1, 2, 3]

[a.b]
c = "d"
TOML;

$baselineData = [
    'title' => 'TOML Example',
    'database' => [
        'server' => '192.168.1.1',
        'ports' => [8000, 8001, 8002],
        'enabled' => true,
    ],
    'servers' => [
        'alpha' => [
            'ip' => '10.0.0.1',
            'dc' => 'eqdc10',
        ],
    ],
    'products' => [
        ['name' => 'Hammer', 'sku' => 738594937],
        ['name' => 'Nail', 'sku' => 284758393],
    ],
];

$composerJson = [
    'name' => 'local/toml-bench',
    'require' => [
        'php' => '>=8.4',
        'php-collective/toml' => 'dev-master',
        'petalbranch/toml' => '^1.2',
        'devium/toml' => '^1.0',
        'internal/toml' => '^1.0',
        'yosymfony/toml' => '^1.0',
    ],
    'repositories' => [[
        'type' => 'path',
        'url' => $root,
        'options' => ['symlink' => false],
    ]],
    'minimum-stability' => 'dev',
    'prefer-stable' => true,
];

mkdir($tempDir, 0777, true);
file_put_contents($tempDir . '/composer.json', json_encode($composerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

try {
    runCommand(['composer', 'install', '--no-interaction', '--no-progress', '--working-dir=' . $tempDir], $root);

    $benchPayload = <<<'PHP'
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Devium\Toml\Toml as DeviumToml;
use Internal\Toml\Toml as InternalToml;
use Petalbranch\Toml\Toml as PetalToml;
use PhpCollective\Toml\Toml as CollectiveToml;
use Yosymfony\Toml\Toml as YosymfonyToml;

$baselineToml = %s;
$modernToml = %s;
$baselineData = %s;

$cases = [
    'decode-baseline' => [
        'iterations' => 3000,
        'libs' => [
            'php-collective' => static fn () => CollectiveToml::decode($baselineToml),
            'petalbranch' => static fn () => PetalToml::parse($baselineToml),
            'devium' => static fn () => DeviumToml::decode($baselineToml, true),
            'internal' => static fn () => InternalToml::parseToArray($baselineToml),
            'yosymfony' => static fn () => YosymfonyToml::parse($baselineToml),
        ],
    ],
    'decode-modern' => [
        'iterations' => 2500,
        'libs' => [
            'php-collective' => static fn () => CollectiveToml::decode($modernToml),
            'petalbranch' => static fn () => PetalToml::parse($modernToml),
            'devium' => static fn () => DeviumToml::decode($modernToml, true),
            'internal' => static fn () => InternalToml::parseToArray($modernToml),
        ],
    ],
    'encode-baseline' => [
        'iterations' => 2500,
        'libs' => [
            'php-collective' => static fn () => CollectiveToml::encode($baselineData),
            'petalbranch' => static fn () => PetalToml::dump($baselineData),
            'devium' => static fn () => DeviumToml::encode($baselineData),
            'internal' => static fn () => (string) InternalToml::encode($baselineData),
        ],
    ],
];

function runBench(callable $fn, int $iterations, int $rounds = 5): array
{
    $times = [];

    for ($round = 0; $round < $rounds; $round++) {
        gc_collect_cycles();
        $start = hrtime(true);

        for ($index = 0; $index < $iterations; $index++) {
            $fn();
        }

        $times[] = (hrtime(true) - $start) / 1_000_000;
    }

    sort($times);
    $median = $times[intdiv(count($times), 2)];

    return [
        'median_ms' => $median,
        'avg_ms' => array_sum($times) / count($times),
        'ops_per_sec' => $iterations / ($median / 1000),
        'rounds' => $times,
    ];
}

$results = [];

foreach ($cases as $caseName => $case) {
    foreach ($case['libs'] as $libName => $fn) {
        try {
            $fn();
        } catch (Throwable $exception) {
            $results[$caseName][$libName] = [
                'error' => get_class($exception) . ': ' . $exception->getMessage(),
            ];
            continue;
        }

        $results[$caseName][$libName] = runBench($fn, $case['iterations']);
    }
}

echo json_encode([
    'php' => PHP_VERSION,
    'cases' => $results,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
PHP;

    file_put_contents(
        $tempDir . '/run-bench.php',
        sprintf(
            $benchPayload,
            var_export($baselineToml, true),
            var_export($modernToml, true),
            var_export($baselineData, true),
        ),
    );

    $result = runCommand(['php', $tempDir . '/run-bench.php'], $root);
    $decoded = json_decode($result, true, 512, JSON_THROW_ON_ERROR);

    echo markdownReport($decoded);
} finally {
    deleteDirectory($tempDir);
}

/**
 * @param array<int, string> $command
 */
function runCommand(array $command, string $cwd): string
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd);
    if (!is_resource($process)) {
        throw new RuntimeException('Failed to start process: ' . implode(' ', $command));
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);
    if ($exitCode !== 0) {
        throw new RuntimeException(trim((string) $stderr));
    }

    return (string) $stdout;
}

function deleteDirectory(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}

/**
 * @param array<string, mixed> $decoded
 */
function markdownReport(array $decoded): string
{
    $lines = [];
    $lines[] = '# Benchmark Report';
    $lines[] = '';
    $lines[] = '- PHP: `' . $decoded['php'] . '`';
    $lines[] = '- Libraries: `php-collective`, `petalbranch`, `devium`, `internal`, `yosymfony`';
    $lines[] = '- Metrics: median wall-clock throughput across 5 rounds';
    $lines[] = '';

    foreach ($decoded['cases'] as $caseName => $caseResults) {
        $lines[] = '## ' . $caseName;
        $lines[] = '';
        $lines[] = '| Library | Ops/s | Median ms | Notes |';
        $lines[] = '|---------|------:|----------:|-------|';

        uasort($caseResults, static function (array $left, array $right): int {
            return ($right['ops_per_sec'] ?? -1) <=> ($left['ops_per_sec'] ?? -1);
        });

        foreach ($caseResults as $library => $result) {
            if (isset($result['error'])) {
                $lines[] = '| ' . $library . ' | n/a | n/a | ' . str_replace('|', '\\|', $result['error']) . ' |';
                continue;
            }

            $lines[] = sprintf(
                '| %s | %.0f | %.2f | |',
                $library,
                $result['ops_per_sec'],
                $result['median_ms'],
            );
        }

        $lines[] = '';
    }

    return implode("\n", $lines) . "\n";
}
