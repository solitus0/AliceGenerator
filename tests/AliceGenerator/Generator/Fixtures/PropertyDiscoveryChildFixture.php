<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class PropertyDiscoveryChildFixture extends PropertyDiscoveryParentFixture
{
    private string $shared = 'child default';

    public string $childOnly = 'child only default';

    public function setShared(string $value): void
    {
        $this->shared = $value;
    }

    public function getShared(): string
    {
        return $this->shared;
    }
}
