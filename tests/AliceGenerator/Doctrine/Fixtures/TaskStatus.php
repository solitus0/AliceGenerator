<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine\Fixtures;

enum TaskStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
