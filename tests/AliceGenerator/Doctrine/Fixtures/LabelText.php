<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Embeddable]
final class LabelText
{
    public function __construct(
        #[ORM\Column]
        public string $caption = '',
        #[ORM\Column(nullable: true)]
        public ?string $description = null,
    ) {
    }
}
