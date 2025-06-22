<?php

declare(strict_types=1);

namespace Solitus0\AliceGenerator\ObjectHandler;

use Solitus0\AliceGenerator\ValueContext;

class ObjectHandlerRegistry implements ObjectHandlerRegistryInterface
{
    /**
     * @var ObjectHandlerInterface[]
     */
    protected $handlers = [];

    public function __construct(array $handlers = [])
    {
        $this->registerHandlers($handlers);
    }

    public function registerHandlers(array $handlers): void
    {
        foreach ($handlers as $handler) {
            $this->registerHandler($handler);
        }
    }

    public function registerHandler(ObjectHandlerInterface $objectHandler): void
    {
        array_unshift($this->handlers, $objectHandler);
    }

    public function runHandlers(ValueContext $valueContext): bool
    {
        foreach ($this->handlers as $handler) {
            if ($handler->handle($valueContext)) {
                return true;
            }
        }

        return false;
    }
}
