<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class LocalizedLabel
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid')]
    public string $catalogId = '';

    #[ORM\Id]
    #[ORM\Column(length: 8)]
    public string $locale = '';

    #[ORM\Embedded(class: LabelText::class)]
    public ?LabelText $text = null;
}
