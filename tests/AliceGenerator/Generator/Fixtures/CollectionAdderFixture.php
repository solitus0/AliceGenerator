<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Generator\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

final class CollectionAdderFixture
{
    public string $name = '';

    /** @var Collection<int, object> */
    private Collection $progress;

    public function __construct()
    {
        $this->progress = new ArrayCollection();
        $this->addProgress(new WritablePropertyFixture('Constructor default'));
    }

    /** @return Collection<int, object> */
    public function getProgress(): Collection
    {
        return $this->progress;
    }

    public function addProgress(WritablePropertyFixture $progress): void
    {
        $this->progress->add($progress);
    }

    public function removeProgress(WritablePropertyFixture $progress): void
    {
        $this->progress->removeElement($progress);
    }
}
