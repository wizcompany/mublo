<?php

namespace Mublo\Controller\Front;

use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Http\Request;
use Mublo\Service\Mypage\MypageMenuBuilder;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\RedirectResponse;
use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Session\SessionInterface;
use Mublo\Service\CustomField\CustomFieldFileHandler;
use Mublo\Infrastructure\Storage\SecureFileService;
use Mublo\Infrastructure\Storage\UploadedFile;
use Mublo\Core\Event\Mypage\MypageContentQueryEvent;
use Mublo\Service\Auth\AuthService;
use Mublo\Service\Balance\BalanceManager;
use Mublo\Service\Member\MemberFieldService;
use Mublo\Service\Member\MemberService;

/**
 * Front MypageController
 *
 * 마이페이지 전담 컨트롤러.
 *
 * 라우트:
 *   GET  /mypage                → index()       (→ /mypage/profile 리다이렉트)
 *   GET  /mypage/profile        → profile()
 *   POST /mypage/profile        → updateProfile()
 *   GET  /mypage/balance        → balance()      (포인트 내역)
 *   GET  /mypage/articles       → articles()     (내가 쓴 글)
 *   GET  /mypage/comments       → comments()     (내가 쓴 댓글)
 *   GET  /mypage/withdraw       → withdraw()
 *   POST /mypage/withdraw       → withdraw()
 */
class MypageController
{
    private ?CustomFieldFileHandler $fileHandler;

    public function __construct(
        private MemberService          $memberService,
        private AuthService            $authService,
        private BalanceManager         $balanceManager,
        private EventDispatcher        $eventDispatcher,
        private SessionInterface       $session,
        private MypageMenuBuilder      $menuBuilder,
        private ?MemberFieldService    $fieldService = null,
        private ?SecureFileService      $secureFileService = null,
    ) {
        $this->fileHandler = $secureFileService ? new CustomFieldFileHandler($secureFileService) : null;
    }

    // =========================================================================
    // 진입점
    // =========================================================================

    /**
     * GET /mypage
     */
    public function index(Request $request, Context $context): RedirectResponse
    {
        return RedirectResponse::to('/mypage/profile');
    }

    // =========================================================================
    // 회원정보수정
    // =========================================================================

    /**
     * GET /mypage/profile
     */
    public function profile(Request $request, Context $context): ViewResponse
    {
        $user     = $this->authService->user();
        $domainId = $context->getDomainId();

        $fieldDefinitions = $this->memberService->getFieldDefinitions($domainId);
        $fieldValues      = $this->memberService->getFieldValues($user['member_id']);

        $fieldValuesMap = [];
        foreach ($fieldValues as $fv) {
            $fieldValuesMap[$fv['field_id']] = $fv['field_value'];
        }

        return $this->mypageView('Profile', 'profile', $context, [
            'user'             => $user,
            'fieldDefinitions' => $fieldDefinitions,
            'fieldValues'      => $fieldValuesMap,
        ]);
    }

    /**
     * POST /mypage/profile
     */
    public function updateProfile(Request $request, Context $context): JsonResponse|RedirectResponse
    {
        $user = $this->authService->user();

        $data = [
            'nickname' => $request->post('nickname', ''),
            'fields'   => $request->post('fields', []),
        ];

        $newPassword = $request->post('new_password', '');
        if (!empty($newPassword)) {
            $newPasswordConfirm = $request->post('new_password_confirm', '');
            if ($newPassword !== $newPasswordConfirm) {
                if ($request->isAjax()) {
                    return JsonResponse::error('새 비밀번호가 일치하지 않습니다.');
                }
                return RedirectResponse::back();
            }
            $data['password'] = $newPassword;
        }

        $result = $this->memberService->update($user['member_id'], $data);

        if ($request->isAjax()) {
            return $result->isSuccess()
                ? JsonResponse::success(null, $result->getMessage())
                : JsonResponse::error($result->getMessage());
        }

        return RedirectResponse::back();
    }

    // =========================================================================
    // 포인트 내역
    // =========================================================================

    /**
     * GET /mypage/balance
     */
    public function balance(Request $request, Context $context): ViewResponse
    {
        $user = $this->authService->user();
        $page = max(1, (int) $request->query('page', 1));

        $history = $this->balanceManager->getHistory(
            memberId: $user['member_id'],
            filters: [],
            page: $page,
            perPage: 20
        );

        // BalanceManager::getHistory()의 pagination 키를 표준 키로 정규화
        $raw = $history['pagination'];
        $pagination = [
            'totalItems'  => $raw['total'],
            'perPage'     => $raw['per_page'],
            'currentPage' => $raw['current_page'],
            'totalPages'  => $raw['total_pages'],
        ];

        return $this->mypageView('Balance', 'balance', $context, [
            'logs'       => $history['items'],
            'pagination' => $pagination,
            'balance'    => $this->balanceManager->getBalance($user['member_id']),
        ]);
    }

    // =========================================================================
    // 내가 쓴 글
    // =========================================================================

    /**
     * GET /mypage/articles
     */
    public function articles(Request $request, Context $context): ViewResponse
    {
        $user     = $this->authService->user();
        $domainId = $context->getDomainId();
        $page     = max(1, (int) $request->query('page', 1));

        $event = $this->eventDispatcher->dispatch(new MypageContentQueryEvent(
            contentType: 'articles',
            memberId: $user['member_id'],
            domainId: $domainId,
            page: $page,
            perPage: 15,
        ));

        return $this->mypageView('Articles', 'articles', $context, [
            'articles'   => $event->getItems(),
            'pagination' => $event->getPagination(),
        ]);
    }

    // =========================================================================
    // 내가 쓴 댓글
    // =========================================================================

    /**
     * GET /mypage/comments
     */
    public function comments(Request $request, Context $context): ViewResponse
    {
        $user     = $this->authService->user();
        $domainId = $context->getDomainId();
        $page     = max(1, (int) $request->query('page', 1));

        $event = $this->eventDispatcher->dispatch(new MypageContentQueryEvent(
            contentType: 'comments',
            memberId: $user['member_id'],
            domainId: $domainId,
            page: $page,
            perPage: 15,
        ));

        return $this->mypageView('Comments', 'comments', $context, [
            'comments'   => $event->getItems(),
            'pagination' => $event->getPagination(),
        ]);
    }

    // =========================================================================
    // 회원 탈퇴
    // =========================================================================

    /**
     * GET /mypage/withdraw  — 탈퇴 폼
     * POST /mypage/withdraw — 탈퇴 처리
     */
    public function withdraw(Request $request, Context $context): ViewResponse|JsonResponse|RedirectResponse
    {
        if ($request->getMethod() === 'POST') {
            return $this->processWithdraw($request, $context);
        }

        return $this->mypageView('Withdraw', 'withdraw', $context);
    }

    private function processWithdraw(Request $request, Context $context): JsonResponse|RedirectResponse
    {
        $user     = $this->authService->user();
        $formData = $request->input('formData') ?? [];
        $password = $formData['password'] ?? '';
        $reason   = trim($formData['reason'] ?? '');

        if (empty($password)) {
            if ($request->isAjax()) {
                return JsonResponse::error('비밀번호를 입력해주세요.');
            }
            return RedirectResponse::back();
        }

        $result = $this->memberService->withdraw($user['member_id'], $password, $reason);

        if ($request->isAjax()) {
            if ($result->isSuccess()) {
                $this->authService->logout();
                return JsonResponse::success(['redirect' => '/'], $result->getMessage());
            }
            return JsonResponse::error($result->getMessage());
        }

        if ($result->isSuccess()) {
            $this->authService->logout();
            return RedirectResponse::to('/');
        }

        return RedirectResponse::back();
    }

    // =========================================================================
    // 파일 업로드 (프로필 추가 필드용)
    // =========================================================================

    /**
     * POST /member/upload-field-file
     */
    public function uploadFieldFile(Request $request, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId();
        $fieldId  = (int) ($request->post('field_id') ?? 0);

        if ($fieldId <= 0 || !$this->fieldService || !$this->fileHandler) {
            return JsonResponse::error('파일 업로드를 처리할 수 없습니다.');
        }

        $field = $this->fieldService->getField($fieldId);
        if (!$field || $field['field_type'] !== 'file') {
            return JsonResponse::error('유효하지 않은 필드입니다.');
        }

        $file = UploadedFile::fromGlobal('file');
        if (!$file || !$file->isValid()) {
            return JsonResponse::error($file ? $file->getErrorMessage() : '파일이 업로드되지 않았습니다.');
        }

        $config = json_decode($field['field_config'] ?? '{}', true) ?: [];
        $result = $this->fileHandler->uploadTemp($file, $domainId, $config);

        if (!$result->isSuccess()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success(
            $this->fileHandler->buildTempResponse($result),
            '파일이 업로드되었습니다.'
        );
    }

    // =========================================================================
    // 공통 헬퍼
    // =========================================================================

    /**
     * 사이드바 메뉴 목록 빌드 (MypageMenuBuilder 위임)
     */
    private function buildMenus(string $section, Context $context): array
    {
        return $this->menuBuilder->buildMenus($section, $context->getDomainId());
    }

    /**
     * Mypage ViewResponse 생성 헬퍼
     * ($user는 _layout.php의 사이드바 사용자 정보용으로 항상 포함)
     */
    private function mypageView(string $view, string $section, Context $context, array $data = []): ViewResponse
    {
        return ViewResponse::view("mypage/{$view}")
            ->withData(array_merge([
                'user'           => $this->authService->user(),
                'mypageMenus'    => $this->buildMenus($section, $context),
                'currentSection' => $section,
            ], $data));
    }
}
