# 개발자 가이드

Mublo Framework의 아키텍처를 이해하고, 패키지와 플러그인을 개발하기 위한 가이드입니다.

## 추천 읽기 경로

1. [아키텍처 개요](architecture.md)
2. [요청 흐름](request-lifecycle.md)
3. [핵심 개념](core-concepts.md)
4. [라우팅과 미들웨어](routing.md)
5. 이후 필요한 주제별 문서

## 읽는 순서

### 기초 (Core 이해)

1. [아키텍처 개요](architecture.md) — 플랫폼 구조, 4대 핵심 시스템, 계층 분리
2. [요청 흐름](request-lifecycle.md) — 부팅에서 응답까지 전체 흐름
3. [핵심 개념](core-concepts.md) — DI, Context, Result, Response
4. [라우팅과 미들웨어](routing.md) — 라우트 등록, 자동 매핑, 미들웨어
5. [데이터베이스](database.md) — DB 접근 API, QueryBuilder, 마이그레이션

### 클라이언트

6. [클라이언트 AJAX 시스템](client-ajax.md) — MubloRequest, MubloModal, 폼 제출

### 확장 개발

7. [이벤트 시스템](event-system.md) — EventDispatcher, Subscriber, 이벤트 발행
8. [Contract 시스템](contract-system.md) — ContractRegistry, 크로스커팅 인터페이스
9. [Manifest 기준](manifest-standard.md) — Package/Plugin 메타데이터 표준
10. [블록 시스템 개발](block-system.md) — BlockRegistry, Renderer, 스킨 연동
11. [패키지 만들기](package-development.md) — 독립 애플리케이션 개발
12. [플러그인 만들기](plugin-development.md) — Core 기능 확장 개발

### 품질

13. [테스트](testing.md) — PHPUnit, 테스트 구조, 작성법
14. [기여 가이드](contributing.md) — 코드 스타일, PR 규칙, 커밋 메시지

---

[< 문서 홈으로](../README.md)
