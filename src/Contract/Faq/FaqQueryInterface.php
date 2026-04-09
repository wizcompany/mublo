<?php

namespace Mublo\Contract\Faq;

/**
 * FAQ 조회 계약 인터페이스
 *
 * FAQ Plugin이 구현하고, Package(Shop/Mshop 등)가 소비합니다.
 * ContractRegistry에 1:1 바인딩(bind/resolve)으로 등록됩니다.
 */
interface FaqQueryInterface
{
    /**
     * 활성 카테고리 목록 (sort_order 정렬)
     *
     * @return array [{category_id, category_name, category_slug, item_count}, ...]
     */
    public function getCategories(int $domainId): array;

    /**
     * 카테고리 slug 배열로 FAQ 항목 조회
     *
     * @return array [category_slug => [{faq_id, question, answer}, ...], ...]
     */
    public function getByCategorySlugs(int $domainId, array $slugs): array;

    /**
     * 전체 FAQ (카테고리별 그룹핑)
     *
     * @return array [{category_name, category_slug, items: [{faq_id, question, answer}, ...]}, ...]
     */
    public function getGroupedAll(int $domainId): array;

    /**
     * 페이지 처리된 FAQ 목록 (카테고리별 그룹핑)
     *
     * @return array{
     *   groups: array<array{category_name: string, category_slug: string, items: array}>,
     *   totalItems: int,
     *   perPage: int,
     *   currentPage: int,
     *   totalPages: int
     * }
     */
    public function getGroupedPaginated(int $domainId, int $page, int $perPage): array;
}
