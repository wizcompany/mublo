<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Packages\Shop\Repository\ShopConfigRepository;
use Mublo\Packages\Shop\Enum\OrderAction;

/**
 * ShopConfig Service
 *
 * 쇼핑몰 설정 비즈니스 로직 담당
 */
class ShopConfigService
{
    private ShopConfigRepository $configRepository;

    /**
     * DB shop_config 컬럼 기본값
     */
    private const DEFAULT_CONFIG = [
        // 기본 설정
        'domain_group' => '',
        'shop_type' => 'self',
        'membership' => 'FREE',
        'cart_keep_days' => 15,
        'guest_cart_keep_days' => 7,
        'use_guest_cart' => 1,
        'skin_name' => 'basic',
        'title' => '',
        'default_shipping_template_id' => 0,

        // SEO / CS
        'seo_keyword' => '',
        'seo_description' => '',
        'kakao_chat_url' => '',
        'naver_chat_url' => '',
        'customer_tel' => '',
        'customer_time' => '',

        // 수수료
        'commission_type' => 1,
        'commission_rate' => 1,

        // 결제 설정
        'payment_pg_key' => '',
        'payment_pg_keys' => '',
        'payment_merchant_code' => '',
        'payment_methods' => '',
        'payment_bank_info' => '',
        'use_point_payment' => 1,
        'point_unit' => 100,
        'point_min' => 100,
        'point_max' => 30000,

        // 적립금
        'reward_type' => 'NONE',
        'reward_value' => 0,
        'reward_review' => 0,

        // 할인
        'discount_type' => 'NONE',
        'discount_value' => 0,

        // 쿠폰 / 주문상태
        'use_coupon' => 1,
        'order_states' => '', // JSON 문자열 (빈값이면 OrderAction::defaultStates() 사용)
        'order_state_actions' => '', // JSON 문자열 (상태별 액션 설정)

        // 상품 상세 탭
        'goods_view_tab' => '', // CSV (review,qna,faq)
    ];

    /**
     * 저장 허용 필드 (화이트리스트)
     */
    private const ALLOWED_FIELDS = [
        'domain_group', 'shop_type', 'membership', 'cart_keep_days', 'guest_cart_keep_days', 'use_guest_cart', 'skin_name', 'title',
        'default_shipping_template_id',
        'seo_keyword', 'seo_description', 'kakao_chat_url', 'naver_chat_url', 'customer_tel', 'customer_time',
        'commission_type', 'commission_rate',
        'payment_pg_key', 'payment_pg_keys', 'payment_merchant_code', 'payment_methods', 'payment_bank_info',
        'use_point_payment', 'point_unit', 'point_min', 'point_max',
        'reward_type', 'reward_value', 'reward_review',
        'discount_type', 'discount_value',
        'use_coupon', 'order_states', 'order_state_actions',
        'goods_view_tab',
    ];

    public function __construct(ShopConfigRepository $configRepository)
    {
        $this->configRepository = $configRepository;
    }

    /**
     * 도메인별 쇼핑몰 설정 조회
     */
    public function getConfig(int $domainId): Result
    {
        $config = $this->configRepository->getConfig($domainId);

        if (!$config) {
            $defaultConfig = array_merge(self::DEFAULT_CONFIG, ['domain_id' => $domainId]);
            $defaultConfig['order_states'] = $this->resolveOrderStates('');
            return Result::success('기본 설정을 반환합니다.', [
                'config' => $defaultConfig,
            ]);
        }

        // DB 값 + 기본값 병합 (DB에 NULL인 필드는 기본값 사용)
        $merged = array_merge(self::DEFAULT_CONFIG, array_filter($config, fn($v) => $v !== null));

        // order_states: 빈값이면 기본 시스템 액션 사용
        $merged['order_states'] = $this->resolveOrderStates($merged['order_states'] ?? '');

        return Result::success('설정을 조회했습니다.', ['config' => $merged]);
    }

    /**
     * 도메인별 쇼핑몰 설정 저장
     */
    public function saveConfig(int $domainId, array $data): Result
    {
        // 화이트리스트 필터
        $filtered = array_intersect_key($data, array_flip(self::ALLOWED_FIELDS));

        if (empty($filtered)) {
            return Result::failure('저장할 설정 데이터가 없습니다.');
        }

        $sanitized = $this->sanitize($filtered);

        $saved = $this->configRepository->saveConfig($domainId, $sanitized);

        if ($saved) {
            return Result::success('쇼핑몰 설정이 저장되었습니다.');
        }

        return Result::failure('쇼핑몰 설정 저장에 실패했습니다.');
    }

    /**
     * order_states JSON 해석 및 기본값 처리
     *
     * 구 스키마(id 없음) → 새 스키마 자동 변환 포함
     *
     * @param string $orderStates JSON 문자열 또는 빈값
     * @return string 정규화된 JSON 문자열
     */
    private function resolveOrderStates(string $orderStates): string
    {
        if (empty($orderStates)) {
            return json_encode(OrderAction::defaultStates(), JSON_UNESCAPED_UNICODE);
        }

        // 기존 CSV 형식 호환 (마이그레이션 전 데이터)
        if (!str_starts_with(trim($orderStates), '[')) {
            return json_encode(OrderAction::defaultStates(), JSON_UNESCAPED_UNICODE);
        }

        $decoded = json_decode($orderStates, true);
        if (!is_array($decoded) || empty($decoded)) {
            return json_encode(OrderAction::defaultStates(), JSON_UNESCAPED_UNICODE);
        }

        // 새 스키마 여부 체크: id 필드 존재 여부
        if (!isset($decoded[0]['id'])) {
            $decoded = $this->migrateToNewSchema($decoded);
            return json_encode($decoded, JSON_UNESCAPED_UNICODE);
        }

        return $orderStates;
    }

    /**
     * 구 스키마 → 새 스키마 자동 변환
     *
     * 기존: {label, action, sort_order}
     * 신규: {id, label, description, action, to, terminal, system, sort_order}
     */
    private function migrateToNewSchema(array $oldStates): array
    {
        $defaults = OrderAction::defaultStates();
        $defaultMap = [];
        foreach ($defaults as $d) {
            $defaultMap[$d['action']] = $d;
        }

        $newStates = [];
        foreach ($oldStates as $old) {
            $action = $old['action'] ?? '';
            $label = $old['label'] ?? '';

            if (empty($action) || empty($label)) {
                continue;
            }

            // 시스템 액션: 기본값에서 to/terminal 가져옴
            if (isset($defaultMap[$action])) {
                $def = $defaultMap[$action];
                $newStates[] = [
                    'id' => $def['id'],
                    'label' => $label, // 사용자 커스텀 라벨 유지
                    'description' => $def['description'],
                    'action' => $action,
                    'to' => $def['to'],
                    'terminal' => $def['terminal'],
                    'system' => true,
                    'sort_order' => $old['sort_order'] ?? 0,
                ];
                unset($defaultMap[$action]);
            } else {
                // 커스텀 상태: custom:{label} → id 생성
                $id = $this->generateCustomId($action, $label);
                $newStates[] = [
                    'id' => $id,
                    'label' => str_starts_with($action, 'custom:') ? substr($action, 7) : $label,
                    'description' => '',
                    'action' => 'custom',
                    'to' => [],
                    'terminal' => false,
                    'system' => false,
                    'sort_order' => $old['sort_order'] ?? 0,
                ];
            }
        }

        // 누락된 시스템 액션 보충
        $maxSort = max(array_column($newStates, 'sort_order') ?: [0]);
        foreach ($defaultMap as $def) {
            $maxSort++;
            $def['sort_order'] = $maxSort;
            $newStates[] = $def;
        }

        return $newStates;
    }

    /**
     * 커스텀 상태 id 생성
     *
     * @param string $action 기존 action (예: custom:확인중)
     * @param string $label 라벨
     * @return string 생성된 id (예: custom_a3f2)
     */
    private function generateCustomId(string $action, string $label): string
    {
        $base = str_starts_with($action, 'custom:') ? substr($action, 7) : $label;
        $slug = $this->toSlug($base);
        $hash = substr(md5($base . microtime(true)), 0, 4);

        $id = $slug . '_' . $hash;

        // 최대 50자 제한
        if (strlen($id) > 50) {
            $id = substr($slug, 0, 45) . '_' . $hash;
        }

        return $id;
    }

    /**
     * 라벨 → slug 변환 (간단한 영문/숫자 변환)
     */
    private function toSlug(string $label): string
    {
        // 영문/숫자만 남기고 나머지는 underscore
        $slug = preg_replace('/[^a-zA-Z0-9]+/', '_', $label);
        $slug = trim($slug, '_');

        // 빈 문자열이면 custom
        if (empty($slug)) {
            $slug = 'custom';
        }

        return strtolower($slug);
    }

    /**
     * order_states JSON 검증 및 정규화 (FSM 새 스키마)
     *
     * 검증 항목:
     * 1. 시스템 액션 누락 여부
     * 2. id 고유성 검증
     * 3. from/to 참조 유효성
     * 4. terminal 상태의 to는 빈 배열이어야 함
     * 5. 시작 상태(receipt) 존재 확인
     * 6. 시스템 상태 불변 필드 보호 (id, action 변경 금지)
     *
     * @param string $json 프론트에서 전달된 JSON 문자열
     * @param string|null $previousJson 이전 설정 JSON (시스템 상태 불변 검증용)
     * @return array ['valid' => bool, 'json' => string, 'errors' => string[], 'warnings' => string[]]
     */
    public function validateOrderStates(string $json, ?string $previousJson = null): array
    {
        $states = json_decode($json, true);
        $errors = [];
        $warnings = [];

        if (!is_array($states) || empty($states)) {
            return [
                'valid' => false,
                'json' => json_encode(OrderAction::defaultStates(), JSON_UNESCAPED_UNICODE),
                'errors' => ['유효하지 않은 JSON 형식입니다.'],
                'warnings' => [],
            ];
        }

        // 전체 id 목록 (참조 유효성 검사용)
        $allIds = array_column($states, 'id');

        // 1. id 고유성 검증
        $duplicateIds = array_filter(array_count_values($allIds), fn($count) => $count > 1);
        foreach ($duplicateIds as $id => $count) {
            $errors[] = "중복된 상태 ID가 있습니다: '{$id}' ({$count}개)";
        }

        // 2. 시스템 액션 누락 체크
        $existingActions = [];
        foreach ($states as $state) {
            if ($state['system'] ?? false) {
                $existingActions[] = $state['action'] ?? '';
            }
        }

        foreach (OrderAction::systemCases() as $systemCase) {
            if (!in_array($systemCase->value, $existingActions, true)) {
                // 누락된 시스템 액션 자동 보충은 하지 않고 에러 리포트
                $errors[] = "시스템 상태 '{$systemCase->defaultLabel()}'({$systemCase->value})이(가) 누락되었습니다.";
            }
        }

        // 3. 각 항목 검증
        foreach ($states as $i => $state) {
            $id = $state['id'] ?? '';
            $label = trim($state['label'] ?? '');

            if (empty($id)) {
                $errors[] = ($i + 1) . "번째 상태의 ID가 비어있습니다.";
                continue;
            }

            if (empty($label)) {
                $errors[] = "상태 '{$id}'의 라벨이 비어있습니다.";
            }

            // to 참조 유효성
            foreach (($state['to'] ?? []) as $toId) {
                if (!in_array($toId, $allIds, true)) {
                    $warnings[] = "상태 '{$id}'의 이동 가능 목록에 존재하지 않는 상태 '{$toId}'가 포함되어 있습니다.";
                }
            }

            // terminal 상태의 to는 빈 배열이어야 함
            if (($state['terminal'] ?? false) && !empty($state['to'] ?? [])) {
                $errors[] = "종료 상태 '{$id}'에 이동 가능 상태가 설정되어 있습니다. 종료 상태의 이동 목록은 비어있어야 합니다.";
            }
        }

        // 4. 시작 상태(receipt) 존재 확인
        if (!in_array('receipt', $allIds, true)) {
            $errors[] = "시작 상태 'receipt'가 존재하지 않습니다.";
        }

        // 5. 시스템 상태 불변 필드 보호
        if ($previousJson !== null) {
            $prevStates = json_decode($previousJson, true);
            if (is_array($prevStates)) {
                $prevSystemMap = [];
                foreach ($prevStates as $s) {
                    if ($s['system'] ?? false) {
                        $prevSystemMap[$s['id']] = $s;
                    }
                }

                foreach ($states as $s) {
                    if (!($s['system'] ?? false)) {
                        continue;
                    }
                    $id = $s['id'] ?? '';

                    if (isset($prevSystemMap[$id])) {
                        $prev = $prevSystemMap[$id];
                        // action 변경 차단
                        if (($s['action'] ?? '') !== ($prev['action'] ?? '')) {
                            $errors[] = "시스템 상태 '{$id}'의 action 값은 변경할 수 없습니다. "
                                      . "('{$prev['action']}' → '{$s['action']}')";
                        }
                    }
                }

                // system: true → false 전환 차단
                foreach ($prevSystemMap as $id => $prev) {
                    $found = array_filter($states, fn($s) => ($s['id'] ?? '') === $id);
                    if (!empty($found)) {
                        $current = reset($found);
                        if (!($current['system'] ?? false)) {
                            $errors[] = "시스템 상태 '{$id}'의 system 플래그를 해제할 수 없습니다.";
                        }
                    }
                }
            }
        }

        // 정규화: sort_order 재할당
        usort($states, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));
        foreach ($states as $i => &$state) {
            $state['sort_order'] = $i + 1;
        }
        unset($state);

        $validJson = json_encode($states, JSON_UNESCAPED_UNICODE);

        return [
            'valid' => empty($errors),
            'json' => $validJson,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * 특정 상태의 액션 설정 조회
     *
     * @param int $domainId 도메인 ID
     * @param string $stateId 상태 id
     * @return array 액션 설정 배열 [{type, enabled, ...}, ...]
     */
    public function getStateActions(int $domainId, string $stateId): array
    {
        $allActions = $this->getAllStateActions($domainId);
        return $allActions[$stateId] ?? [];
    }

    /**
     * 전체 상태별 액션 설정 조회
     *
     * @param int $domainId 도메인 ID
     * @return array {stateId => [{type, enabled, ...}, ...], ...}
     */
    public function getAllStateActions(int $domainId): array
    {
        $result = $this->getConfig($domainId);
        $config = $result->get('config', []);
        $actionsJson = $config['order_state_actions'] ?? '';

        if (empty($actionsJson)) {
            return [];
        }

        $decoded = is_string($actionsJson) ? json_decode($actionsJson, true) : $actionsJson;
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * 상태별 액션 설정 저장
     *
     * @param int $domainId 도메인 ID
     * @param array $stateActions {stateId => [{type, enabled, ...}, ...], ...}
     * @return Result
     */
    public function saveStateActions(int $domainId, array $stateActions): Result
    {
        $json = json_encode($stateActions, JSON_UNESCAPED_UNICODE);

        return $this->saveConfig($domainId, [
            'order_state_actions' => $json,
        ]);
    }

    /**
     * 입력 데이터 정제
     */
    private function sanitize(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $sanitized[$key] = match ($key) {
                // 정수
                'cart_keep_days', 'commission_type', 'commission_rate',
                'point_unit', 'point_min', 'point_max', 'reward_review'
                    => (int)$value,

                // 불리언 (TINYINT)
                'use_guest_cart', 'use_point_payment', 'use_coupon'
                    => !empty($value) ? 1 : 0,

                // 소수
                'discount_value', 'reward_value'
                    => round((float)$value, 2),

                // ENUM
                'shop_type'
                    => in_array($value, ['self', 'other']) ? $value : 'self',
                'discount_type', 'reward_type'
                    => in_array($value, ['NONE', 'BASIC', 'LEVEL', 'PERCENTAGE', 'FIXED']) ? $value : 'NONE',

                // 문자열 (trim)
                default => is_string($value) ? trim($value) : $value,
            };
        }

        return $sanitized;
    }
}
