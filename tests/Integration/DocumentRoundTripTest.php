<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Test\Integration;

use PhpCollective\Toml\Encoder\DocumentFormattingMode;
use PhpCollective\Toml\Encoder\EncoderOptions;
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
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesQuotedKeyStyle(): void
    {
        $input = <<<'TOML'
"key.with.dots" = 1
'spaced key' = 2
TOML;

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

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
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

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
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

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
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesInlineTableSpacing(): void
    {
        $input = <<<'TOML'
point = { x = 1,  y = { nested = true } }
TOML;

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentLosslesslyPreservesExactParsedLexemes(): void
    {
        $input = <<<'TOML'
 title   =   'value'
ratio = 1_2.3_4
values = [ 1 ,2 , 3 ]

[ server . "quoted.key" ] # keep header spacing
point = { x = 1,  y = 2 }
TOML;

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesTabWhitespace(): void
    {
        $input = "\tkey\t=\t\"value\"\n[\tserver\t]\n\tport\t=\t80";

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesTabIndentedMultilineArray(): void
    {
        $input = "values = [\n\t1,\n\t2,\n]";

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesTabIndentedMultilineInlineTable(): void
    {
        $input = "point = {\n\tx = 1,\n\ty = 2,\n}";

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame($input, $encoded);
    }

    public function testEncodeDocumentPreservesMixedTabSpaceWhitespace(): void
    {
        $input = " \tkey \t = \t \"value\"";

        $doc = Toml::parse($input, true);
        $encoded = Toml::encodeDocument($doc, new EncoderOptions(documentFormatting: DocumentFormattingMode::SourceAware));

        $this->assertSame($input, $encoded);
    }
}
