<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class JobBatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    /** @var Collection<int, JobProgress> */
    #[ORM\OneToMany(mappedBy: 'batch', targetEntity: JobProgress::class)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $progress;

    public function __construct(
        #[ORM\Column]
        public string $name = '',
    ) {
        $this->progress = new ArrayCollection();
    }

    /** @return Collection<int, JobProgress> */
    public function getProgress(): Collection
    {
        return $this->progress;
    }
}
