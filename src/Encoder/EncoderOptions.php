<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Encoder;

use PhpCollective\Toml\TomlVersion;

final readonly class EncoderOptions
{
    public function __construct(
        public bool $sortKeys = false,
        public string $newline = "\n",
        public DocumentFormattingMode $documentFormatting = DocumentFormattingMode::Normalized,
        public bool $skipNulls = false,
        public TomlVersion $version = TomlVersion::V10,
        public bool $integerGrouping = false,
        public bool $trailingComma = false,
        public bool $dottedKeys = false,
        public ArrayStyle $arrayStyle = ArrayStyle::Inline,
        public int $arrayAutoThreshold = 3,
        public string $indent = '    ',
    ) {
    }

    /**
     * Returns options optimized for minimal diffs in version control.
     *
     * - Trailing commas in arrays (adding items doesn't modify previous line)
     * - Auto multiline arrays (larger arrays use one item per line)
     */
    public static function diffFriendly(): self
    {
        return new self(
            trailingComma: true,
            arrayStyle: ArrayStyle::Auto,
        );
    }
}
