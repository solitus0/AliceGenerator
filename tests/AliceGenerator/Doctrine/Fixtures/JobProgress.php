<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class JobProgress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    public function __construct(
        #[ORM\Column]
        public string $label = '',
        #[ORM\ManyToOne(inversedBy: 'progress')]
        public ?JobBatch $batch = null,
    ) {
    }
}
