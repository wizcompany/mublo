-- 전체 도메인 공통 공지용 게시판 플래그 추가
-- is_global = 1 인 게시판은 모든 도메인의 Front 에서 읽기 가능 (Super 만 설정)
ALTER TABLE board_configs
    ADD COLUMN is_global TINYINT(1) NOT NULL DEFAULT 0 COMMENT '전체 도메인 공통 게시판 (Super 전용)' AFTER is_active,
    ADD INDEX idx_is_global (is_global);
