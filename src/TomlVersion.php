<?php

declare(strict_types=1);

namespace PhpCollective\Toml;

enum TomlVersion: string
{
    case V10 = '1.0';
    case V11 = '1.1';
}
