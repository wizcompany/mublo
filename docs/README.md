# Mublo Framework 문서

> 사용자 가이드는 초기 공개 번들 기준으로 읽는 것을 권장합니다.
> 기본 공개 범위는 `Board`, `Shop` 패키지와 `Banner`, `Faq`, `MemberPoint`, `Popup`, `SnsLogin`, `Survey`, `VisitorStats`, `Widget` 플러그인입니다.
> 이 공개 저장소에는 위 범위만 포함되어 있습니다.

## 어디서 시작할지

- 운영자라면: [사용자 가이드](user-guide/README.md)
- 개발자라면: [개발자 가이드](dev-guide/README.md)
- 구조를 빠르게 훑고 싶다면: [아키텍처 개요](dev-guide/architecture.md)
- 설치부터 보려면: [설치 가이드](user-guide/installation.md)

## 사용자(운영자) 가이드

Mublo를 설치하고 사이트를 운영하는 방법을 안내합니다. 개발 지식이 없어도 따라할 수 있습니다.

| 순서 | 문서 | 설명 |
|------|------|------|
| 1 | [설치 가이드](user-guide/installation.md) | 요구사항, 파일 업로드, 웹 설치기 |
| 2 | [첫 실행과 기본 설정](user-guide/first-setup.md) | 사이트명, 기본 도메인, 관리자 설정 |
| 3 | [관리자 화면 기본 사용법](user-guide/admin-basics.md) | 관리자 메뉴 구조, 공통 조작법 |
| 4 | [멀티 도메인 관리](user-guide/domain-management.md) | 도메인 추가, 설정 분리, 테마 적용 |
| 5 | [블록으로 페이지 만들기](user-guide/block-page-builder.md) | 블록 종류, 행/열 구성, 스킨 선택 |
| 6 | [게시판 운영](user-guide/board-usage.md) | 게시판 생성, 권한 설정, 카테고리 |
| 7 | [회원 관리](user-guide/member-management.md) | 회원 레벨, 권한, 포인트 |
| 8 | [플러그인 사용법](user-guide/plugin-usage.md) | 배너, FAQ, 위젯, 팝업, 통계 등 |
| 9 | [문제 해결](user-guide/troubleshooting.md) | 자주 묻는 질문, 오류 대응 |

## 개발자 가이드

패키지나 플러그인을 개발하거나 Core를 이해하고 싶은 개발자를 위한 가이드입니다.

| 순서 | 문서 | 설명 |
|------|------|------|
| 1 | [아키텍처 개요](dev-guide/architecture.md) | 플랫폼 구조, 4대 핵심 시스템, 계층 분리 |
| 2 | [요청 흐름](dev-guide/request-lifecycle.md) | 부팅 → Context → 라우팅 → 응답 |
| 3 | [핵심 개념](dev-guide/core-concepts.md) | DI, Context, Result, Response |
| 4 | [라우팅과 미들웨어](dev-guide/routing.md) | 라우트 등록, 자동 매핑, 미들웨어 체인 |
| 5 | [데이터베이스](dev-guide/database.md) | DB 접근 API, QueryBuilder, 마이그레이션 |
| 6 | [이벤트 시스템](dev-guide/event-system.md) | EventDispatcher, Subscriber, 이벤트 발행 |
| 7 | [Contract 시스템](dev-guide/contract-system.md) | ContractRegistry, 크로스커팅 인터페이스 |
| 8 | [블록 시스템 개발](dev-guide/block-system.md) | BlockRegistry, Renderer, 스킨 |
| 9 | [패키지 만들기](dev-guide/package-development.md) | Provider, 라우트, 마이그레이션, 이벤트 |
| 10 | [플러그인 만들기](dev-guide/plugin-development.md) | Provider, Contract 구현, 관리자 메뉴 |
| 11 | [테스트](dev-guide/testing.md) | PHPUnit, 테스트 구조, 작성법 |
| 12 | [기여 가이드](dev-guide/contributing.md) | 코드 스타일, PR 규칙, 커밋 메시지 |

## API / 스키마 레퍼런스

| 문서 | 설명 |
|------|------|
| [디렉토리 구조](reference/directory-structure.md) | 전체 디렉토리 구조 상세 |
| [DB 스키마](reference/database-schema.md) | Core + Board 중심 테이블 구조 |
| [이벤트 목록](reference/event-reference.md) | 이벤트 클래스, 파라미터, 발행 시점 |
| [설정 파일](reference/config-reference.md) | app.php, database.php, security.php |
| [확장 포인트](reference/hook-points.md) | Plugin/Package가 개입할 수 있는 지점 목록 |
