<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RenamedProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'progress')]
    private ?RenamedBatch $batch = null;

    public function __construct(
        RenamedBatch $parent,
        #[ORM\Column]
        public string $label,
        #[ORM\Column]
        private string $status = 'Queued',
    ) {
        $this->batch = $parent;
    }

    public function getBatch(): ?RenamedBatch
    {
        return $this->batch;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
}
