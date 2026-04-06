<?php
/**
 * src/Controller/Admin/MemberController.php
 *
 * 관리자 회원 관리 컨트롤러
 *
 * URL: /admin/member
 */

namespace Mublo\Controller\Admin;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\Member\MemberFormRenderingEvent;
use Mublo\Core\Event\Member\MemberDataEnrichingEvent;
use Mublo\Service\Member\MemberService;
use Mublo\Service\Member\MemberAdminService;
use Mublo\Service\Member\MemberFieldService;
use Mublo\Service\Member\MemberLevelService;
use Mublo\Service\Auth\AuthService;
use Mublo\Helper\Form\FormHelper;
use Mublo\Repository\Balance\BalanceLogRepository;

class MemberController
{
    private MemberService $memberService;
    private MemberAdminService $memberAdminService;
    private MemberFieldService $fieldService;
    private MemberLevelService $levelService;
    private AuthService $authService;
    private BalanceLogRepository $balanceLogRepository;
    private ?EventDispatcher $eventDispatcher;

    public function __construct(
        MemberService $memberService,
        MemberAdminService $memberAdminService,
        MemberFieldService $fieldService,
        MemberLevelService $levelService,
        AuthService $authService,
        BalanceLogRepository $balanceLogRepository,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->memberService = $memberService;
        $this->memberAdminService = $memberAdminService;
        $this->fieldService = $fieldService;
        $this->levelService = $levelService;
        $this->authService = $authService;
        $this->balanceLogRepository = $balanceLogRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * 회원 목록
     *
     * GET /admin/member
     *
     * View에서 $this->columns(), $this->listRenderHelper를 사용하여 렌더링
     */
    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $domainGroup = $context->getDomainGroup();
        $request = $context->getRequest();

        // 페이징/검색 파라미터
        $page = (int) ($request->get('page') ?? 1);
        $defaultPerPage = (int) ($context->getDomainInfo()?->getSiteConfig()['per_page'] ?? 20);
        $perPage = (int) ($request->get('per_page') ?? $defaultPerPage);
        $searchField = $request->get('search_field') ?? '';
        $searchKeyword = $request->get('search_keyword') ?? '';

        // 검색 필드 목록 조회 (members 컬럼 + 추가 필드)
        $searchFieldsData = $this->memberAdminService->getSearchFields($domainId);

        // 검색 조건 구성
        $search = [];
        if ($searchKeyword && $searchField) {
            $fieldData = $searchFieldsData[$searchField] ?? null;
            $search = [
                'keyword' => $searchKeyword,
                'field' => $searchField,
                'field_info' => $fieldData['field_info'] ?? null, // 추가 필드면 field_info 포함
            ];
        }

        // 회원 목록 조회 (추가 필드 포함)
        // domain_group을 전달하여 하위 사이트 회원까지 계층 범위로 조회
        $result = $this->memberAdminService->getListWithFields($domainId, $page, $perPage, $search, $domainGroup);
        $members = $result['data']['members'] ?? [];
        $listFields = $result['data']['listFields'] ?? [];
        $pagination = $result['data']['pagination'] ?? [];

        // View용 검색 필드 옵션 (label 추출, 암호화 필드는 🔒 표시)
        $searchFields = [];
        foreach ($searchFieldsData as $fieldName => $data) {
            $label = $data['label'];
            $isEncrypted = $data['field_info']['is_encrypted'] ?? false;
            $searchFields[$fieldName] = $isEncrypted ? "🔒 {$label}" : $label;
        }

        // 등급 옵션 (select box용)
        $levelOptions = $this->levelService->getOptionsForSelect();

        // View에서 $this->columns(), $this->listRenderHelper로 렌더링
        return ViewResponse::view('member/index')
            ->withData([
                'pageTitle' => '회원 관리',
                'members' => $members,           // View에서 리스트 렌더링
                'listFields' => $listFields,     // 추가 필드 정보
                'pagination' => $pagination,
                'searchFields' => $searchFields,
                'levelOptions' => $levelOptions, // 등급 선택 옵션
                'currentSearch' => [
                    'field' => $searchField,
                    'keyword' => $searchKeyword,
                ],
            ]);
    }

    /**
     * 회원 등록 폼
     *
     * GET /admin/member/create
     */
    public function create(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;

        // 추가 필드 정의 조회
        $fieldDefinitions = $this->fieldService->getFields($domainId);

        // 최고관리자 여부
        $currentAdmin = $this->authService->user();
        $adminIsSuper = (bool) ($currentAdmin['is_super'] ?? false);

        // 등급 옵션 (비회원 제외, 최고관리자가 아니면 super 등급 제외)
        $levelOptions = $this->levelService->getOptionsForSelect(false, !$adminIsSuper);

        // 상태 옵션
        $statusOptions = $this->getStatusOptions();

        // 플러그인 폼 확장 이벤트
        $formEvent = new MemberFormRenderingEvent('create', null, $context);
        $this->eventDispatcher?->dispatch($formEvent);

        return ViewResponse::view('member/form')
            ->withData([
                'pageTitle' => '회원 등록',
                'mode' => 'create',
                'member' => null,
                'fieldDefinitions' => $fieldDefinitions,
                'fieldValues' => [],
                'levelOptions' => $levelOptions,
                'statusOptions' => $statusOptions,
                'adminIsSuper' => $adminIsSuper,
                'pluginSections' => $formEvent->getSectionsSorted(),
                'pluginScripts' => $formEvent->getScriptsSorted(),
            ]);
    }

    /**
     * 회원 등록 처리
     *
     * POST /admin/member/store
     */
    public function store(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        // 현재 로그인 관리자 정보
        $currentAdmin = $this->authService->user();

        // formData[필드명] 형식으로 전송된 데이터 정제
        $formData = $request->input('formData') ?? [];
        $normalizedData = FormHelper::normalizeFormData($formData, $this->getFormSchema());

        $data = [
            'domain_id' => $domainId,
            'user_id' => trim($normalizedData['user_id'] ?? ''),
            'password' => $normalizedData['password'] ?? '',
            'nickname' => trim($normalizedData['nickname'] ?? ''),
            'level_value' => (int) ($normalizedData['level_value'] ?? 1),
            'status' => $normalizedData['status'] ?? 'active',
            'fields' => $request->input('fields') ?? [],  // 다차원 배열은 별도 처리
            // 관리자 권한 검증용
            'admin_id' => $currentAdmin['member_id'] ?? 0,
            'admin_is_super' => $currentAdmin['is_super'] ?? false,
            'admin_level_value' => (int) ($currentAdmin['level_value'] ?? 0),
            'admin_level_type' => $currentAdmin['level_type'] ?? null,
            'admin_domain_group' => $currentAdmin['domain_group'] ?? null,
        ];

        // MemberAdminService의 register 호출
        $result = $this->memberAdminService->register($data);

        if ($result->isSuccess()) {
            return JsonResponse::success(
                ['redirect' => '/admin/member'],
                $result->getMessage()
            );
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 회원 수정 폼
     *
     * GET /admin/member/edit/{id}
     */
    public function edit(array $params, Context $context): ViewResponse
    {
        // autoResolve는 숫자 배열 ['123'], 명시적 라우트는 ['id' => '123']
        $memberId = (int) ($params['id'] ?? $params[0] ?? 0);
        $domainId = $context->getDomainId() ?? 1;

        if (!$memberId) {
            return ViewResponse::view('Error/404')
                ->withData(['message' => '회원을 찾을 수 없습니다.']);
        }

        $member = $this->memberService->findById($memberId);

        $currentAdmin = $this->authService->user();
        $adminIsSuper = (bool) ($currentAdmin['is_super'] ?? false);

        if (!$member || (!$adminIsSuper && $member->getDomainId() !== $domainId)) {
            return ViewResponse::view('Error/404')
                ->withData(['message' => '회원을 찾을 수 없습니다.']);
        }

        // 추가 필드 정의 조회
        $fieldDefinitions = $this->fieldService->getFields($domainId);

        // 추가 필드 값 조회 (field_id => value 형태로 변환)
        $fieldValuesRaw = $this->memberService->getFieldValues($memberId);
        $fieldValues = [];
        foreach ($fieldValuesRaw as $fv) {
            $fieldValues[$fv['field_id']] = $fv['field_value'];
        }

        // 등급 옵션 (비회원 제외, 최고관리자가 아니면 super 등급 제외)
        $levelOptions = $this->levelService->getOptionsForSelect(false, !$adminIsSuper);

        // 상태 옵션
        $statusOptions = $this->getStatusOptions();

        // 최근 포인트 내역 5건
        $this->balanceLogRepository->setDomainId($domainId);
        $recentPointLogs = array_map(
            fn($log) => $log->toArray(),
            $this->balanceLogRepository->getByMember($memberId, 1, 5)
        );

        $memberArray = $member->toArray();

        // 플러그인 데이터 보강 이벤트
        $enrichEvent = new MemberDataEnrichingEvent($memberId, $memberArray, 'admin_detail');
        $this->eventDispatcher?->dispatch($enrichEvent);

        // 플러그인 폼 확장 이벤트
        $formEvent = new MemberFormRenderingEvent('edit', $memberArray, $context);
        $this->eventDispatcher?->dispatch($formEvent);

        return ViewResponse::view('member/form')
            ->withData([
                'pageTitle' => '회원 정보 수정',
                'mode' => 'edit',
                'member' => $memberArray,
                'fieldDefinitions' => $fieldDefinitions,
                'fieldValues' => $fieldValues,
                'levelOptions' => $levelOptions,
                'statusOptions' => $statusOptions,
                'adminIsSuper' => $adminIsSuper,
                'recentPointLogs' => $recentPointLogs,
                'pluginExtras' => $enrichEvent->getExtras(),
                'pluginSections' => $formEvent->getSectionsSorted(),
                'pluginScripts' => $formEvent->getScriptsSorted(),
            ]);
    }

    /**
     * 회원 정보 수정 처리
     *
     * POST /admin/member/update/{id}
     */
    public function update(array $params, Context $context): JsonResponse
    {
        // autoResolve는 숫자 배열 ['123'], 명시적 라우트는 ['id' => '123']
        $memberId = (int) ($params['id'] ?? $params[0] ?? 0);
        $request = $context->getRequest();

        if (!$memberId) {
            return JsonResponse::error('회원 ID가 필요합니다.');
        }

        // 현재 로그인 관리자 정보
        $currentAdmin = $this->authService->user();

        // formData[필드명] 형식으로 전송된 데이터 정제
        $formData = $request->input('formData') ?? [];
        $normalizedData = FormHelper::normalizeFormData($formData, $this->getFormSchema());

        $adminIsSuper = (bool) ($currentAdmin['is_super'] ?? false);

        $data = [
            'password' => $normalizedData['password'] ?? '',
            'nickname' => isset($normalizedData['nickname']) ? trim($normalizedData['nickname']) : null,
            'level_value' => $normalizedData['level_value'] ?? null,
            'status' => $normalizedData['status'] ?? null,
            'can_create_site' => $normalizedData['can_create_site'] ?? null,
            'fields' => $request->input('fields') ?? [],  // 다차원 배열은 별도 처리
            // 관리자 권한 검증용
            'admin_id' => $currentAdmin['member_id'] ?? 0,
            'admin_is_super' => $adminIsSuper,
            'admin_level_value' => (int) ($currentAdmin['level_value'] ?? 0),
            'admin_level_type' => $currentAdmin['level_type'] ?? null,
            'admin_domain_group' => $currentAdmin['domain_group'] ?? null,
            'admin_domain_id' => $context->getDomainId() ?? 1,
        ];

        // MemberAdminService의 update 호출
        $result = $this->memberAdminService->update($memberId, $data);

        if ($result->isSuccess()) {
            return JsonResponse::success($result->getData(), $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 회원 삭제
     *
     * DELETE /admin/member/delete/{id}
     */
    public function delete(array $params, Context $context): JsonResponse
    {
        // autoResolve는 숫자 배열 ['123'], 명시적 라우트는 ['id' => '123']
        $memberId = (int) ($params['id'] ?? $params[0] ?? 0);

        if (!$memberId) {
            return JsonResponse::error('회원 ID가 필요합니다.');
        }

        // 현재 로그인 관리자 정보 (도메인 경계 검증용)
        $currentAdmin = $this->authService->user();
        $adminContext = [
            'admin_domain_id' => $context->getDomainId() ?? 1,
            'admin_is_super' => $currentAdmin['is_super'] ?? false,
            'admin_domain_group' => $currentAdmin['domain_group'] ?? null,
        ];

        $result = $this->memberAdminService->delete($memberId, $adminContext);

        if ($result->isSuccess()) {
            return JsonResponse::success($result->getData(), $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 중복 체크 API
     *
     * POST /admin/member/check-duplicate
     *
     * Request: { field_name: 'user_id'|'email'|..., value: string, member_id?: int }
     * Response: { result: 'success', duplicate: bool, message: string }
     */
    public function checkDuplicate(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        // JSON 또는 Form 데이터 모두 지원
        $fieldName = $request->input('field_name') ?? $request->json('field_name') ?? '';
        $value = $request->input('value') ?? $request->json('value') ?? '';
        $memberIdRaw = $request->input('member_id') ?? $request->json('member_id');
        $excludeMemberId = $memberIdRaw ? (int) $memberIdRaw : null;

        if (empty($fieldName) || empty($value)) {
            return JsonResponse::error('필드명과 값을 입력해주세요.');
        }

        $result = $this->memberService->checkDuplicate(
            $domainId,
            $fieldName,
            $value,
            $excludeMemberId
        );

        // Result::failure = 중복 있음, Result::success = 사용 가능
        return JsonResponse::success([
            'duplicate' => $result->isFailure(),
        ], $result->getMessage());
    }

    /**
     * 목록 일괄 수정
     *
     * POST /admin/member/listModify
     */
    public function listModify(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $checkedIds = $request->input('chk') ?? [];

        if (empty($checkedIds)) {
            return JsonResponse::error('수정할 회원을 선택해주세요.');
        }

        $currentAdmin = $this->authService->user();
        $levelValueData = $request->input('level_value') ?? [];
        $statusData = $request->input('status') ?? [];

        $updated = 0;
        $failed = 0;

        foreach ($checkedIds as $memberId) {
            $memberId = (int) $memberId;

            $data = [
                'admin_id' => $currentAdmin['member_id'] ?? 0,
                'admin_is_super' => $currentAdmin['is_super'] ?? false,
                'admin_level_type' => $currentAdmin['level_type'] ?? null,
                'admin_domain_group' => $currentAdmin['domain_group'] ?? null,
                'admin_domain_id' => $context->getDomainId() ?? 1,
            ];

            if (isset($levelValueData[$memberId])) {
                $data['level_value'] = (int) $levelValueData[$memberId];
            }

            if (isset($statusData[$memberId])) {
                $data['status'] = $statusData[$memberId];
            }

            $result = $this->memberAdminService->update($memberId, $data);
            if ($result->isSuccess()) {
                $updated++;
            } else {
                $failed++;
            }
        }

        if ($updated > 0) {
            $message = "{$updated}명의 회원 정보가 수정되었습니다.";
            if ($failed > 0) {
                $message .= " ({$failed}명 실패)";
            }
            return JsonResponse::success(['updated' => $updated, 'failed' => $failed], $message);
        }

        return JsonResponse::error('수정된 항목이 없습니다.');
    }

    /**
     * 목록 일괄 삭제
     *
     * POST /admin/member/listDelete
     */
    public function listDelete(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $checkedIds = $request->input('chk') ?? [];

        if (empty($checkedIds)) {
            return JsonResponse::error('삭제할 회원을 선택해주세요.');
        }

        $currentAdmin = $this->authService->user();
        $adminContext = [
            'admin_domain_id' => $context->getDomainId() ?? 1,
            'admin_is_super' => $currentAdmin['is_super'] ?? false,
            'admin_domain_group' => $currentAdmin['domain_group'] ?? null,
        ];

        $deleted = 0;
        $failed = 0;

        foreach ($checkedIds as $memberId) {
            $memberId = (int) $memberId;

            $result = $this->memberAdminService->delete($memberId, $adminContext);
            if ($result->isSuccess()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted > 0) {
            $message = "{$deleted}명의 회원이 삭제되었습니다.";
            if ($failed > 0) {
                $message .= " ({$failed}명 실패)";
            }
            return JsonResponse::success(['updated' => $deleted, 'failed' => $failed], $message);
        }

        return JsonResponse::error('삭제된 항목이 없습니다.');
    }

    /**
     * 상태 옵션 목록
     */
    private function getStatusOptions(): array
    {
        return [
            'active' => '활성',
            'inactive' => '비활성',
            'dormant' => '휴면',
            'blocked' => '차단',
        ];
    }

    /**
     * 회원 검색 API
     *
     * POST /admin/member/search
     *
     * 관리자 선택, 회원 검색 등에서 사용
     */
    public function search(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $keyword = trim($request->json('keyword', ''));
        $limit = min((int) $request->json('limit', 10), 50);

        if (strlen($keyword) < 2) {
            return JsonResponse::error('검색어는 2글자 이상 입력해주세요.');
        }

        // 아이디 또는 이름으로 검색
        $result = $this->memberAdminService->searchMembers($domainId, $keyword, $limit);

        if ($result->isSuccess()) {
            return JsonResponse::success([
                'members' => $result->get('members', []),
            ]);
        }

        return JsonResponse::error($result->getMessage() ?: '검색 중 오류가 발생했습니다.');
    }

    /**
     * 회원 폼 데이터 스키마
     *
     * FormHelper::normalizeFormData()에서 사용
     */
    private function getFormSchema(): array
    {
        return [
            'numeric' => ['member_id', 'level_value'],
            'bool' => ['can_create_site'],
            'required_string' => ['user_id'],
            'enum' => [
                'status' => [
                    'values' => ['active', 'inactive', 'dormant', 'blocked'],
                    'default' => 'active',
                ],
            ],
        ];
    }
}
