<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

class PropertyDiscoveryParentFixture
{
    private string $inheritedPrivate = 'private default';

    private string $shared = 'parent default';

    public string $inheritedPublic = 'public default';

    public function setInheritedPrivate(string $value): void
    {
        $this->inheritedPrivate = $value;
    }

    public function getInheritedPrivate(): string
    {
        return $this->inheritedPrivate;
    }

    public function setParentShared(string $value): void
    {
        $this->shared = $value;
    }

    public function getParentShared(): string
    {
        return $this->shared;
    }
}
