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
        public TomlVersion $version = TomlVersion::V11,
        public bool $integerGrouping = false,
        public bool $trailingComma = false,
        public bool $dottedKeys = false,
    ) {
    }
}
