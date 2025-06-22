<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Metadata;

use Metadata\PropertyMetadata as BasePropertyMetadata;

class PropertyMetadata extends BasePropertyMetadata
{
    public $staticData;

    public $ignore = false;
}
