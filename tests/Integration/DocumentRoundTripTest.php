<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Integration;

use PhpCollective\Toml\Toml;
use PHPUnit\Framework\TestCase;

final class DocumentRoundTripTest extends TestCase
{
    public function testEncodeDocumentPreservesCommentsAndBlankLines(): void
    {
        $input = <<<'TOML'
# Header comment
title = "Example" # trailing comment

[server] # table comment
# host comment
host = "localhost"
TOML;

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc);

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesQuotedKeyStyle(): void
    {
        $input = <<<'TOML'
"key.with.dots" = 1
'spaced key' = 2
TOML;

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc);

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesStringStyles(): void
    {
        $input = <<<'TOML'
basic = "hello\nworld"
literal = 'literal text'
multiline = """
line1
line2"""
TOML;

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc);

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesDocumentOrder(): void
    {
        $input = <<<'TOML'
title = "Example"

[b]
value = 2

[a]
value = 1
TOML;

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc);

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesArrayCollectionFormatting(): void
    {
        $input = <<<'TOML'
values = [
  1,
  # between
  2,
]
TOML;

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc);

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesInlineTableSpacing(): void
    {
        $input = <<<'TOML'
point = { x = 1,  y = { nested = true } }
TOML;

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc);

        $this->assertSame($input, $encoded);
    }
}
