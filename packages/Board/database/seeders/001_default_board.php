<?php
/**
 * Board 패키지 기본 시드
 *
 * 기본 게시판 그룹 + 공지사항/자유게시판 생성
 *
 * 호출 시나리오:
 * - 초기 설치: Installer.runSeeders() → $pdo만 전달 (domain_id=1)
 * - 새 도메인: ExtensionService.seedDefaultExtensions() → $pdo + $domainId 전달
 */
return function (PDO $pdo, int $domainId = 1): void {
    $now = date('Y-m-d H:i:s');

    // 이미 게시판이 있으면 건너뜀
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `board_configs` WHERE domain_id = ?");
    $stmt->execute([$domainId]);
    if ((int) $stmt->fetchColumn() > 0) {
        return;
    }

    // 1. 기본 그룹 생성
    $stmt = $pdo->prepare("INSERT IGNORE INTO `board_groups`
        (domain_id, group_slug, group_name, sort_order, is_active, created_at, updated_at)
        VALUES (?, 'default', '기본 그룹', 0, 1, ?, ?)");
    $stmt->execute([$domainId, $now, $now]);

    $groupId = $pdo->lastInsertId();
    if (!$groupId) {
        $stmt = $pdo->prepare("SELECT group_id FROM `board_groups` WHERE domain_id = ? AND group_slug = 'default'");
        $stmt->execute([$domainId]);
        $groupId = $stmt->fetchColumn();
    }

    if (!$groupId) {
        return;
    }

    // 2. 공지사항 (write_level=230: 스태프 이상만 작성, 비회원도 열람 가능)
    $stmt = $pdo->prepare("INSERT IGNORE INTO `board_configs`
        (domain_id, group_id, board_slug, board_name, board_description,
         list_level, read_level, write_level, comment_level,
         board_skin, use_comment, use_reaction, use_file,
         sort_order, is_active, created_at, updated_at)
        VALUES (?, ?, 'notice', '공지사항', '사이트 공지사항을 안내하는 게시판입니다.',
         0, 0, 230, 1,
         'basic', 1, 0, 1,
         0, 1, ?, ?)");
    $stmt->execute([$domainId, $groupId, $now, $now]);

    // 3. 자유게시판 (write_level=1: 회원만 작성)
    $stmt = $pdo->prepare("INSERT IGNORE INTO `board_configs`
        (domain_id, group_id, board_slug, board_name, board_description,
         list_level, read_level, write_level, comment_level,
         board_skin, use_comment, use_reaction, use_file,
         sort_order, is_active, created_at, updated_at)
        VALUES (?, ?, 'free', '자유게시판', '자유롭게 글을 작성할 수 있는 게시판입니다.',
         0, 0, 1, 1,
         'basic', 1, 1, 1,
         1, 1, ?, ?)");
    $stmt->execute([$domainId, $groupId, $now, $now]);
};
