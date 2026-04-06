<?php

namespace Mublo\Packages\Shop\Action;

use Mublo\Contract\Notification\NotificationGatewayInterface;
use Mublo\Core\Registry\ContractRegistry;
use Mublo\Infrastructure\Logging\Logger;
use Mublo\Packages\Shop\Event\OrderStatusChangedEvent;

/**
 * NotificationActionHandler
 *
 * 알림 발송 액션 (알림톡, SMS, 이메일)
 *
 * ContractRegistry에서 NotificationGatewayInterface 구현체를 조회하여 발송.
 * Plugin이 구현체를 등록하지 않으면 로그만 남기고 건너뜀.
 */
class NotificationActionHandler implements ActionHandlerInterface
{
    public function __construct(
        private ContractRegistry $registry
    ) {}

    public function execute(array $config, OrderStatusChangedEvent $event): void
    {
        $channel      = $config['channel'] ?? '';
        $templateCode = $config['template_code'] ?? '';
        $recipient    = $config['recipient'] ?? 'orderer';

        if ($channel === '' || $templateCode === '') {
            Logger::warning('OrderStateAction:notification 설정 누락', [
                'order_no' => $event->getOrderNo(),
                'channel'  => $channel,
                'template' => $templateCode,
            ]);
            return;
        }

        // 채널을 지원하는 게이트웨이 탐색
        $gateway = $this->findGatewayByChannel($channel);
        if ($gateway === null) {
            Logger::info('OrderStateAction:notification 게이트웨이 미등록', [
                'order_no' => $event->getOrderNo(),
                'channel'  => $channel,
            ]);
            return;
        }

        // 수신자 전화번호 결정
        $order = $event->getOrder();
        $recipientPhone = $this->resolveRecipient($recipient, $order);

        if ($recipientPhone === '') {
            Logger::warning('OrderStateAction:notification 수신자 정보 없음', [
                'order_no'  => $event->getOrderNo(),
                'recipient' => $recipient,
            ]);
            return;
        }

        // 치환 변수 생성 + 발송
        $fieldValues = $this->prepareFieldValues($order, $event);
        $result = $gateway->send($channel, $templateCode, $recipientPhone, $fieldValues);

        if ($result['success'] ?? false) {
            Logger::info('OrderStateAction:notification 발송 성공', [
                'order_no' => $event->getOrderNo(),
                'channel'  => $channel,
                'template' => $templateCode,
            ]);
        } else {
            Logger::warning('OrderStateAction:notification 발송 실패', [
                'order_no' => $event->getOrderNo(),
                'channel'  => $channel,
                'message'  => $result['message'] ?? '',
            ]);
        }
    }

    public function getType(): string
    {
        return 'notification';
    }

    public function getLabel(): string
    {
        return '알림 발송';
    }

    public function getDescription(): string
    {
        return '주문 상태 변경 시 알림톡, SMS, 이메일 등으로 알림을 발송합니다. 채널/수신자별 다중 등록이 가능합니다.';
    }

    public function allowDuplicate(): bool
    {
        return true;
    }

    public function getSchema(): array
    {
        return [
            'required' => ['channel', 'template_code', 'recipient'],
            'optional' => ['enabled'],
            'fields' => [
                'channel' => [
                    'type' => 'select',
                    'label' => '발송 채널',
                    'options' => $this->getAvailableChannels(),
                ],
                'template_code' => [
                    'type' => 'text',
                    'label' => '템플릿 코드',
                    'placeholder' => '예: order_confirmed',
                ],
                'recipient' => [
                    'type' => 'select',
                    'label' => '수신자',
                    'options' => [
                        'orderer'   => '주문자',
                        'recipient' => '수령인',
                        'admin'     => '관리자',
                    ],
                ],
            ],
        ];
    }

    /**
     * 등록된 게이트웨이에서 해당 채널을 지원하는 것을 찾는다
     */
    private function findGatewayByChannel(string $channel): ?NotificationGatewayInterface
    {
        if (!$this->registry->has(NotificationGatewayInterface::class)) {
            return null;
        }

        $allMeta = $this->registry->allMeta(NotificationGatewayInterface::class);

        foreach ($allMeta as $key => $meta) {
            $channels = $meta['channels'] ?? [];
            if (in_array($channel, $channels, true)) {
                return $this->registry->get(NotificationGatewayInterface::class, $key);
            }
        }

        return null;
    }

    /**
     * 등록된 모든 게이트웨이의 채널을 병합하여 옵션 목록 생성
     */
    private function getAvailableChannels(): array
    {
        $channels = [];

        if ($this->registry->has(NotificationGatewayInterface::class)) {
            $allMeta = $this->registry->allMeta(NotificationGatewayInterface::class);
            foreach ($allMeta as $meta) {
                foreach ($meta['channels'] ?? [] as $ch) {
                    if (!isset($channels[$ch])) {
                        $channels[$ch] = $ch;
                    }
                }
            }
        }

        // 게이트웨이 미등록 시 기본 채널 표시
        if (empty($channels)) {
            $channels = [
                'alimtalk' => '카카오 알림톡',
                'sms'      => 'SMS',
                'email'    => '이메일',
            ];
        }

        return $channels;
    }

    /**
     * 수신자 유형에 따라 전화번호 결정
     */
    private function resolveRecipient(string $type, array $order): string
    {
        return match ($type) {
            'recipient' => $order['recipient_phone'] ?? '',
            'admin'     => $order['admin_phone'] ?? '',
            default     => $order['orderer_phone'] ?? '',
        };
    }

    /**
     * 주문 데이터에서 템플릿 치환 변수 생성
     */
    private function prepareFieldValues(array $order, OrderStatusChangedEvent $event): array
    {
        return [
            'orderer_name'  => $order['orderer_name'] ?? '',
            'orderer_phone' => $order['orderer_phone'] ?? '',
            'order_no'      => $event->getOrderNo(),
            'order_date'    => $order['created_at'] ?? '',
            'total_amount'  => $order['total_amount'] ?? 0,
            'status_label'  => $event->getNewLabel(),
            'prev_status'   => $event->getPrevLabel(),
            'device_name'   => $order['device_name'] ?? '',
        ];
    }
}
