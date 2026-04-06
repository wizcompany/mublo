<?php

namespace Mublo\Contract\Notification;

/**
 * 알림 발송 계약 인터페이스
 *
 * ContractRegistry에 등록되어 1:N 메시지 발송을 지원합니다.
 * 각 발송 플러그인(알리고, Twilio 등)은 이 인터페이스를 구현합니다.
 */
interface NotificationGatewayInterface
{
    /**
     * 메시지 발송
     *
     * @param string $channel      채널 타입 ('alimtalk', 'sms', 'email' 등)
     * @param string $templateCode 템플릿 코드
     * @param string $recipient    수신자 (전화번호 또는 이메일)
     * @param array  $fieldValues  치환 변수 ['orderer_name' => '홍길동', ...]
     * @return array ['success' => bool, 'message' => string]
     */
    public function send(
        string $channel,
        string $templateCode,
        string $recipient,
        array $fieldValues
    ): array;

    /**
     * 지원하는 채널 목록
     *
     * @return array<string, string> ['alimtalk' => '카카오 알림톡', 'sms' => 'SMS']
     */
    public function getSupportedChannels(): array;

    /**
     * 채널·템플릿 트리 조회
     *
     * @param int $domainId
     * @return array [
     *   'alimtalk' => ['label' => '카카오 알림톡', 'channels' => [
     *     ['id' => 1, 'name' => '위즈컴퍼니', 'templates' => [
     *       ['code' => 'FORM_001', 'name' => '접수완료'],
     *     ]],
     *   ]],
     *   'sms' => ['label' => 'SMS', 'channels' => [...]],
     * ]
     */
    public function getChannelTree(int $domainId): array;
}
