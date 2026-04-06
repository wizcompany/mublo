<?php

namespace Mublo\Packages\Board\Subscriber;

use Mublo\Core\Event\Domain\DomainCreatedEvent;
use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Infrastructure\Database\Database;
use Mublo\Helper\String\StringHelper;

/**
 * 도메인 생성 시 Board 기본 데이터 자동 시딩
 *
 * 패키지가 활성화된 상태에서 새 도메인이 생성되면
 * install()과 동일한 기본 게시판(공지사항, 자유게시판)을 생성한다.
 */
class DomainEventSubscriber implements EventSubscriberInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DomainCreatedEvent::class => 'onDomainCreated',
        ];
    }

    public function onDomainCreated(DomainCreatedEvent $event): void
    {
        $domainId = $event->getDomainId();
        self::seedBoards($this->db, $domainId);
    }

    /**
     * Board 기본 데이터 시딩 (install + DomainCreatedEvent 공용)
     */
    public static function seedBoards(Database $db, int $domainId): void
    {
        if (!$domainId) {
            return;
        }

        // 이미 게시판이 있으면 건너뜀
        $existing = $db->selectOne(
            "SELECT COUNT(*) AS cnt FROM board_configs WHERE domain_id = ?",
            [$domainId]
        );
        if (($existing['cnt'] ?? 0) > 0) {
            return;
        }

        $now = date('Y-m-d H:i:s');

        // 기본 그룹 생성
        $groupId = $db->insert(
            "INSERT IGNORE INTO board_groups (domain_id, group_slug, group_name, sort_order, is_active, created_at, updated_at)
             VALUES (?, 'default', '기본 그룹', 0, 1, ?, ?)",
            [$domainId, $now, $now]
        );

        if (!$groupId) {
            $row = $db->selectOne(
                "SELECT group_id FROM board_groups WHERE domain_id = ? AND group_slug = 'default'",
                [$domainId]
            );
            $groupId = $row['group_id'] ?? null;
        }

        if (!$groupId) {
            return;
        }

        // 기본 게시판 정의 (menu: true → 메뉴 아이템 + 트리 자동 등록)
        $boards = [
            [
                'slug' => 'notice',
                'name' => '공지사항',
                'description' => '사이트 공지사항을 안내하는 게시판입니다.',
                'write_level' => 230,
                'use_reaction' => 0,
                'sort_order' => 0,
                'menu' => true,
            ],
            [
                'slug' => 'free',
                'name' => '자유게시판',
                'description' => '자유롭게 글을 작성할 수 있는 게시판입니다.',
                'write_level' => 1,
                'use_reaction' => 1,
                'sort_order' => 1,
                'menu' => false,
            ],
        ];

        // 기존 메뉴 트리의 마지막 순서 조회
        $lastSort = $db->selectOne(
            "SELECT MAX(sort_order) AS max_sort FROM menu_tree WHERE domain_id = ? AND parent_code IS NULL",
            [$domainId]
        );
        $treeSortOrder = (int) ($lastSort['max_sort'] ?? 0) + 1;

        foreach ($boards as $board) {
            $db->insert(
                "INSERT IGNORE INTO board_configs
                    (domain_id, group_id, board_slug, board_name, board_description,
                     list_level, read_level, write_level, comment_level,
                     board_skin, use_comment, use_reaction, use_file,
                     sort_order, is_active, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?,
                     0, 0, ?, 1, 'basic', 1, ?, 1, ?, 1, ?, ?)",
                [$domainId, $groupId, $board['slug'], $board['name'], $board['description'],
                 $board['write_level'], $board['use_reaction'], $board['sort_order'], $now, $now]
            );

            // 메뉴 아이템 + 트리 배치 (menu: true인 게시판만)
            if (!empty($board['menu'])) {
                $menuCode = self::seedMenuItem($db, $domainId, $board['name'], '/board/' . $board['slug']);
                self::seedTreeNode($db, $domainId, $menuCode, $board['name'], $treeSortOrder++);
            }
        }
    }

    /**
     * 메뉴 아이템 시딩
     *
     * @return string 생성된 menu_code
     */
    private static function seedMenuItem(Database $db, int $domainId, string $label, string $url): string
    {
        $menuCode = StringHelper::random(8);

        // unique_codes 등록
        $db->insert(
            "INSERT INTO unique_codes (domain_id, code_type, code) VALUES (?, 'menu', ?)",
            [$domainId, $menuCode]
        );

        // menu_items 등록
        $db->insert(
            "INSERT INTO menu_items
                (domain_id, menu_code, label, url, icon, provider_type, provider_name, is_active, created_at, updated_at)
             VALUES (?, ?, ?, ?, 'bi-clipboard-text', 'package', 'Board', 1, NOW(), NOW())",
            [$domainId, $menuCode, $label, $url]
        );

        return $menuCode;
    }

    /**
     * 메뉴 트리 노드 시딩 (루트 레벨)
     */
    private static function seedTreeNode(Database $db, int $domainId, string $menuCode, string $label, int $sortOrder): void
    {
        $db->insert(
            "INSERT INTO menu_tree (domain_id, menu_code, path_code, path_name, parent_code, depth, sort_order)
             VALUES (?, ?, ?, ?, NULL, 1, ?)",
            [$domainId, $menuCode, $menuCode, $label, $sortOrder]
        );
    }
}
