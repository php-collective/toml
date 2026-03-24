<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Integration;

use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;

final class RoundTripTest extends TestCase
{
    public function testRoundTripSimple(): void
    {
        $original = [
            'title' => 'TOML Example',
            'count' => 42,
            'enabled' => true,
            'pi' => 3.14,
        ];

        $toml = Toml::encode($original);
        $decoded = Toml::decode($toml);

        $this->assertEquals($original, $decoded);
    }

    public function testRoundTripWithArrays(): void
    {
        $original = [
            'numbers' => [1, 2, 3],
            'strings' => ['a', 'b', 'c'],
            'mixed' => [1, 'two', 3.0],
        ];

        $toml = Toml::encode($original);
        $decoded = Toml::decode($toml);

        $this->assertEquals($original, $decoded);
    }

    public function testRoundTripWithTables(): void
    {
        // Note: scalars must come first to match TOML output order
        $original = [
            'title' => 'Project',
            'database' => [
                'host' => 'localhost',
                'port' => 5432,
            ],
            'server' => [
                'enabled' => true,
                'config' => [
                    'timeout' => 30,
                ],
            ],
        ];

        $toml = Toml::encode($original);
        $decoded = Toml::decode($toml);

        $this->assertEquals($original, $decoded);
    }

    public function testRoundTripWithArrayOfTables(): void
    {
        $original = [
            'products' => [
                ['name' => 'Hammer', 'price' => 9.99],
                ['name' => 'Nail', 'price' => 0.05],
            ],
        ];

        $toml = Toml::encode($original);
        $decoded = Toml::decode($toml);

        $this->assertEquals($original, $decoded);
    }

    public function testRoundTripWithEscapedStrings(): void
    {
        $original = [
            'path' => "line1\nline2",
            'tab' => "col1\tcol2",
            'quote' => 'say "hello"',
        ];

        $toml = Toml::encode($original);
        $decoded = Toml::decode($toml);

        $this->assertEquals($original, $decoded);
    }

    public function testRoundTripComplex(): void
    {
        // Complex document with various TOML features
        // Note: scalars must come before tables to match TOML output order
        $original = [
            'title' => 'TOML Example',
            'ports' => [8000, 8001, 8002],
            'owner' => [
                'name' => 'Tom Preston-Werner',
            ],
            'database' => [
                'enabled' => true,
            ],
            'servers' => [
                ['name' => 'alpha', 'ip' => '10.0.0.1'],
                ['name' => 'beta', 'ip' => '10.0.0.2'],
            ],
        ];

        $toml = Toml::encode($original);
        $decoded = Toml::decode($toml);

        $this->assertEquals($original, $decoded);
    }

    public function testRoundTripWithSpecialFloats(): void
    {
        $original = [
            'positive_inf' => INF,
            'negative_inf' => -INF,
        ];

        $toml = Toml::encode($original);
        $decoded = Toml::decode($toml);

        $this->assertTrue(is_infinite($decoded['positive_inf']) && $decoded['positive_inf'] > 0);
        $this->assertTrue(is_infinite($decoded['negative_inf']) && $decoded['negative_inf'] < 0);
    }

    public function testRoundTripWithQuotedKeys(): void
    {
        $original = [
            'simple' => 1,
            'key with spaces' => 2,
            'key.with.dots' => 3,
        ];

        $toml = Toml::encode($original);
        $decoded = Toml::decode($toml);

        $this->assertEquals($original, $decoded);
    }
}
