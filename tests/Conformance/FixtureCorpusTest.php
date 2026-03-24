<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Conformance;

use PhpCollective\Toml\Toml;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FixtureCorpusTest extends TestCase
{
    #[DataProvider('validFixtureProvider')]
    public function testValidFixturesParseSuccessfully(string $path): void
    {
        $input = $this->readFixture($path);
        $result = Toml::tryParse($input);

        $this->assertTrue($result->isValid(), basename($path));
        $this->assertNotNull($result->getValue(), basename($path));
    }

    #[DataProvider('invalidFixtureProvider')]
    public function testInvalidFixturesAreRejected(string $path): void
    {
        $input = $this->readFixture($path);
        $result = Toml::tryParse($input);

        $this->assertFalse($result->isValid(), basename($path));
        $this->assertNotEmpty($result->getErrors(), basename($path));
    }

    #[DataProvider('semanticFixtureProvider')]
    public function testSemanticFixturesAreRejected(string $path): void
    {
        $input = $this->readFixture($path);
        $result = Toml::tryParse($input);

        $this->assertFalse($result->isValid(), basename($path));
        $this->assertNotEmpty($result->getErrors(), basename($path));
    }

    #[DataProvider('roundTripFixtureProvider')]
    public function testRoundTripFixturesReencodeIdentically(string $path): void
    {
        $input = $this->readFixture($path);
        $document = Toml::parse($input, true);

        $this->assertSame($input, Toml::encodeDocument($document), basename($path));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validFixtureProvider(): iterable
    {
        yield from self::fixtureProvider('valid');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidFixtureProvider(): iterable
    {
        yield from self::fixtureProvider('invalid');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function semanticFixtureProvider(): iterable
    {
        yield from self::fixtureProvider('semantic');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function roundTripFixtureProvider(): iterable
    {
        yield from self::fixtureProvider('roundtrip');
    }

    /**
     * @return iterable<string, array{string}>
     */
    private static function fixtureProvider(string $category): iterable
    {
        $paths = glob(__DIR__ . '/../Fixtures/Conformance/' . $category . '/*.toml');
        if ($paths === false) {
            return;
        }

        sort($paths);

        foreach ($paths as $path) {
            yield basename($path) => [$path];
        }
    }

    private function readFixture(string $path): string
    {
        $contents = file_get_contents($path);
        $this->assertNotFalse($contents);

        return $contents;
    }
}
