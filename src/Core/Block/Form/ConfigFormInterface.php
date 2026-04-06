<?php
namespace Mublo\Core\Block\Form;

/**
 * ConfigFormInterface
 *
 * 블록 콘텐츠 설정 폼 인터페이스
 *
 * 관리자 페이지에서 콘텐츠 타입별 설정 폼을 렌더링할 때 사용
 */
interface ConfigFormInterface
{
    /**
     * 설정 폼 HTML 렌더링
     *
     * @param array $currentConfig 현재 설정 값
     * @return string 렌더링된 폼 HTML
     */
    public function render(array $currentConfig = []): string;

    /**
     * 아이템 선택 UI 렌더링 (선택)
     *
     * 게시판 선택, 배너 그룹 선택 등의 아이템 선택 UI
     *
     * @param array $selectedItems 현재 선택된 아이템
     * @param int $domainId 도메인 ID
     * @return string 렌더링된 선택 UI HTML
     */
    public function renderItemSelector(array $selectedItems = [], int $domainId = 0): string;
}
