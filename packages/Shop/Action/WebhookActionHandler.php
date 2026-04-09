<?php

namespace Mublo\Packages\Shop\Action;

use Mublo\Packages\Shop\Event\OrderStatusChangedEvent;
use Mublo\Infrastructure\Logging\Logger;

/**
 * WebhookActionHandler
 *
 * 외부 URL로 주문 상태 변경 정보를 JSON HTTP 요청으로 전송한다.
 * 타임아웃 10초, 실패 시 로그만 기록 (재시도 없음, 비차단).
 */
class WebhookActionHandler implements ActionHandlerInterface
{
    private const TIMEOUT_SECONDS = 10;

    public function execute(array $config, OrderStatusChangedEvent $event): void
    {
        $url = $config['url'] ?? '';
        $method = strtoupper($config['method'] ?? 'POST');

        if (empty($url)) {
            Logger::warning('OrderStateAction:webhook - URL이 비어있음', [
                'order_no' => $event->getOrderNo(),
            ]);
            return;
        }

        $order = $event->getOrder();

        // 민감 정보 제거
        unset(
            $order['orderer_phone'],
            $order['orderer_email'],
            $order['recipient_phone'],
            $order['shipping_address1'],
            $order['shipping_address2'],
            $order['shipping_zip'],
        );

        $payload = [
            'event' => 'order_status_changed',
            'order_no' => $event->getOrderNo(),
            'prev_status' => $event->getPrevStateId(),
            'prev_label' => $event->getPrevLabel(),
            'new_status' => $event->getNewStateId(),
            'new_label' => $event->getNewLabel(),
            'order' => $order,
            'timestamp' => date('c'),
        ];

        $jsonBody = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_POSTFIELDS => $jsonBody,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonBody),
                'X-Mublo-Event: order_status_changed',
            ],
            CURLOPT_TIMEOUT => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            Logger::warning('OrderStateAction:webhook 연결 실패', [
                'order_no' => $event->getOrderNo(),
                'url' => $url,
                'error' => $error,
            ]);
            return;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            Logger::warning('OrderStateAction:webhook HTTP 오류', [
                'order_no' => $event->getOrderNo(),
                'url' => $url,
                'http_code' => $httpCode,
                'response' => mb_substr($response, 0, 200),
            ]);
        } else {
            Logger::info('OrderStateAction:webhook 성공', [
                'order_no' => $event->getOrderNo(),
                'url' => $url,
                'http_code' => $httpCode,
            ]);
        }
    }

    public function getType(): string
    {
        return 'webhook';
    }

    public function getLabel(): string
    {
        return '웹훅 호출';
    }

    public function getDescription(): string
    {
        return '외부 URL로 주문 정보를 HTTP 요청으로 전송합니다. 외부 시스템 연동에 활용됩니다.';
    }

    public function allowDuplicate(): bool
    {
        return true;
    }

    public function getSchema(): array
    {
        return [
            'required' => ['url', 'method'],
            'fields' => [
                'url' => [
                    'type' => 'text',
                    'label' => '웹훅 URL',
                    'placeholder' => 'https://example.com/webhook',
                ],
                'method' => [
                    'type' => 'select',
                    'label' => 'HTTP 메서드',
                    'options' => [
                        'POST' => 'POST',
                        'PUT' => 'PUT',
                    ],
                ],
            ],
        ];
    }
}
