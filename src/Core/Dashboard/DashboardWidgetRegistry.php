<?php

namespace Mublo\Core\Dashboard;

class DashboardWidgetRegistry
{
    /** @var array<string, array{widget: DashboardWidgetInterface, priority: int, source: string}> */
    private array $widgets = [];

    /**
     * 위젯 등록
     */
    public function register(string $id, DashboardWidgetInterface $widget, int $priority = 50): void
    {
        $this->widgets[$id] = [
            'widget'   => $widget,
            'priority' => $priority,
            'source'   => $this->detectSource($id),
        ];
    }

    /**
     * 전체 위젯 (priority 정렬)
     *
     * @return array<string, array{widget: DashboardWidgetInterface, priority: int, source: string}>
     */
    public function all(): array
    {
        $sorted = $this->widgets;
        uasort($sorted, fn($a, $b) => $a['priority'] <=> $b['priority']);
        return $sorted;
    }

    /**
     * 위젯 조회
     */
    public function get(string $id): ?DashboardWidgetInterface
    {
        return $this->widgets[$id]['widget'] ?? null;
    }

    /**
     * 위젯 존재 여부
     */
    public function has(string $id): bool
    {
        return isset($this->widgets[$id]);
    }

    /**
     * 위젯 소스 조회 (core, plugin, package)
     */
    public function getSource(string $id): string
    {
        return $this->widgets[$id]['source'] ?? 'unknown';
    }

    /**
     * 등록된 위젯 ID 목록
     *
     * @return string[]
     */
    public function ids(): array
    {
        return array_keys($this->widgets);
    }

    /**
     * ID 패턴으로 소스 판별
     */
    private function detectSource(string $id): string
    {
        if (str_starts_with($id, 'core.')) return 'core';
        if (str_starts_with($id, 'plugin.')) return 'plugin';
        return 'package';
    }
}
