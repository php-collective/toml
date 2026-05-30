<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Encoder;

use PhpCollective\Toml\Encoder\EncoderOptions;
use PhpCollective\Toml\Toml;
use PhpCollective\Toml\TomlVersion;
use PHPUnit\Framework\TestCase;

final class EncoderThresholdsTest extends TestCase
{
    public function testMultilineThresholdNullKeepsLongStringSingleLine(): void
    {
        $toml = Toml::encode(
            ['message' => 'this string is longer than ten characters'],
            new EncoderOptions(multilineThreshold: null),
        );

        self::assertSame('message = "this string is longer than ten characters"', $toml);
    }

    public function testMultilineThresholdUsesMultilineStringOnlyAboveThreshold(): void
    {
        $toml = Toml::encode(
            [
                'long' => 'this string is longer than ten characters',
                'short' => 'short',
            ],
            new EncoderOptions(multilineThreshold: 10),
        );

        self::assertSame(
            "long = \"\"\"\nthis string is longer than ten characters\"\"\"\nshort = \"short\"",
            $toml,
        );
    }

    public function testInlineTableThresholdNullKeepsNestedTable(): void
    {
        $toml = Toml::encode(
            [
                'server' => [
                    'host' => 'localhost',
                    'port' => 8080,
                ],
            ],
            new EncoderOptions(inlineTableThreshold: null),
        );

        self::assertSame("\n[server]\nhost = \"localhost\"\nport = 8080", $toml);
    }

    public function testInlineTableThresholdRendersSmallFlatTableInline(): void
    {
        $toml = Toml::encode(
            [
                'point' => [
                    'a' => 1,
                    'b' => 2,
                ],
            ],
            new EncoderOptions(inlineTableThreshold: 3),
        );

        self::assertSame('point = { a = 1, b = 2 }', $toml);
    }

    public function testInlineTableThresholdKeepsLargeTableAsHeader(): void
    {
        $toml = Toml::encode(
            [
                'point' => [
                    'a' => 1,
                    'b' => 2,
                    'c' => 3,
                    'd' => 4,
                ],
            ],
            new EncoderOptions(inlineTableThreshold: 3),
        );

        self::assertSame("\n[point]\na = 1\nb = 2\nc = 3\nd = 4", $toml);
    }

    public function testInlineTableThresholdKeepsTableContainingNestedTableAsHeader(): void
    {
        $toml = Toml::encode(
            [
                'server' => [
                    'host' => 'localhost',
                    'tls' => [
                        'enabled' => true,
                    ],
                ],
            ],
            new EncoderOptions(inlineTableThreshold: 3),
        );

        self::assertSame("\n[server]\nhost = \"localhost\"\ntls = { enabled = true }", $toml);
    }

    public function testInlineTableThresholdHonorsSortKeysAndTrailingComma(): void
    {
        $toml = Toml::encode(
            [
                'point' => [
                    'b' => 2,
                    'a' => 1,
                ],
            ],
            new EncoderOptions(
                sortKeys: true,
                trailingComma: true,
                inlineTableThreshold: 3,
            ),
        );

        self::assertSame('point = { a = 1, b = 2, }', $toml);
    }

    public function testMultilineThresholdNeverPromotesKeys(): void
    {
        // The threshold applies to string values only; quoted keys must stay
        // single-line, since a multiline key would produce invalid TOML.
        $toml = Toml::encode(
            ['this is a very long quoted key indeed' => 'v'],
            new EncoderOptions(multilineThreshold: 5),
        );

        self::assertStringNotContainsString('"""', $toml);
        // Round-trips back to the same key/value.
        self::assertSame(['this is a very long quoted key indeed' => 'v'], Toml::decode($toml));
    }

    public function testInlineTableOmitsTrailingCommaForToml10(): void
    {
        // Trailing commas in inline tables are invalid in TOML 1.0.
        $toml = Toml::encode(
            ['point' => ['a' => 1, 'b' => 2]],
            new EncoderOptions(
                version: TomlVersion::V10,
                trailingComma: true,
                inlineTableThreshold: 3,
            ),
        );

        self::assertSame('point = { a = 1, b = 2 }', $toml);
        self::assertSame(['point' => ['a' => 1, 'b' => 2]], Toml::decode($toml, TomlVersion::V10));
    }
}
