# DB 스키마

## Core 테이블

### domain_configs

멀티 도메인 설정 테이블.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| domain_id | INT PK | 도메인 ID |
| domain | VARCHAR(200) | 도메인명 (example.com) |
| domain_group | VARCHAR(100) | 도메인 그룹 계층 (1/3/5) |
| member_id | INT | 도메인 소유자 |
| status | ENUM | active, inactive, blocked |
| contract_type | VARCHAR(20) | free, monthly, yearly, permanent |
| contract_start_date | DATE | 계약 시작일 |
| contract_end_date | DATE | 계약 종료일 |
| storage_limit | BIGINT | 저장소 한도 (bytes) |
| member_limit | INT | 회원 수 한도 |
| site_config | JSON | 사이트 기본 설정 |
| company_config | JSON | 회사 정보 |
| seo_config | JSON | SEO 설정 |
| theme_config | JSON | 테마/스킨 설정 |
| extension_config | JSON | 활성 패키지/플러그인 목록 |
| extra_config | JSON | 기타 설정 |

### members

회원 테이블.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| member_id | BIGINT PK | 회원 ID |
| domain_id | INT | 도메인 ID |
| userid | VARCHAR(100) | 로그인 ID |
| password | VARCHAR(255) | bcrypt 해시 |
| nickname | VARCHAR(100) | 닉네임 |
| email | VARCHAR(200) | 이메일 |
| level_value | INT | 회원 등급 값 |
| status | ENUM | active, inactive, dormant, blocked, pending, withdrawn |
| point_balance | INT | 포인트 잔액 (스냅샷) |
| last_login_at | DATETIME | 최근 로그인 |
| created_at | DATETIME | 가입일 |
| updated_at | DATETIME | 수정일 |

UK: `(domain_id, userid)`

### member_levels

회원 등급 정의.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| level_id | INT PK | 등급 ID |
| domain_id | INT | 도메인 ID |
| level_value | INT | 등급 값 (1~255) |
| level_name | VARCHAR(50) | 등급명 |
| is_admin | TINYINT | 관리자 여부 |
| is_super | TINYINT | Super Admin 여부 |

### balance_logs

포인트 원장 (INSERT ONLY).

| 컬럼 | 타입 | 설명 |
|------|------|------|
| log_id | BIGINT PK | 로그 ID |
| domain_id | INT | 도메인 ID |
| member_id | BIGINT | 회원 ID |
| amount | INT | 변경액 (+지급, -차감) |
| balance_before | INT | 변경 전 잔액 |
| balance_after | INT | 변경 후 잔액 |
| source_type | VARCHAR(20) | plugin, package, admin, system |
| source_name | VARCHAR(50) | MemberPoint, Shop 등 |
| action | VARCHAR(50) | article_write, purchase 등 |
| message | VARCHAR(500) | 사용자 메시지 |
| reference_type | VARCHAR(50) | 참조 타입 (선택) |
| reference_id | VARCHAR(100) | 참조 ID (선택) |
| idempotency_key | VARCHAR(100) | 중복 방지 키 (선택) |
| admin_id | INT | 관리자 ID (수동 조정 시) |
| created_at | DATETIME | 생성일 |

### block_pages

블록 페이지.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| page_id | INT PK | 페이지 ID |
| domain_id | INT | 도메인 ID |
| page_code | VARCHAR(50) | 페이지 코드 |
| page_title | VARCHAR(200) | 페이지 제목 |
| layout_type | TINYINT | 1(전체), 2(좌측), 3(우측), 4(양측) |
| is_active | TINYINT | 활성 여부 |

### block_rows

블록 행.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| row_id | INT PK | 행 ID |
| domain_id | INT | 도메인 ID |
| page_id | INT | 소속 페이지 (NULL이면 위치 기반) |
| position | VARCHAR(20) | 위치 (index, left, right 등) |
| column_count | INT | 열 개수 |
| sort_order | INT | 정렬 순서 |
| is_active | TINYINT | 활성 여부 |

### block_columns

블록 열.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| column_id | INT PK | 열 ID |
| row_id | INT | 소속 행 |
| domain_id | INT | 도메인 ID |
| content_type | VARCHAR(50) | 콘텐츠 타입 (html, banner, board 등) |
| content_kind | VARCHAR(20) | core, plugin, package |
| content_skin | VARCHAR(50) | 스킨명 |
| content_config | JSON | 콘텐츠 설정 |
| content_items | JSON | 선택된 항목 목록 |
| title_config | JSON | 제목 설정 |
| sort_order | INT | 정렬 순서 |
| is_active | TINYINT | 활성 여부 |

### menu_items

메뉴 항목.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| item_id | INT PK | 항목 ID |
| domain_id | INT | 도메인 ID |
| menu_type | VARCHAR(20) | main, footer, utility, mypage |
| parent_id | INT | 상위 메뉴 ID |
| label | VARCHAR(100) | 표시 텍스트 |
| url | VARCHAR(500) | 이동 URL |
| icon | VARCHAR(100) | 아이콘 클래스 |
| sort_order | INT | 정렬 순서 |

### schema_migrations

마이그레이션 이력.

| 컬럼 | 타입 | 설명 |
|------|------|------|
| id | INT PK | |
| source | ENUM | core, plugin, package |
| name | VARCHAR(100) | core, Banner, Board 등 |
| file | VARCHAR(200) | 001_create_tables.sql |
| executed_at | DATETIME | 실행일 |

## Board 패키지 테이블

### board_groups

| 컬럼 | 설명 |
|------|------|
| group_id | 그룹 ID |
| domain_id | 도메인 ID |
| group_name | 그룹명 |
| group_slug | URL 슬러그 |
| sort_order | 정렬 순서 |

### board_configs

게시판 설정. 50개 이상의 설정 컬럼.

| 주요 컬럼 | 설명 |
|----------|------|
| board_id | 게시판 ID |
| domain_id | 도메인 ID |
| group_id | 소속 그룹 |
| board_name | 게시판명 |
| board_slug | URL 슬러그 |
| board_skin | 스킨명 |
| board_editor | 에디터명 |
| use_comment | 댓글 사용 여부 |
| use_reaction | 리액션 사용 여부 |
| use_file | 파일 첨부 여부 |
| use_link | 링크 사용 여부 |
| use_category | 카테고리 사용 여부 |
| is_secret_board | 비밀게시판 여부 |
| list_level / read_level / write_level / comment_level / download_level | 권한 레벨 |
| file_count_limit / file_size_limit | 파일 제한 |
| reaction_config | JSON (리액션 타입 설정) |
| daily_write_limit / daily_comment_limit | JSON (레벨별 일일 제한) |

### board_articles

| 주요 컬럼 | 설명 |
|----------|------|
| article_id | 글 ID |
| domain_id, board_id | 소속 |
| category_id | 카테고리 (선택) |
| author_name | 작성자 이름 (스냅샷) |
| author_password | 비회원 비밀번호 (bcrypt) |
| title | 제목 |
| content | 본문 (HTML) |
| thumbnail | 썸네일 URL |
| status | published, draft, deleted |
| is_notice | 공지 여부 |
| is_secret | 비밀글 여부 |
| view_count | 조회수 |
| comment_count | 댓글수 |
| reaction_count | 리액션수 |

### board_comments

| 주요 컬럼 | 설명 |
|----------|------|
| comment_id | 댓글 ID |
| article_id | 소속 글 |
| parent_id | 상위 댓글 (중첩) |
| depth | 깊이 (0=댓글, 1=답글) |
| path | 계층 경로 (1/3/12) |
| content | 본문 |
| is_secret | 비밀 댓글 |

### board_categories

| 컬럼 | 설명 |
|------|------|
| category_id | 카테고리 ID |
| domain_id | 도메인 ID |
| category_name | 카테고리명 |
| category_slug | URL 슬러그 |
| sort_order | 정렬 순서 |

### board_category_mapping

게시판 ↔ 카테고리 다대다 매핑. 매핑 단위로 권한 재정의 가능.

### board_reactions

| 컬럼 | 설명 |
|------|------|
| reaction_id | 리액션 ID |
| target_type | article / comment |
| target_id | 대상 ID |
| member_id | 회원 ID |
| reaction_type | like 등 |

UK: `(target_type, target_id, member_id)`

### board_attachments

| 컬럼 | 설명 |
|------|------|
| attachment_id | 첨부파일 ID |
| article_id | 소속 글 |
| original_name | 원본 파일명 |
| stored_path | 저장 경로 |
| file_size | 파일 크기 |
| mime_type | MIME 타입 |
| is_image | 이미지 여부 |
| download_count | 다운로드 수 |

### board_links

외부 링크 (OG 메타 자동 추출).

### board_permissions

통합 권한 테이블 (그룹/카테고리/게시판 대상).

| 컬럼 | 설명 |
|------|------|
| target_type | group, category, board |
| target_id | 대상 ID |
| member_id | 회원 ID |
| permission_type | admin, moderator, editor |

### board_point_configs / board_point_scope_configs

포인트 설정 (도메인별 전역 + 그룹/게시판별 재정의).

---

[< 레퍼런스 목록](README.md)
