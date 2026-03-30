<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Encoder;

enum ArrayStyle: string
{
    /**
     * Arrays are encoded inline on a single line.
     * Example: ports = [8080, 8081, 8082]
     */
    case Inline = 'inline';

    /**
     * Arrays are always encoded with one item per line.
     * Example:
     * ports = [
     *     8080,
     *     8081,
     *     8082,
     * ]
     */
    case Multiline = 'multiline';

    /**
     * Arrays with more than the threshold number of items are multiline.
     * Short arrays remain inline for readability.
     */
    case Auto = 'auto';
}
