<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

enum ExpressionLikeEnum: string
{
    case Quoted = "a\"b\\c\n@reference";
}
