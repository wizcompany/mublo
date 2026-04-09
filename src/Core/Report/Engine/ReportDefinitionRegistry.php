<?php

namespace Mublo\Core\Report\Engine;

use Mublo\Core\Report\Contract\ReportDefinitionInterface;

class ReportDefinitionRegistry
{
    /** @var array<string, ReportDefinitionInterface> */
    private array $definitions = [];

    public function register(ReportDefinitionInterface $definition): void
    {
        $this->definitions[$definition->name()] = $definition;
    }

    public function has(string $name): bool
    {
        return isset($this->definitions[$name]);
    }

    public function get(string $name): ReportDefinitionInterface
    {
        if (!$this->has($name)) {
            throw new \RuntimeException("Report definition not found: {$name}");
        }

        return $this->definitions[$name];
    }
}

