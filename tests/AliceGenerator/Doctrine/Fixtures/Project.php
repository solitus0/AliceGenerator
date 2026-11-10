<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests\Doctrine\Fixtures;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Project
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    public ?int $id = null;

    #[ORM\Column(nullable: true)]
    public ?string $summary = null;

    public string $runtimeNote = '';

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(mappedBy: 'project', targetEntity: Task::class)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $tasks;

    /** @var Collection<int, Assignee> */
    #[ORM\ManyToMany(targetEntity: Assignee::class)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $watchers;

    public function __construct(
        #[ORM\Column]
        public string $name = '',
    ) {
        $this->tasks = new ArrayCollection();
        $this->watchers = new ArrayCollection();
    }

    /** @return Collection<int, Task> */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): void
    {
        $this->tasks->add($task);
    }

    public function removeTask(Task $task): void
    {
        $this->tasks->removeElement($task);
    }

    /** @return Collection<int, Assignee> */
    public function getWatchers(): Collection
    {
        return $this->watchers;
    }

    public function addWatcher(Assignee $watcher): void
    {
        $this->watchers->add($watcher);
    }

    public function removeWatcher(Assignee $watcher): void
    {
        $this->watchers->removeElement($watcher);
    }
}
