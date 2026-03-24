<?php

declare(strict_types=1);

namespace PhpCollective\Toml\Ast\Value;

use PhpCollective\Toml\Ast\Node;

interface Value extends Node
{
    public function getValue(): mixed;
}
