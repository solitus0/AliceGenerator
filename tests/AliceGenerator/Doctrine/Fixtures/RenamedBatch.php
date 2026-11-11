<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RenamedBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    /** @var Collection<int, RenamedProgress> */
    #[ORM\OneToMany(mappedBy: 'batch', targetEntity: RenamedProgress::class)]
    private Collection $progress;

    public function __construct(
        #[ORM\Column]
        public string $name = '',
    ) {
        $this->progress = new ArrayCollection();
    }

    /** @return Collection<int, RenamedProgress> */
    public function getProgress(): Collection
    {
        return $this->progress;
    }
}
