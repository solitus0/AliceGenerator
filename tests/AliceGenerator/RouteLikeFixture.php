<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\Tests;

final class RouteLikeFixture
{
    private ?string $webspace;

    private string $locale;

    private string $slug;

    private ?self $parentRoute;

    private string $resourceKey;

    private string $resourceId;

    public function __construct(
        string $resourceKey,
        string $resourceId,
        string $locale,
        string $slug,
        ?string $webspace = null,
        ?self $parentRoute = null
    ) {
        $this->resourceKey = $resourceKey;
        $this->resourceId = $resourceId;
        $this->locale = $locale;
        $this->slug = $slug;
        $this->webspace = $webspace;
        $this->parentRoute = $parentRoute;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function setWebspace(?string $webspace): static
    {
        $this->webspace = $webspace;

        return $this;
    }

    public function debugState(): array
    {
        return [$this->resourceKey, $this->resourceId, $this->locale, $this->slug, $this->webspace, $this->parentRoute];
    }
}
