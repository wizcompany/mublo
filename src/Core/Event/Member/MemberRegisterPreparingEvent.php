<?php

namespace Mublo\Core\Event\Member;

use Mublo\Core\Context\Context;
use Mublo\Core\Event\AbstractEvent;

/**
 * 회원가입 데이터 가공 확장 이벤트
 *
 * 검증 통과 후, DB 저장 전에 Plugin이 데이터를 변환/보강할 수 있는 확장점.
 * MemberService::register()에서 발행.
 *
 * 플러그인 데이터 충돌 방지:
 * - Core 필드 수정: set() 사용 (주의해서 사용)
 * - 플러그인 전용 데이터: setPluginData()로 네임스페이스 격리
 *
 * 사용 예 (ReferralPlugin):
 * ```php
 * public function prepareReferral(MemberRegisterPreparingEvent $event): void
 * {
 *     $code = $event->get('plugin_referral_code');
 *     if (!empty($code)) {
 *         $referrerId = $this->referralService->findMemberIdByCode($code);
 *         $event->setPluginData('referral', [
 *             'referrer_id' => $referrerId,
 *             'referral_code' => $code,
 *         ]);
 *     }
 * }
 * ```
 */
class MemberRegisterPreparingEvent extends AbstractEvent
{
    private array $data;
    private Context $context;

    /** @var array<string, array> 플러그인별 네임스페이스 데이터 */
    private array $pluginData = [];

    /**
     * @param array $data 가입 데이터 (검증 통과 후)
     * @param Context $context 현재 컨텍스트
     */
    public function __construct(array $data, Context $context)
    {
        $this->data = $data;
        $this->context = $context;
    }

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * 특정 키의 값 반환
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * 가입 데이터 수정 (Core 필드)
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    /**
     * 플러그인 전용 데이터 설정 (네임스페이스 격리)
     *
     * @param string $pluginName 플러그인 이름 (예: 'referral', 'geo')
     * @param array $data 플러그인 데이터
     */
    public function setPluginData(string $pluginName, array $data): void
    {
        $this->pluginData[$pluginName] = $data;
    }

    /**
     * 특정 플러그인 데이터 반환
     */
    public function getPluginData(string $pluginName): array
    {
        return $this->pluginData[$pluginName] ?? [];
    }

    /**
     * 모든 플러그인 데이터 반환
     *
     * @return array<string, array>
     */
    public function getAllPluginData(): array
    {
        return $this->pluginData;
    }
}
