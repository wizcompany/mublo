<?php
namespace Mublo\Service\Menu;

use Mublo\Core\Result\Result;
use Mublo\Entity\Menu\MenuItem;
use Mublo\Entity\Menu\MenuNode;
use Mublo\Repository\Menu\MenuItemRepository;
use Mublo\Repository\Menu\MenuTreeRepository;
use Mublo\Infrastructure\Cache\CacheInterface;
use Mublo\Infrastructure\Code\CodeGenerator;

/**
 * MenuService
 *
 * 메뉴 관리 서비스
 * - 메뉴 아이템 CRUD
 * - 메뉴 트리 구성
 * - 유틸리티/푸터 메뉴 관리
 */
class MenuService
{
    private MenuItemRepository $itemRepository;
    private MenuTreeRepository $treeRepository;
    private CodeGenerator $codeGenerator;
    private ?CacheInterface $cache;

    public function __construct(
        MenuItemRepository $itemRepository,
        MenuTreeRepository $treeRepository,
        CodeGenerator $codeGenerator,
        ?CacheInterface $cache = null
    ) {
        $this->itemRepository = $itemRepository;
        $this->treeRepository = $treeRepository;
        $this->codeGenerator = $codeGenerator;
        $this->cache = $cache;
    }

    // ========================================
    // 메뉴 아이템 관리
    // ========================================

    /**
     * 도메인별 메뉴 아이템 목록 조회
     */
    public function getItems(int $domainId, bool $activeOnly = false): array
    {
        return $this->itemRepository->findByDomain($domainId, $activeOnly);
    }

    /**
     * 검색/페이지네이션이 적용된 메뉴 아이템 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 항목 수
     * @param array $search 검색 조건 ['keyword' => '', 'field' => '']
     * @return array ['items' => [], 'pagination' => []]
     */
    public function getItemsPaginated(int $domainId, int $page = 1, int $perPage = 20, array $search = []): array
    {
        $result = $this->itemRepository->findPaginated($domainId, $page, $perPage, $search);

        $totalPages = (int) ceil($result['total'] / $perPage);

        return [
            'items' => $result['items'],
            'pagination' => [
                'currentPage' => $page,
                'perPage' => $perPage,
                'totalItems' => $result['total'],
                'totalPages' => $totalPages,
            ],
        ];
    }

    /**
     * 도메인별 고유 제공자 목록 조회
     *
     * 제공자 유형별로 그룹화된 배열 반환:
     * ['core' => [], 'plugin' => ['Mshop', 'AutoForm'], 'package' => ['Shop']]
     */
    public function getProviderOptions(int $domainId): array
    {
        $rows = $this->itemRepository->findDistinctProviders($domainId);

        $grouped = ['core' => [], 'plugin' => [], 'package' => []];
        foreach ($rows as $row) {
            $type = $row['provider_type'] ?? 'core';
            $name = $row['provider_name'] ?? '';
            if (isset($grouped[$type]) && $name !== '' && $name !== null) {
                $grouped[$type][] = $name;
            }
        }

        return $grouped;
    }

    /**
     * 검색 필드 옵션 반환
     */
    public function getSearchFields(): array
    {
        return [
            'label' => '메뉴명',
            'url' => 'URL',
            'menu_code' => '메뉴코드',
            'provider_name' => '제공자',
        ];
    }

    /**
     * 메뉴 아이템 단건 조회
     *
     * @param int $itemId 아이템 ID
     * @param int|null $domainId 도메인 ID (소유권 검증, null이면 검증 생략)
     */
    public function getItem(int $itemId, ?int $domainId = null): ?MenuItem
    {
        $item = $this->itemRepository->find($itemId);

        // 도메인 소유권 검증
        if ($item !== null && $domainId !== null && $item->getDomainId() !== $domainId) {
            return null;
        }

        return $item;
    }

    /**
     * 메뉴 아이템 생성
     */
    public function createItem(int $domainId, array $data): Result
    {
        // 필수값 검증
        if (empty($data['label'])) {
            return Result::failure('메뉴명은 필수입니다.');
        }

        // 메뉴 코드 생성 (unique_codes 테이블에서 관리)
        $menuCode = $this->codeGenerator->generate('menu', 8);

        $insertData = [
            'domain_id' => $domainId,
            'menu_code' => $menuCode,
            'label' => $data['label'],
            'url' => $data['url'] ?? null,
            'icon' => $data['icon'] ?? null,
            'target' => $data['target'] ?? '_self',
            'visibility' => $data['visibility'] ?? 'all',
            'pair_code' => $data['pair_code'] ?? null,
            'min_level' => (int) ($data['min_level'] ?? 0),
            'required_permission' => $data['required_permission'] ?? null,
            'show_on_pc' => (int) ($data['show_on_pc'] ?? 1),
            'show_on_mobile' => (int) ($data['show_on_mobile'] ?? 1),
            'show_in_utility' => (int) ($data['show_in_utility'] ?? 0),
            'show_in_footer' => (int) ($data['show_in_footer'] ?? 0),
            'show_in_mypage' => (int) ($data['show_in_mypage'] ?? 0),
            'utility_order' => (int) ($data['utility_order'] ?? 0),
            'footer_order' => (int) ($data['footer_order'] ?? 0),
            'mypage_order' => (int) ($data['mypage_order'] ?? 0),
            'is_system' => (int) ($data['is_system'] ?? 0),
            'provider_type' => $data['provider_type'] ?? 'core',
            'provider_name' => $data['provider_name'] ?? null,
            'is_active' => (int) ($data['is_active'] ?? 1),
        ];

        $itemId = $this->itemRepository->create($insertData);

        if (!$itemId) {
            return Result::failure('메뉴 생성에 실패했습니다.');
        }

        $this->invalidateUrlMapCache($domainId);

        return Result::success('메뉴가 생성되었습니다.', [
            'item_id' => $itemId,
            'menu_code' => $menuCode,
        ]);
    }

    /**
     * 메뉴 아이템 수정
     *
     * @param int $itemId 아이템 ID
     * @param array $data 수정 데이터
     * @param int|null $domainId 도메인 ID (소유권 검증, null이면 검증 생략)
     */
    public function updateItem(int $itemId, array $data, ?int $domainId = null): Result
    {
        $item = $this->getItem($itemId, $domainId);
        if (!$item) {
            return Result::failure('메뉴를 찾을 수 없습니다.');
        }

        $updateData = [];
        $allowedFields = [
            'label', 'url', 'icon', 'target', 'visibility', 'pair_code',
            'min_level', 'required_permission', 'show_on_pc', 'show_on_mobile',
            'show_in_utility', 'show_in_footer', 'show_in_mypage',
            'utility_order', 'footer_order', 'mypage_order', 'is_system',
            'is_active',
        ];

        $intFields = [
            'min_level', 'show_on_pc', 'show_on_mobile',
            'show_in_utility', 'show_in_footer', 'show_in_mypage',
            'utility_order', 'footer_order', 'mypage_order', 'is_system',
            'is_active',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = in_array($field, $intFields) ? (int) $data[$field] : $data[$field];
            }
        }

        if (empty($updateData)) {
            return Result::failure('수정할 데이터가 없습니다.');
        }

        $this->itemRepository->update($itemId, $updateData);

        // 트리의 path_name 업데이트 (라벨이 변경된 경우)
        if (isset($data['label']) && $data['label'] !== $item->getLabel()) {
            $this->updateTreePathNames($item->getDomainId(), $item->getMenuCode(), $data['label']);
        }

        $this->invalidateUrlMapCache($item->getDomainId());

        return Result::success('메뉴가 수정되었습니다.');
    }

    /**
     * 메뉴 아이템 삭제
     *
     * @param int $itemId 아이템 ID
     * @param int|null $domainId 도메인 ID (소유권 검증, null이면 검증 생략)
     */
    public function deleteItem(int $itemId, ?int $domainId = null): Result
    {
        $item = $this->getItem($itemId, $domainId);
        if (!$item) {
            return Result::failure('메뉴를 찾을 수 없습니다.');
        }

        $domainId = $item->getDomainId();

        try {
            $this->itemRepository->getDb()->transaction(function () use ($item, $itemId) {
                // 트리에서도 삭제
                $this->treeRepository->deleteByMenuCode($item->getDomainId(), $item->getMenuCode());

                // unique_codes에서 코드 삭제
                $this->codeGenerator->delete('menu', $item->getMenuCode());

                // 아이템 삭제
                $this->itemRepository->delete($itemId);
            });

            $this->invalidateUrlMapCache($domainId);

            return Result::success('메뉴가 삭제되었습니다.');
        } catch (\Throwable $e) {
            return Result::failure('메뉴 삭제에 실패했습니다.');
        }
    }

    /**
     * 메뉴 아이템 일괄 수정
     *
     * @param array $itemIds 수정할 아이템 ID 목록
     * @param array $fieldData 필드별 데이터 ['visibility' => [item_id => value], ...]
     * @return Result
     */
    public function bulkUpdateItems(array $itemIds, array $fieldData): Result
    {
        if (empty($itemIds)) {
            return Result::failure('수정할 항목이 없습니다.');
        }

        $allowedFields = ['min_level', 'show_on_pc', 'show_on_mobile', 'is_active'];

        try {
            $updatedCount = $this->itemRepository->getDb()->transaction(function () use ($itemIds, $fieldData, $allowedFields) {
                $count = 0;

                foreach ($itemIds as $itemId) {
                    $itemId = (int) $itemId;
                    $updateData = [];

                    foreach ($allowedFields as $field) {
                        if (isset($fieldData[$field][$itemId])) {
                            $value = $fieldData[$field][$itemId];
                            // 모든 필드가 정수형
                            $updateData[$field] = (int) $value;
                        }
                    }

                    if (!empty($updateData)) {
                        $this->itemRepository->update($itemId, $updateData);
                        $count++;
                    }
                }

                return $count;
            });

            if ($updatedCount === 0) {
                return Result::failure('수정된 항목이 없습니다.');
            }

            // 첫 번째 아이템의 도메인으로 캐시 무효화 (동일 도메인 내 일괄 수정)
            $firstItem = $this->getItem((int) $itemIds[0]);
            if ($firstItem) {
                $this->invalidateUrlMapCache($firstItem->getDomainId());
            }

            return Result::success("{$updatedCount}개 항목이 수정되었습니다.", [
                'updated_count' => $updatedCount,
            ]);
        } catch (\Throwable $e) {
            return Result::failure('일괄 수정에 실패했습니다.');
        }
    }

    // ========================================
    // 메뉴 트리 관리
    // ========================================

    /**
     * 트리 구조 조회 (메뉴 아이템 정보 포함)
     */
    public function getTree(int $domainId, bool $activeOnly = true): array
    {
        return $this->treeRepository->findTreeWithItems($domainId, $activeOnly);
    }

    /**
     * 트리 구조를 계층형으로 변환
     */
    public function getTreeHierarchy(int $domainId, bool $activeOnly = true): array
    {
        $flatTree = $this->getTree($domainId, $activeOnly);
        return $this->buildHierarchy($flatTree);
    }

    /**
     * 평면 트리를 계층형으로 변환
     */
    private function buildHierarchy(array $flatTree): array
    {
        $hierarchy = [];
        $map = [];

        // 1차: path_code를 키로 매핑
        foreach ($flatTree as $node) {
            $node['children'] = [];
            $map[$node['path_code']] = $node;
        }

        // 2차: 부모-자식 연결
        foreach ($map as $pathCode => $node) {
            if (empty($node['parent_code'])) {
                $hierarchy[] = &$map[$pathCode];
            } else {
                if (isset($map[$node['parent_code']])) {
                    $map[$node['parent_code']]['children'][] = &$map[$pathCode];
                }
            }
        }

        return $hierarchy;
    }

    /**
     * 트리에 노드 추가
     */
    public function addToTree(int $domainId, string $menuCode, ?string $parentCode = null): Result
    {
        // 메뉴 아이템 존재 확인
        $item = $this->itemRepository->findByMenuCode($domainId, $menuCode);
        if (!$item) {
            return Result::failure('메뉴 아이템을 찾을 수 없습니다.');
        }

        // 부모 정보 확인
        $depth = 1;
        $pathCode = $menuCode;
        $pathName = $item['label'];

        if ($parentCode !== null) {
            $parent = $this->treeRepository->findByPathCode($domainId, $parentCode);
            if (!$parent) {
                return Result::failure('부모 메뉴를 찾을 수 없습니다.');
            }
            $depth = $parent['depth'] + 1;
            $pathCode = $parentCode . '>' . $menuCode;
            $pathName = $parent['path_name'] . '>' . $item['label'];
        }

        // 정렬 순서
        $sortOrder = $this->treeRepository->getMaxSortOrder($domainId, $parentCode) + 1;

        $nodeData = [
            'domain_id' => $domainId,
            'menu_code' => $menuCode,
            'path_code' => $pathCode,
            'path_name' => $pathName,
            'parent_code' => $parentCode,
            'depth' => $depth,
            'sort_order' => $sortOrder,
        ];

        $nodeId = $this->treeRepository->create($nodeData);

        if (!$nodeId) {
            return Result::failure('트리 추가에 실패했습니다.');
        }

        return Result::success('메뉴가 트리에 추가되었습니다.', [
            'node_id' => $nodeId,
            'path_code' => $pathCode,
        ]);
    }

    /**
     * 트리에서 노드 제거
     */
    public function removeFromTree(int $nodeId): Result
    {
        $node = $this->treeRepository->find($nodeId);
        if (!$node) {
            return Result::failure('노드를 찾을 수 없습니다.');
        }

        // 자식 노드도 함께 삭제
        $this->treeRepository->deleteByPathPrefix($node->getDomainId(), $node->getPathCode());

        return Result::success('메뉴가 트리에서 제거되었습니다.');
    }

    /**
     * 트리 전체 저장 (재구성)
     */
    public function saveTree(int $domainId, array $treeData): Result
    {
        try {
            $this->treeRepository->getDb()->transaction(function () use ($domainId, $treeData) {
                // 기존 트리 삭제
                $this->treeRepository->deleteByDomain($domainId);

                // 새 트리 구성
                $this->insertTreeNodes($domainId, $treeData, null, 1);
            });

            return Result::success('메뉴 트리가 저장되었습니다.');
        } catch (\Throwable $e) {
            return Result::failure('메뉴 트리 저장에 실패했습니다.');
        }
    }

    /**
     * 트리 노드 재귀 삽입
     */
    private function insertTreeNodes(int $domainId, array $nodes, ?string $parentCode, int $depth): void
    {
        $sortOrder = 1;

        foreach ($nodes as $node) {
            $menuCode = $node['menu_code'];
            $item = $this->itemRepository->findByMenuCode($domainId, $menuCode);

            if (!$item) {
                continue;
            }

            $pathCode = $parentCode ? $parentCode . '>' . $menuCode : $menuCode;

            // 부모의 path_name 조회
            $pathName = $item['label'];
            if ($parentCode !== null) {
                $parentNode = $this->treeRepository->findByPathCode($domainId, $parentCode);
                if ($parentNode) {
                    $pathName = $parentNode['path_name'] . '>' . $item['label'];
                }
            }

            $nodeData = [
                'domain_id' => $domainId,
                'menu_code' => $menuCode,
                'path_code' => $pathCode,
                'path_name' => $pathName,
                'parent_code' => $parentCode,
                'depth' => $depth,
                'sort_order' => $sortOrder,
            ];

            $this->treeRepository->create($nodeData);

            // 자식 노드 처리
            if (!empty($node['children'])) {
                $this->insertTreeNodes($domainId, $node['children'], $pathCode, $depth + 1);
            }

            $sortOrder++;
        }
    }

    /**
     * 트리 path_name 업데이트 (메뉴 라벨 변경 시)
     */
    private function updateTreePathNames(int $domainId, string $menuCode, string $newLabel): void
    {
        $nodes = $this->treeRepository->findByMenuCode($domainId, $menuCode);

        foreach ($nodes as $node) {
            // 해당 노드의 path_name에서 이 메뉴의 라벨만 변경
            $pathParts = explode('>', $node['path_name']);
            $codeParts = explode('>', $node['path_code']);

            $index = array_search($menuCode, $codeParts);
            if ($index !== false && isset($pathParts[$index])) {
                $pathParts[$index] = $newLabel;
                $newPathName = implode('>', $pathParts);

                $this->treeRepository->update($node['node_id'], ['path_name' => $newPathName]);
            }

            // 자식 노드들의 path_name도 업데이트
            $this->updateChildrenPathNames($domainId, $node['path_code'], $menuCode, $newLabel);
        }
    }

    /**
     * 자식 노드들의 path_name 업데이트
     */
    private function updateChildrenPathNames(int $domainId, string $parentPathCode, string $menuCode, string $newLabel): void
    {
        $children = $this->treeRepository->findChildren($domainId, $parentPathCode);

        foreach ($children as $child) {
            $pathParts = explode('>', $child['path_name']);
            $codeParts = explode('>', $child['path_code']);

            $index = array_search($menuCode, $codeParts);
            if ($index !== false && isset($pathParts[$index])) {
                $pathParts[$index] = $newLabel;
                $newPathName = implode('>', $pathParts);

                $this->treeRepository->update($child['node_id'], ['path_name' => $newPathName]);
            }

            // 재귀 호출
            $this->updateChildrenPathNames($domainId, $child['path_code'], $menuCode, $newLabel);
        }
    }

    // ========================================
    // pair_code 확장
    // ========================================

    /**
     * 선택된 item_ids에서 pair_code 짝을 찾아 자동 추가
     *
     * 같은 pair_code를 가진 다른 아이템(활성 상태)을 자동으로 포함시킨다.
     * 예: 로그인(guest)만 선택 → 로그아웃(member)도 자동 추가
     */
    private function expandWithPairedItems(int $domainId, array $itemIds): array
    {
        if (empty($itemIds)) {
            return $itemIds;
        }

        $pairCodes = $this->itemRepository->findPairCodesByIds($domainId, $itemIds);

        if (empty($pairCodes)) {
            return $itemIds;
        }

        $pairedIds = $this->itemRepository->findPairedItemIds($domainId, $pairCodes, $itemIds);

        return array_merge($itemIds, $pairedIds);
    }

    // ========================================
    // 유틸리티/푸터 메뉴 관리
    // ========================================

    /**
     * 유틸리티 메뉴 조회
     */
    public function getUtilityMenus(int $domainId): array
    {
        return $this->itemRepository->findUtilityMenus($domainId);
    }

    /**
     * 푸터 메뉴 조회
     */
    public function getFooterMenus(int $domainId): array
    {
        return $this->itemRepository->findFooterMenus($domainId);
    }

    /**
     * 유틸리티 메뉴 저장 (포함 목록 + 순서 전체 교체)
     */
    public function saveUtilityOrder(int $domainId, array $itemIds): Result
    {
        $itemIds = $this->expandWithPairedItems($domainId, $itemIds);

        try {
            $this->itemRepository->getDb()->transaction(function () use ($domainId, $itemIds) {
                $this->itemRepository->resetUtilityFlags($domainId);
                $order = 1;
                foreach ($itemIds as $itemId) {
                    $this->itemRepository->setUtilityActive((int) $itemId, $domainId, $order++);
                }
            });

            return Result::success('유틸리티 메뉴가 저장되었습니다.');
        } catch (\Throwable $e) {
            return Result::failure('유틸리티 메뉴 저장에 실패했습니다.');
        }
    }

    /**
     * 푸터 메뉴 저장 (포함 목록 + 순서 전체 교체)
     */
    public function saveFooterOrder(int $domainId, array $itemIds): Result
    {
        $itemIds = $this->expandWithPairedItems($domainId, $itemIds);

        try {
            $this->itemRepository->getDb()->transaction(function () use ($domainId, $itemIds) {
                $this->itemRepository->resetFooterFlags($domainId);
                $order = 1;
                foreach ($itemIds as $itemId) {
                    $this->itemRepository->setFooterActive((int) $itemId, $domainId, $order++);
                }
            });

            return Result::success('푸터 메뉴가 저장되었습니다.');
        } catch (\Throwable $e) {
            return Result::failure('푸터 메뉴 저장에 실패했습니다.');
        }
    }

    /**
     * 마이페이지 메뉴 조회
     */
    public function getMypageMenus(int $domainId): array
    {
        return $this->itemRepository->findMypageMenus($domainId);
    }

    /**
     * 마이페이지 메뉴 저장 (포함 목록 + 순서 전체 교체, 시스템 메뉴 제외)
     */
    public function saveMypageOrder(int $domainId, array $itemIds): Result
    {
        $itemIds = $this->expandWithPairedItems($domainId, $itemIds);

        try {
            $this->itemRepository->getDb()->transaction(function () use ($domainId, $itemIds) {
                $this->itemRepository->resetMypageFlags($domainId);
                $order = 1;
                foreach ($itemIds as $itemId) {
                    $this->itemRepository->setMypageActive((int) $itemId, $domainId, $order++);
                }
            });

            return Result::success('마이페이지 메뉴가 저장되었습니다.');
        } catch (\Throwable $e) {
            return Result::failure('마이페이지 메뉴 저장에 실패했습니다.');
        }
    }

    // ========================================
    // 옵션 목록
    // ========================================

    /**
     * target 옵션
     */
    public function getTargetOptions(): array
    {
        return [
            '_self' => '현재 창',
            '_blank' => '새 창',
        ];
    }

    // ========================================
    // 캐시 관리
    // ========================================

    /**
     * 메뉴 URL 맵 캐시 무효화
     *
     * ContextBuilder의 메뉴 매칭에서 사용하는 캐시를 삭제합니다.
     * 메뉴 생성/수정/삭제 시 호출되어 다음 요청에서 최신 데이터를 사용하게 합니다.
     */
    private function invalidateUrlMapCache(int $domainId): void
    {
        $this->cache?->delete("menu:urlmap:{$domainId}");
    }

    // ========================================
    // 기본 메뉴 시딩
    // ========================================

    /**
     * 신규 도메인에 기본 메뉴 시딩
     *
     * 최초 설치 시 seeder(001_seed_menu_data.sql)와 동일한 메뉴 구성을 생성한다.
     * - 메인 메뉴: 홈, 커뮤니티
     * - 유틸리티 메뉴: 로그인/회원가입 (비회원), 마이페이지/로그아웃 (회원)
     * - 마이페이지 메뉴: 회원정보수정, 포인트 지갑, 내가 쓴 글, 내가 쓴 댓글, 회원탈퇴
     * - 푸터 메뉴: 이용약관, 개인정보보호정책
     * - 메뉴 트리: 홈, 커뮤니티 (루트 노드)
     */
    public function seedDefaultMenus(int $domainId): Result
    {
        $definitions = $this->getDefaultMenuDefinitions();
        $createdCodes = [];

        foreach ($definitions as $key => $def) {
            $result = $this->createItem($domainId, $def);
            if ($result->isFailure()) {
                return Result::failure("기본 메뉴 생성 실패: {$def['label']}");
            }
            $createdCodes[$key] = $result->get('menu_code');
        }

        // 메인 메뉴 트리에 홈, 커뮤니티 배치
        foreach (['home', 'community'] as $key) {
            if (isset($createdCodes[$key])) {
                $this->addToTree($domainId, $createdCodes[$key]);
            }
        }

        return Result::success('기본 메뉴가 생성되었습니다.');
    }

    /**
     * 기본 메뉴 정의
     *
     * createItem()에 전달할 데이터 배열
     */
    private function getDefaultMenuDefinitions(): array
    {
        return [
            // === 메인 메뉴 ===
            'home' => [
                'label' => '홈', 'url' => '/',
                'provider_type' => 'core',
            ],
            'community' => [
                'label' => '커뮤니티', 'url' => '/community',
                'provider_type' => 'core',
            ],

            // === 유틸리티 메뉴 (비회원) ===
            'login' => [
                'label' => '로그인', 'url' => '/login',
                'visibility' => 'guest', 'pair_code' => 'auth',
                'show_in_utility' => 1, 'utility_order' => 1,
                'provider_type' => 'core',
            ],
            'register' => [
                'label' => '회원가입', 'url' => '/member/register',
                'visibility' => 'guest', 'pair_code' => 'account',
                'show_in_utility' => 1, 'utility_order' => 2,
                'provider_type' => 'core',
            ],

            // === 유틸리티 메뉴 (회원) ===
            'mypage' => [
                'label' => '마이페이지', 'url' => '/mypage',
                'visibility' => 'member', 'pair_code' => 'account',
                'show_in_utility' => 1, 'utility_order' => 1,
                'provider_type' => 'core',
            ],
            'logout' => [
                'label' => '로그아웃', 'url' => '/logout',
                'visibility' => 'member', 'pair_code' => 'auth',
                'show_in_utility' => 1, 'utility_order' => 2,
                'provider_type' => 'core',
            ],

            // === 마이페이지 서브 메뉴 ===
            'mypage_profile' => [
                'label' => '회원정보수정', 'url' => '/mypage/profile',
                'visibility' => 'member', 'show_in_mypage' => 1, 'mypage_order' => 100,
                'is_system' => 1, 'provider_type' => 'core',
            ],
            'mypage_balance' => [
                'label' => '포인트 지갑', 'url' => '/mypage/balance',
                'visibility' => 'member', 'show_in_mypage' => 1, 'mypage_order' => 200,
                'provider_type' => 'core',
            ],
            'mypage_articles' => [
                'label' => '내가 쓴 글', 'url' => '/mypage/articles',
                'visibility' => 'member', 'show_in_mypage' => 1, 'mypage_order' => 300,
                'provider_type' => 'core',
            ],
            'mypage_comments' => [
                'label' => '내가 쓴 댓글', 'url' => '/mypage/comments',
                'visibility' => 'member', 'show_in_mypage' => 1, 'mypage_order' => 400,
                'provider_type' => 'core',
            ],
            'mypage_withdraw' => [
                'label' => '회원탈퇴', 'url' => '/mypage/withdraw',
                'visibility' => 'member', 'show_in_mypage' => 1, 'mypage_order' => 900,
                'is_system' => 1, 'provider_type' => 'core',
            ],

            // === 푸터 메뉴 ===
            'terms' => [
                'label' => '이용약관', 'url' => '/terms',
                'show_in_footer' => 1, 'footer_order' => 1,
                'provider_type' => 'core',
            ],
            'privacy' => [
                'label' => '개인정보보호정책', 'url' => '/privacy',
                'show_in_footer' => 1, 'footer_order' => 2,
                'provider_type' => 'core',
            ],
        ];
    }
}
