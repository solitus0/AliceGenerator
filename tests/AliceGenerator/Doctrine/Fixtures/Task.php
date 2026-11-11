<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Task
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $dueAt = null;

    /** @var Collection<int, Comment> */
    #[ORM\OneToMany(mappedBy: 'task', targetEntity: Comment::class)]
    private Collection $comments;

    public function __construct(
        #[ORM\Column]
        public string $title = '',
        #[ORM\ManyToOne(inversedBy: 'tasks')]
        public ?Project $project = null,
        #[ORM\ManyToOne(targetEntity: Assignee::class)]
        public Assignee|string|null $assignee = null,
        #[ORM\Column(enumType: TaskStatus::class)]
        public TaskStatus $status = TaskStatus::Published,
    ) {
        $this->comments = new ArrayCollection();
    }

    /** @return Collection<int, Comment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(Comment $comment): void
    {
        $this->comments->add($comment);
    }

    public function removeComment(Comment $comment): void
    {
        $this->comments->removeElement($comment);
    }
}
