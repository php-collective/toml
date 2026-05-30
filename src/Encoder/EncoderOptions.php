<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Encoder;

use PhpCollective\Toml\TomlVersion;

final class EncoderOptions
{
    public function __construct(
        public readonly bool $sortKeys = false,
        public readonly string $newline = "\n",
        public readonly DocumentFormattingMode $documentFormatting = DocumentFormattingMode::Normalized,
        public readonly bool $skipNulls = false,
        public readonly TomlVersion $version = TomlVersion::V10,
        public readonly bool $integerGrouping = false,
        public readonly bool $trailingComma = false,
        public readonly bool $dottedKeys = false,
        public readonly ArrayStyle $arrayStyle = ArrayStyle::Inline,
        public readonly int $arrayAutoThreshold = 3,
        public readonly string $indent = '    ',
        public readonly StringStyle $stringStyle = StringStyle::Basic,
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
