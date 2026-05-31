<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Conformance;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TomlTestDecoderTest extends TestCase
{
    #[DataProvider('tomlTestFixtureProvider')]
    public function testTomlDecoderMatchesTaggedJsonFixtures(string $fixtureBase): void
    {
        if (!is_dir('/tmp/toml-test/tests')) {
            $this->markTestSkipped('toml-test corpus is not available in this environment.');
        }

        $tomlPath = $fixtureBase . '.toml';
        $jsonPath = $fixtureBase . '.json';

        $output = $this->runDecoder(file_get_contents($tomlPath) ?: '');

        // toml-test stores float values in varied textual forms (`300`, `1000.0`,
        // `3.0e14`); the reference runner compares them numerically, so we do too.
        $this->assertEquals(
            self::normalizeFloatLeaves($this->decodeJsonFile($jsonPath)),
            self::normalizeFloatLeaves($this->decodeJson($output)),
            basename($tomlPath),
        );
    }

    /**
     * Recursively replaces every `{"type": "float", "value": "..."}` leaf's textual
     * value with a numeric one so comparisons are value-based rather than string-based.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    public static function normalizeFloatLeaves(array $data): array
    {
        if (
            ($data['type'] ?? null) === 'float'
            && array_key_exists('value', $data)
            && is_string($data['value'])
        ) {
            $data['value'] = self::canonicalFloat($data['value']);

            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = self::normalizeFloatLeaves($value);
            }
        }

        return $data;
    }

    private static function canonicalFloat(string $value): string|float
    {
        return match (strtolower($value)) {
            'nan', '+nan', '-nan' => 'nan',
            'inf', '+inf' => INF,
            '-inf' => -INF,
            default => (float)$value,
        };
    }

    public function testNumericLikeBareKeyWithLeadingZeroParsesAsKey(): void
    {
        $output = $this->runDecoder("-01 = true\n");

        $this->assertEquals(
            $this->decodeJson('{"-01":{"type":"bool","value":"true"}}'),
            $this->decodeJson($output),
        );
    }

    public function testTomlDecoderSupportsStrictToml10Mode(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runDecoderWithStatus("time = 07:32\n", [
            'TOML_VERSION' => '1.0',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $stdout);
        $this->assertStringContainsString('Invalid token', trim((string)$stderr));
    }

    public function testTomlDecoderRejectsToml11InlineTableSyntaxInStrictToml10Mode(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runDecoderWithStatus('point = { x = 1, }', [
            'TOML_VERSION' => '1.0',
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertSame('', $stdout);
        $this->assertStringContainsString('Inline table trailing commas require TOML 1.1', trim((string)$stderr));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function tomlTestFixtureProvider(): iterable
    {
        $fixtures = [
            '/tmp/toml-test/tests/valid/float/exponent',
            '/tmp/toml-test/tests/valid/float/zero',
            '/tmp/toml-test/tests/valid/datetime/milliseconds',
            '/tmp/toml-test/tests/valid/key/alphanum',
            '/tmp/toml-test/tests/valid/key/quoted-unicode',
        ];

        foreach ($fixtures as $fixture) {
            yield basename($fixture) => [$fixture];
        }
    }

    private function runDecoder(string $input): string
    {
        [$exitCode, $stdout, $stderr] = $this->runDecoderWithStatus($input);

        $this->assertSame(0, $exitCode, trim($stderr));
        $this->assertNotFalse($stdout);

        return $stdout;
    }

    /**
     * @param string $input
     * @param array<string, string> $env
     *
     * @return array{int, string, string}
     */
    private function runDecoderWithStatus(string $input, array $env = []): array
    {
        $command = [PHP_BINARY, __DIR__ . '/../../bin/toml-decoder'];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, null, $env);
        $this->assertIsResource($process);

        fwrite($pipes[0], $input);
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [$exitCode, (string)$stdout, (string)$stderr];
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

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonFile(string $path): array
    {
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $this->decodeJson($contents);
    }
}
