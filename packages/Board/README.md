# Board

Mublo Framework 기본 게시판 패키지입니다.

## Overview

- 게시판/카테고리/그룹 관리
- 게시글/댓글/첨부파일/반응 기능
- 커뮤니티 메인 화면 제공
- 게시판별 포인트 정책 지원

## Dependency

- Mublo Core `>=1.0.0`

## Install

1. 패키지가 로드된 상태에서 관리자에서 설치를 실행합니다.
2. 설치 라우트:
   - `POST /admin/board/install`

실사용 진입점:
- 관리자: `/admin/board/config`
- 프론트: `/board/{board_id}`
- 커뮤니티: `/community`

## Main Routes

- Front
  - `GET /board/{board_id}`
  - `GET /board/{board_id}/view/{post_no}`
  - `GET|POST /board/{board_id}/write`
- Admin
  - `GET /admin/board/config`
  - `GET /admin/board/group`
  - `GET /admin/board/article`
  - `GET /admin/board/category`
  - `GET|POST /admin/board/point`

## Notes

- 관리자 라우트는 `AdminMiddleware`를 사용합니다.
- 패키지 마이그레이션은 `database/migrations` 기준으로 실행됩니다.
