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

        $this->assertEquals(
            $this->decodeJsonFile($jsonPath),
            $this->decodeJson($output),
            basename($tomlPath),
        );
    }

    public function testNumericLikeBareKeyWithLeadingZeroParsesAsKey(): void
    {
        $output = $this->runDecoder("-01 = true\n");

        $this->assertEquals(
            $this->decodeJson('{"-01":{"type":"bool","value":"true"}}'),
            $this->decodeJson($output),
        );
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
        $command = [PHP_BINARY, __DIR__ . '/../../bin/toml-decoder'];
        $descriptorSpec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes);
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
