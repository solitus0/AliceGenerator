<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine\Fixtures;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
final class CommentModeration
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: Comment::class)]
    #[ORM\JoinColumn(nullable: false)]
    public ?Comment $comment = null;

    #[ORM\Column]
    public string $decision = '';
}
