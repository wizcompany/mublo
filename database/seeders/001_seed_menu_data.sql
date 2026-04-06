-- ====================================
-- Mublo Core - 프론트 메뉴 시드 데이터
-- ====================================
--
-- 설치 시 기본 프론트 메뉴 등록
-- domain_configs 행이 존재해야 FK 제약을 만족하므로
-- 마이그레이션이 아닌 시더로 실행 (setupDomain 이후)
--
-- ====================================

-- ====================================
-- 메뉴 아이템 시드
-- ====================================

-- 메인 메뉴
INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, provider_type, provider_name, is_active)
VALUES (1, 'mH7pK2nT', '홈', '/', NULL, 'core', NULL, 1);

INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, provider_type, provider_name, is_active)
VALUES (1, 'bX4wQ9rY', '커뮤니티', '/community', NULL, 'core', NULL, 1);

-- 유틸리티 메뉴 (비로그인 전용)
INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, visibility, pair_code, show_in_utility, utility_order, provider_type, provider_name, is_active)
VALUES (1, 'cL6vN3eZ', '로그인', '/login', NULL, 'guest', 'auth', 1, 1, 'core', NULL, 1);

INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, visibility, pair_code, show_in_utility, utility_order, provider_type, provider_name, is_active)
VALUES (1, 'fJ8dG5aS', '회원가입', '/member/register', NULL, 'guest', 'account', 1, 2, 'core', NULL, 1);

-- 유틸리티 메뉴 (로그인 전용)
INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, visibility, pair_code, show_in_utility, utility_order, provider_type, provider_name, is_active)
VALUES (1, 'gR1mB7qP', '마이페이지', '/mypage', NULL, 'member', 'account', 1, 1, 'core', NULL, 1);

INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, visibility, pair_code, show_in_utility, utility_order, provider_type, provider_name, is_active)
VALUES (1, 'hW2tC4xL', '로그아웃', '/logout', NULL, 'member', 'auth', 1, 2, 'core', NULL, 1);

-- 마이페이지 서브 메뉴 (로그인 전용, 마이페이지 사이드바에 표시)
INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, visibility, show_in_mypage, mypage_order, is_system, provider_type, provider_name, is_active)
VALUES (1, 'jY5uF6kV', '회원정보수정', '/mypage/profile', NULL, 'member', 1, 100, 1, 'core', NULL, 1);

INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, visibility, show_in_mypage, mypage_order, is_system, provider_type, provider_name, is_active)
VALUES (1, 'kP3nD8wM', '포인트 지갑', '/mypage/balance', NULL, 'member', 1, 200, 0, 'core', NULL, 1);

INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, visibility, show_in_mypage, mypage_order, is_system, provider_type, provider_name, is_active)
VALUES (1, 'lQ7eR1bN', '내가 쓴 글', '/mypage/articles', NULL, 'member', 1, 300, 0, 'core', NULL, 1);

INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, visibility, show_in_mypage, mypage_order, is_system, provider_type, provider_name, is_active)
VALUES (1, 'mS4yT5cJ', '내가 쓴 댓글', '/mypage/comments', NULL, 'member', 1, 400, 0, 'core', NULL, 1);

INSERT IGNORE INTO menu_items (domain_id, menu_code, label, url, icon, visibility, show_in_mypage, mypage_order, is_system, provider_type, provider_name, is_active)
VALUES (1, 'nV9aH2pX', '회원탈퇴', '/mypage/withdraw', NULL, 'member', 1, 900, 1, 'core', NULL, 1);

-- 푸터 메뉴: 002_seed_policy_data.php 에서 등록 (약관 데이터와 함께)

-- ====================================
-- unique_codes 등록 (CodeGenerator 충돌 방지)
-- ====================================

INSERT IGNORE INTO unique_codes (domain_id, code_type, code, reference_table)
VALUES
    (1, 'menu', 'mH7pK2nT', 'menu_items'),
    (1, 'menu', 'bX4wQ9rY', 'menu_items'),
    (1, 'menu', 'cL6vN3eZ', 'menu_items'),
    (1, 'menu', 'fJ8dG5aS', 'menu_items'),
    (1, 'menu', 'gR1mB7qP', 'menu_items'),
    (1, 'menu', 'hW2tC4xL', 'menu_items'),
    (1, 'menu', 'jY5uF6kV', 'menu_items'),
    (1, 'menu', 'kP3nD8wM', 'menu_items'),
    (1, 'menu', 'lQ7eR1bN', 'menu_items'),
    (1, 'menu', 'mS4yT5cJ', 'menu_items'),
    (1, 'menu', 'nV9aH2pX', 'menu_items');

-- ====================================
-- 메인 메뉴 트리 (홈, 커뮤니티만 배치)
-- ====================================

INSERT IGNORE INTO menu_tree (domain_id, menu_code, path_code, path_name, parent_code, depth, sort_order)
VALUES
    (1, 'mH7pK2nT', 'mH7pK2nT', '홈', NULL, 1, 1),
    (1, 'bX4wQ9rY', 'bX4wQ9rY', '커뮤니티', NULL, 1, 2);
