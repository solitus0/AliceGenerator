<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Export\Fixtures;

use Doctrine\ORM\Mapping as ORM;
use Solitus0\AliceGenerator\Tests\Doctrine\Fixtures\JobBatch;

#[ORM\Entity]
final class RecipeRecord
{
    #[ORM\Id]
    #[ORM\Column]
    public ?int $id = null;

    /** @var array<array-key, mixed> */
    #[ORM\Column(type: 'json')]
    public array $payload = ['default'];

    #[ORM\ManyToOne(targetEntity: JobBatch::class)]
    private ?JobBatch $batch;

    public function __construct(?JobBatch $sourceBatch = null)
    {
        $this->batch = $sourceBatch;
    }

    public function getBatch(): ?JobBatch
    {
        return $this->batch;
    }
}
