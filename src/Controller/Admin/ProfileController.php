<?php

namespace Mublo\Controller\Admin;

use Mublo\Core\Context\Context;
use Mublo\Core\Http\Request;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\ViewResponse;
use Mublo\Service\Auth\AuthService;
use Mublo\Service\Member\MemberService;

/**
 * Admin ProfileController
 *
 * 관리자 본인 정보 수정
 *
 * 라우트 (autoResolve):
 *   GET  /admin/profile         → index()
 *   POST /admin/profile/update  → update()
 */
class ProfileController
{
    public function __construct(
        private AuthService $authService,
        private MemberService $memberService,
    ) {}

    /**
     * GET /admin/profile
     */
    public function index(Request $request, Context $context): ViewResponse
    {
        $user = $this->authService->user();

        return ViewResponse::view('Profile/Index')
            ->withData([
                'pageTitle' => '내 정보',
                'user' => $user,
            ]);
    }

    /**
     * POST /admin/profile/update
     */
    public function update(Request $request, Context $context): JsonResponse
    {
        $user = $this->authService->user();
        $formData = $request->input('formData') ?? [];

        $data = [];

        $nickname = trim($formData['nickname'] ?? '');
        if ($nickname !== '') {
            $data['nickname'] = $nickname;
        }

        $newPassword = $formData['new_password'] ?? '';
        if ($newPassword !== '') {
            $confirmPassword = $formData['new_password_confirm'] ?? '';
            if ($newPassword !== $confirmPassword) {
                return JsonResponse::error('새 비밀번호가 일치하지 않습니다.');
            }
            $data['password'] = $newPassword;
        }

        if (empty($data)) {
            return JsonResponse::error('변경할 항목이 없습니다.');
        }

        $result = $this->memberService->update($user['member_id'], $data);

        return $result->isSuccess()
            ? JsonResponse::success(null, $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }
}
