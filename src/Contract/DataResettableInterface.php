<?php

namespace Mublo\Contract;

use Mublo\Infrastructure\Database\Database;

/**
 * 데이터 초기화 계약
 *
 * Plugin/Package Provider가 이 인터페이스를 구현하면
 * 관리자 시스템 관리 → 데이터 초기화 항목에 자동 노출됩니다.
 */
interface DataResettableInterface
{
    /**
     * 초기화 가능한 카테고리 목록 반환
     *
     * @return array [['key' => 'board', 'label' => '게시판', 'description' => '게시글, 댓글 등 삭제', 'icon' => 'bi-clipboard']]
     */
    public function getResetCategories(): array;

    /**
     * 지정 카테고리 데이터 초기화 실행
     *
     * @param string $category 카테고리 키
     * @param int $domainId 도메인 ID
     * @param \Mublo\Infrastructure\Database\Database $db Database 인스턴스
     * @return array ['tables_cleared' => int, 'files_deleted' => int, 'details' => string]
     */
    public function reset(string $category, int $domainId, Database $db): array;
}
