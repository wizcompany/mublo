<?php

namespace Mublo\Service\Mypage;

use Mublo\Service\Menu\MenuService;

/**
 * MypageMenuBuilder
 *
 * 마이페이지 사이드바 메뉴를 빌드하는 서비스.
 *
 * MypageController뿐 아니라 Package/Plugin 컨트롤러에서도
 * 마이페이지 레이아웃을 사용할 수 있도록 Core 서비스로 분리.
 *
 * 메뉴 소스: menu_items.show_in_mypage=1 (관리자 설정 반영)
 * Plugin/Package 메뉴 추가: 패키지 설치 시 menu_items에 INSERT (이벤트 방식 불사용)
 */
class MypageMenuBuilder
{
    public function __construct(private MenuService $menuService) {}

    /**
     * 사이드바 메뉴 목록 빌드
     *
     * @param string $section  현재 활성 섹션 식별자
     * @param int    $domainId 도메인 ID
     * @return array<int, array{label: string, url: string, section: string, order: int, active: bool}>
     */
    public function buildMenus(string $section, int $domainId): array
    {
        $dbRows = $this->menuService->getMypageMenus($domainId);

        $menus = array_map(fn($row) => [
            'label'     => $row['label'],
            'url'       => $row['url'],
            'section'   => basename($row['url']),
            'order'     => (int) $row['mypage_order'],
            'is_system' => (bool) ($row['is_system'] ?? false),
        ], $dbRows);

        foreach ($menus as &$menu) {
            $menu['active'] = ($menu['section'] === $section);
        }

        return $menus;
    }
}
