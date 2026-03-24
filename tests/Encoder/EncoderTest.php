<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Encoder;

use DateTimeImmutable;
use PhpCollective\Toml\Encoder\Encoder;
use PhpCollective\Toml\Encoder\EncoderOptions;
use PhpCollective\Toml\Exception\EncodeException;
use PHPUnit\Framework\TestCase;

final class EncoderTest extends TestCase
{
    public function testEncodeScalars(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'string' => 'hello',
            'integer' => 42,
            'float' => 3.14,
            'bool' => true,
        ]);

        $this->assertStringContainsString('string = "hello"', $result);
        $this->assertStringContainsString('integer = 42', $result);
        $this->assertStringContainsString('float = 3.14', $result);
        $this->assertStringContainsString('bool = true', $result);
    }

    public function testEncodeArray(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'items' => [1, 2, 3],
        ]);

        $this->assertStringContainsString('items = [1, 2, 3]', $result);
    }

    public function testEncodeNestedTable(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'server' => [
                'host' => 'localhost',
                'port' => 8080,
            ],
        ]);

        $this->assertStringContainsString('[server]', $result);
        $this->assertStringContainsString('host = "localhost"', $result);
    }

    public function testEncodeArrayOfTables(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'products' => [
                ['name' => 'Hammer'],
                ['name' => 'Nail'],
            ],
        ]);

        $this->assertStringContainsString('[[products]]', $result);
    }

    public function testEncodeBooleans(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'yes' => true,
            'no' => false,
        ]);

        $this->assertStringContainsString('yes = true', $result);
        $this->assertStringContainsString('no = false', $result);
    }

    public function testEncodeSpecialFloats(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'positive_inf' => INF,
            'negative_inf' => -INF,
            'not_a_number' => NAN,
        ]);

        $this->assertStringContainsString('positive_inf = inf', $result);
        $this->assertStringContainsString('negative_inf = -inf', $result);
        $this->assertStringContainsString('not_a_number = nan', $result);
    }

    public function testEncodeStringWithEscapes(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'path' => "line1\nline2",
            'quote' => 'say "hello"',
        ]);

        $this->assertStringContainsString('path = "line1\\nline2"', $result);
        $this->assertStringContainsString('quote = "say \\"hello\\""', $result);
    }

    public function testEncodeDateTime(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $date = new DateTimeImmutable('2024-03-15T10:30:00+00:00');

        $result = $encoder->encode([
            'created' => $date,
        ]);

        $this->assertStringContainsString('created = 2024-03-15T10:30:00', $result);
    }

    public function testEncodeQuotedKey(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'simple' => 1,
            'key with spaces' => 2,
        ]);

        $this->assertStringContainsString('simple = 1', $result);
        $this->assertStringContainsString('"key with spaces" = 2', $result);
    }

    public function testEncodeSortedKeys(): void
    {
        $encoder = new Encoder(new EncoderOptions(sortKeys: true));

        $result = $encoder->encode([
            'zebra' => 1,
            'apple' => 2,
            'mango' => 3,
        ]);

        // With sorted keys, apple should come before mango which comes before zebra
        $applePos = strpos($result, 'apple');
        $mangoPos = strpos($result, 'mango');
        $zebraPos = strpos($result, 'zebra');

        $this->assertLessThan($mangoPos, $applePos);
        $this->assertLessThan($zebraPos, $mangoPos);
    }

    public function testEncodeNullThrowsException(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $this->expectException(EncodeException::class);
        $this->expectExceptionMessage('TOML does not support null values');

        $encoder->encode(['value' => null]);
    }

    public function testEncodeNestedArrayOfTables(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'servers' => [
                [
                    'name' => 'alpha',
                    'ports' => [8000, 8001],
                ],
                [
                    'name' => 'beta',
                    'ports' => [9000],
                ],
            ],
        ]);

        $this->assertStringContainsString('[[servers]]', $result);
        $this->assertStringContainsString('name = "alpha"', $result);
        $this->assertStringContainsString('ports = [8000, 8001]', $result);
        $this->assertStringContainsString('name = "beta"', $result);
    }

    public function testEncodeEmptyArray(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'empty' => [],
        ]);

        $this->assertStringContainsString('empty = []', $result);
    }

    public function testEncodeMixedArray(): void
    {
        $encoder = new Encoder(new EncoderOptions());

        $result = $encoder->encode([
            'mixed' => [1, 'two', 3.0, true],
        ]);

        $this->assertStringContainsString('mixed = [1, "two", 3.0, true]', $result);
    }
}
