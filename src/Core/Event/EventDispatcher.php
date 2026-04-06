<?php

namespace Mublo\Core\Event;

/**
 * Event Dispatcher
 *
 * 이벤트 발송 및 리스너 관리
 */
class EventDispatcher
{
    /**
     * 이벤트 리스너
     * [eventName => [priority => [callable, ...]]]
     */
    private array $listeners = [];

    /**
     * 정렬된 리스너 캐시
     * [eventName => [callable, ...]]
     */
    private array $sortedListeners = [];

    /**
     * 리스너 등록
     *
     * @param string $eventName 이벤트명 (클래스명)
     * @param callable $listener 리스너 콜백
     * @param int $priority 우선순위 (높을수록 먼저 실행)
     */
    public function addListener(string $eventName, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventName][$priority][] = $listener;
        unset($this->sortedListeners[$eventName]);
    }

    /**
     * Subscriber 등록
     *
     * EventSubscriberInterface를 구현한 클래스의 모든 이벤트 리스너를 등록
     *
     * @param EventSubscriberInterface $subscriber
     */
    public function addSubscriber(EventSubscriberInterface $subscriber): void
    {
        foreach ($subscriber::getSubscribedEvents() as $eventName => $params) {
            // 'methodName' 형식
            if (is_string($params)) {
                $this->addListener($eventName, [$subscriber, $params]);
                continue;
            }

            // ['methodName', $priority] 또는 [['method1', $priority1], ['method2', $priority2]] 형식
            if (is_array($params)) {
                // ['methodName', $priority] 형식
                if (is_string($params[0])) {
                    $this->addListener(
                        $eventName,
                        [$subscriber, $params[0]],
                        $params[1] ?? 0
                    );
                    continue;
                }

                // [['method1', $priority1], ['method2', $priority2]] 형식
                foreach ($params as $listener) {
                    $this->addListener(
                        $eventName,
                        [$subscriber, $listener[0]],
                        $listener[1] ?? 0
                    );
                }
            }
        }
    }

    /**
     * 리스너 제거
     */
    public function removeListener(string $eventName, callable $listener): void
    {
        if (!isset($this->listeners[$eventName])) {
            return;
        }

        foreach ($this->listeners[$eventName] as $priority => &$listeners) {
            foreach ($listeners as $key => $existingListener) {
                if ($existingListener === $listener) {
                    unset($listeners[$key]);
                }
            }
        }

        // 모든 우선순위 배열이 비었으면 이벤트 키 자체를 제거
        $hasListeners = false;
        foreach ($this->listeners[$eventName] as $listeners) {
            if (!empty($listeners)) {
                $hasListeners = true;
                break;
            }
        }
        if (!$hasListeners) {
            unset($this->listeners[$eventName]);
        }

        unset($this->sortedListeners[$eventName]);
    }

    /**
     * 이벤트 발송
     *
     * @param EventInterface $event 이벤트 객체
     * @return EventInterface 처리된 이벤트 객체
     */
    public function dispatch(EventInterface $event): EventInterface
    {
        $eventName = $event->getName();
        $listeners = $this->getListeners($eventName);

        foreach ($listeners as $listener) {
            if ($event->isPropagationStopped()) {
                break;
            }

            try {
                $listener($event);
            } catch (\Error $e) {
                // PHP Error (TypeError, ParseError 등)는 재throw — 잡으면 안 되는 치명 오류
                throw $e;
            } catch (\Throwable $e) {
                error_log("[EventDispatcher] Listener exception on {$eventName}: " . get_class($e) . ': ' . $e->getMessage());
            }
        }

        return $event;
    }

    /**
     * 특정 이벤트의 리스너 목록 반환
     */
    public function getListeners(string $eventName): array
    {
        if (!isset($this->listeners[$eventName])) {
            return [];
        }

        if (!isset($this->sortedListeners[$eventName])) {
            $this->sortedListeners[$eventName] = $this->sortListeners($eventName);
        }

        return $this->sortedListeners[$eventName];
    }

    /**
     * 리스너가 있는지 확인
     */
    public function hasListeners(string $eventName): bool
    {
        return !empty($this->listeners[$eventName]);
    }

    /**
     * 리스너 우선순위별 정렬
     */
    private function sortListeners(string $eventName): array
    {
        $listeners = $this->listeners[$eventName];
        krsort($listeners);

        return array_merge(...$listeners);
    }
}
