<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test;

use PhpCollective\Toml\Normalizer;
use PhpCollective\Toml\Parser\Parser;
use PHPUnit\Framework\TestCase;

final class NormalizerTest extends TestCase
{
    private Parser $parser;

    private Normalizer $normalizer;

    protected function setUp(): void
    {
        $this->parser = new Parser();
        $this->normalizer = new Normalizer();
    }

    public function testNormalizeSimpleKeyValue(): void
    {
        $doc = $this->parser->parse('key = "value"');
        $result = $this->normalizer->normalize($doc);

        $this->assertSame(['key' => 'value'], $result);
    }

    public function testNormalizeMultipleKeyValues(): void
    {
        $doc = $this->parser->parse("name = \"test\"\ncount = 42");
        $result = $this->normalizer->normalize($doc);

        $this->assertSame(['name' => 'test', 'count' => 42], $result);
    }

    public function testNormalizeDottedKey(): void
    {
        $doc = $this->parser->parse('a.b.c = 1');
        $result = $this->normalizer->normalize($doc);

        $this->assertSame(['a' => ['b' => ['c' => 1]]], $result);
    }

    public function testNormalizeTable(): void
    {
        $doc = $this->parser->parse("[server]\nhost = \"localhost\"\nport = 8080");
        $result = $this->normalizer->normalize($doc);

        $this->assertSame([
            'server' => [
                'host' => 'localhost',
                'port' => 8080,
            ],
        ], $result);
    }

    public function testNormalizeDottedTable(): void
    {
        $doc = $this->parser->parse("[server.database]\nname = \"mydb\"");
        $result = $this->normalizer->normalize($doc);

        $this->assertSame([
            'server' => [
                'database' => [
                    'name' => 'mydb',
                ],
            ],
        ], $result);
    }

    public function testNormalizeArrayTable(): void
    {
        $toml = <<<'TOML'
[[products]]
name = "Hammer"
price = 9.99

[[products]]
name = "Nail"
price = 0.05
TOML;

        $doc = $this->parser->parse($toml);
        $result = $this->normalizer->normalize($doc);

        $this->assertSame([
            'products' => [
                ['name' => 'Hammer', 'price' => 9.99],
                ['name' => 'Nail', 'price' => 0.05],
            ],
        ], $result);
    }

    public function testNormalizeNestedArrayTable(): void
    {
        $toml = <<<'TOML'
[[fruits]]
name = "apple"

[[fruits.varieties]]
name = "red delicious"

[[fruits.varieties]]
name = "granny smith"

[[fruits]]
name = "banana"

[[fruits.varieties]]
name = "plantain"
TOML;

        $doc = $this->parser->parse($toml);
        $result = $this->normalizer->normalize($doc);

        $this->assertSame([
            'fruits' => [
                [
                    'name' => 'apple',
                    'varieties' => [
                        ['name' => 'red delicious'],
                        ['name' => 'granny smith'],
                    ],
                ],
                [
                    'name' => 'banana',
                    'varieties' => [
                        ['name' => 'plantain'],
                    ],
                ],
            ],
        ], $result);
    }

    public function testNormalizeInlineTable(): void
    {
        $doc = $this->parser->parse('point = { x = 1, y = 2 }');
        $result = $this->normalizer->normalize($doc);

        $this->assertSame(['point' => ['x' => 1, 'y' => 2]], $result);
    }

    public function testNormalizeArray(): void
    {
        $doc = $this->parser->parse('numbers = [1, 2, 3]');
        $result = $this->normalizer->normalize($doc);

        $this->assertSame(['numbers' => [1, 2, 3]], $result);
    }

    public function testNormalizeMixedDocument(): void
    {
        $toml = <<<'TOML'
title = "TOML Example"

[owner]
name = "Tom Preston-Werner"

[database]
enabled = true
ports = [8000, 8001, 8002]

[[servers]]
name = "alpha"
ip = "10.0.0.1"

[[servers]]
name = "beta"
ip = "10.0.0.2"
TOML;

        $doc = $this->parser->parse($toml);
        $result = $this->normalizer->normalize($doc);

        $this->assertSame([
            'title' => 'TOML Example',
            'owner' => [
                'name' => 'Tom Preston-Werner',
            ],
            'database' => [
                'enabled' => true,
                'ports' => [8000, 8001, 8002],
            ],
            'servers' => [
                ['name' => 'alpha', 'ip' => '10.0.0.1'],
                ['name' => 'beta', 'ip' => '10.0.0.2'],
            ],
        ], $result);
    }
}
