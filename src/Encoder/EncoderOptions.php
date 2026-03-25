<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Encoder;

final readonly class EncoderOptions
{
    public function __construct(
        public bool $sortKeys = false,
        public string $newline = "\n",
        public DocumentFormattingMode $documentFormatting = DocumentFormattingMode::Normalized,
        public bool $skipNulls = false,
    ) {
    }
}
