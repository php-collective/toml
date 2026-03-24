<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Encoder;

final readonly class EncoderOptions
{
    public function __construct(
        public bool $preserveTrivia = false,
        public int $inlineTableMaxKeys = 3,
        public string $newline = "\n",
        public bool $sortKeys = false,
    ) {
    }
}
