# 기여 가이드

Mublo Framework 공개 저장소에 기여할 때의 기본 기준을 정리합니다.

## 시작하기

1. 저장소를 포크합니다.
2. 로컬에 클론합니다.
3. 기능 브랜치를 생성합니다.

```bash
git checkout -b feature/my-feature
```

## 개발 환경

- PHP 8.2 이상
- MySQL 5.7 이상 또는 MariaDB 10.3 이상
- Composer

```bash
composer install
composer test
```

`config/`, `storage/`, `.env` 는 설치 과정 또는 로컬 환경 설정으로 준비합니다.

## 코드 스타일

- 클래스명은 PascalCase를 사용합니다.
- 메서드명은 camelCase를 사용합니다.
- Controller에는 비즈니스 로직을 넣지 않습니다.
- Service는 가능하면 `Result` 객체를 반환합니다.
- 한 클래스에 여러 책임을 섞지 않습니다.

## 커밋 메시지

커밋 메시지는 한글을 기본으로 사용합니다.

```text
feat: 회원 포인트 자동 지급 기능 추가
fix: 게시판 권한 검사 누락 수정
refactor: BoardService 메서드 분리
docs: 설치 가이드 업데이트
test: MemberService 단위 테스트 추가
```

## Pull Request

- `main` 기준 최신 상태에서 브랜치를 분기합니다.
- 관련 테스트를 통과시킨 뒤 PR을 생성합니다.
- 공개 범위를 바꾸는 변경이면 `README`, `docs/README`, 관련 모듈 README를 함께 수정합니다.

### PR 체크리스트

- [ ] 테스트 통과
- [ ] 기존 기능 회귀 없음
- [ ] 코드 스타일 준수
- [ ] 필요한 문서 업데이트 완료

## 테스트

PR 전 최소 `composer test` 는 통과해야 합니다. DI 규칙과 테스트를 함께 확인하려면 아래 명령을 사용합니다.

```bash
composer check
```

---

[< 이전: 테스트](testing.md) | [문서 홈](../README.md)
