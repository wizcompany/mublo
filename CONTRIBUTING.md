# 기여 가이드

Mublo Framework에 기여해 주셔서 감사합니다.

## 시작하기

1. 저장소를 포크합니다
2. 로컬에 클론합니다
3. 브랜치를 생성합니다

```bash
git checkout -b feature/my-feature
```

## 개발 환경

- PHP 8.2 이상
- MySQL 5.7 이상
- Composer

```bash
composer install
composer test
```

로컬 개발 시 `config/`, `storage/`, `.env` 는 설치 과정 또는 환경별 설정으로 준비합니다.

## 코드 스타일

- 클래스명: PascalCase (`MemberService`)
- 메서드명: camelCase (`findById`)
- 한 클래스 한 책임
- Controller에 비즈니스 로직 금지
- Service는 Result 객체 반환

## 커밋 메시지

한글로 작성합니다.

```
feat: 회원 포인트 자동 지급 기능 추가
fix: 게시판 권한 검사 누락 수정
refactor: BoardService 메서드 분리
docs: 설치 가이드 업데이트
test: MemberService 단위 테스트 추가
```

## Pull Request

1. `main` 브랜치에서 최신 코드를 가져옵니다
2. 기능 브랜치에서 작업합니다
3. 테스트를 통과시킵니다
4. PR을 생성합니다

### PR 체크리스트

- [ ] 테스트 통과
- [ ] 기존 테스트 깨뜨리지 않음
- [ ] 코드 스타일 준수
- [ ] 관련 문서 업데이트 (필요 시)

## 공개 범위 주의

- 현재 공개 저장소 기준 공식 대상은 `Board`, `Shop` 패키지와 기본 플러그인 번들입니다.
- 내부용 모듈, 실험용 모듈, 별도 애드온을 전제로 한 변경은 기본 브랜치에 바로 섞지 않습니다.
- 새 기능이 공개 범위를 넓히면 `README`, `docs/README`, 모듈 README를 함께 갱신합니다.

## 질문이 있다면

이슈를 생성해 주세요.
