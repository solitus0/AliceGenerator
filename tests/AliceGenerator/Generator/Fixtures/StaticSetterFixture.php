<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

final class StaticSetterFixture
{
    public string $name = '';

    private string $status = 'initial';

    private string $internal_status = 'initial';

    public function changeStatus(string $status): void
    {
        $this->status = $status;
        $this->internal_status = $status;
    }

    public static function setStatus(string $status): void
    {
        throw new \LogicException('Cannot set instance status statically: ' . $status);
    }

    public static function setInternalStatus(string $status): void
    {
        self::setStatus($status);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getInternalStatus(): string
    {
        return $this->internal_status;
    }
}
