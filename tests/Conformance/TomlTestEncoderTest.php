<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Conformance;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises bin/toml-encoder against the official toml-test corpus: the tagged
 * JSON of a valid case is encoded to TOML, decoded again, and the resulting
 * tagged JSON must match the original. This proves the encoder emits valid,
 * semantically equivalent TOML.
 *
 * Empty-table fixtures (`{}`) and NUL-byte-key fixtures are intentionally
 * excluded: PHP arrays cannot distinguish an empty table from an empty array,
 * and json_decode() cannot represent a NUL byte in an object key.
 */
final class TomlTestEncoderTest extends TestCase
{
    #[DataProvider('encoderFixtureProvider')]
    public function testEncoderRoundTripsTaggedJsonFixtures(string $fixtureBase): void
    {
        if (!is_dir('/tmp/toml-test/tests')) {
            $this->markTestSkipped('toml-test corpus is not available in this environment.');
        }

        $jsonPath = $fixtureBase . '.json';
        $expected = file_get_contents($jsonPath);
        $this->assertNotFalse($expected);

        $toml = $this->runScript(__DIR__ . '/../../bin/toml-encoder', $expected);
        $actual = $this->runScript(__DIR__ . '/../../bin/toml-decoder', $toml);

        // Floats are compared numerically (see TomlTestDecoderTest::normalizeFloatLeaves).
        $this->assertEquals(
            TomlTestDecoderTest::normalizeFloatLeaves($this->decodeJson($expected)),
            TomlTestDecoderTest::normalizeFloatLeaves($this->decodeJson($actual)),
            basename($fixtureBase),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function encoderFixtureProvider(): iterable
    {
        $fixtures = [
            'valid/string/escape-esc',
            'valid/string/escapes',
            'valid/string/hex-escape',
            'valid/string/unicode-escape',
            'valid/float/exponent',
            'valid/float/zero',
            'valid/float/long',
            'valid/datetime/datetime',
            'valid/datetime/milliseconds',
            'valid/datetime/timezone',
            'valid/array/array',
            'valid/key/alphanum',
        ];

        foreach ($fixtures as $fixture) {
            yield $fixture => ['/tmp/toml-test/tests/' . $fixture];
        }
    }

    private function runScript(string $script, string $input): string
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([PHP_BINARY, $script], $descriptorSpec, $pipes);
        $this->assertIsResource($process);

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, trim((string)$stderr));
        $this->assertNotFalse($stdout);

        return $stdout;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->fail($e->getMessage());
        }

        $this->assertIsArray($decoded);

        return $decoded;
    }
}
