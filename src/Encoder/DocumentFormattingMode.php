<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Encoder;

enum DocumentFormattingMode: string
{
    case Normalized = 'normalized';
    case SourceAware = 'source-aware';
}
