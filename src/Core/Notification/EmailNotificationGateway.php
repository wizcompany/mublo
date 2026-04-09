<?php

namespace Mublo\Core\Notification;

use Mublo\Contract\Notification\NotificationGatewayInterface;
use Mublo\Infrastructure\Mail\Mailer;

/**
 * Core 이메일 알림 게이트웨이
 *
 * NotificationGatewayInterface 구현체로 ContractRegistry에 등록.
 * Aligo 플러그인(alimtalk/sms)과 동일한 1:N 패턴으로 'email' 채널을 담당한다.
 *
 * 흐름:
 *   NotificationActionHandler → findGatewayByChannel('email')
 *   → ContractRegistry에서 이 게이트웨이 반환
 *   → send() → Mailer.sendTemplate() 또는 Mailer.sendTo()
 */
class EmailNotificationGateway implements NotificationGatewayInterface
{
    private Mailer $mailer;

    public function __construct(Mailer $mailer)
    {
        $this->mailer = $mailer;
    }

    public function send(string $channel, string $templateCode, string $recipient, array $fieldValues): array
    {
        if ($channel !== 'email') {
            return ['success' => false, 'message' => "Unsupported channel: {$channel}"];
        }

        if (empty($recipient)) {
            return ['success' => false, 'message' => 'Recipient email is empty'];
        }

        try {
            if (!empty($templateCode)) {
                $subject = $this->resolveSubject($templateCode, $fieldValues);
                $sent = $this->mailer->sendTemplate($recipient, $subject, $templateCode, $fieldValues);
            } else {
                $subject = $fieldValues['subject'] ?? '알림';
                $body = $fieldValues['body'] ?? $this->buildDefaultBody($fieldValues);
                $sent = $this->mailer->sendTo($recipient, $subject, $body);
            }

            return $sent
                ? ['success' => true, 'message' => 'Email sent']
                : ['success' => false, 'message' => 'Email sending failed'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getSupportedChannels(): array
    {
        return ['email' => '이메일'];
    }

    public function getChannelTree(int $domainId): array
    {
        return [
            'email' => [
                'label'    => '이메일',
                'channels' => [],
            ],
        ];
    }

    /**
     * 템플릿 코드에 따른 제목 결정
     */
    private function resolveSubject(string $templateCode, array $fieldValues): string
    {
        $subjects = [
            'password-reset' => '비밀번호 재설정 안내',
            'welcome'        => '회원가입을 환영합니다',
        ];

        return $subjects[$templateCode] ?? ($fieldValues['subject'] ?? '알림');
    }

    /**
     * 템플릿 없을 때 fieldValues를 HTML 테이블로 변환
     */
    private function buildDefaultBody(array $fieldValues): string
    {
        $rows = '';
        foreach ($fieldValues as $key => $value) {
            $label = htmlspecialchars($key);
            $val = htmlspecialchars((string) $value);
            $rows .= "<tr><td style='padding:6px 12px;font-weight:bold;border:1px solid #ddd'>{$label}</td>"
                    . "<td style='padding:6px 12px;border:1px solid #ddd'>{$val}</td></tr>";
        }

        return "<table style='border-collapse:collapse;font-family:sans-serif;font-size:14px'>{$rows}</table>";
    }
}
