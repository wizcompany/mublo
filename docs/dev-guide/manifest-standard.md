# Manifest 기준

`manifest.json`은 Package/Plugin 메타데이터의 단일 기준이다.  
관리 UI, 설치기, 확장 스캔은 이 파일을 기준으로 확장을 식별한다.

이 문서는 현재 코어 로더가 실제로 읽는 키를 기준으로 정리한다.

## 공통 원칙

- `name`은 디렉토리명과 동일한 PascalCase를 사용한다.
- 표시 이름은 `title`이 아니라 `label`을 사용한다.
- 버전은 SemVer 형식(`1.0.0`)을 사용한다.
- 의존성은 `requires` 객체에 명시한다.
- 로더가 읽지 않는 임의 키는 넣지 않는다.

## 표준 키

| 키 | 타입 | 대상 | 필수 | 설명 |
|----|------|------|------|------|
| `name` | string | 공통 | 예 | 확장 식별자. 디렉토리명과 같아야 한다 |
| `label` | string | 공통 | 예 | 관리자/설치 화면에 보이는 이름 |
| `description` | string | 공통 | 예 | 한 줄 설명 |
| `version` | string | 공통 | 예 | SemVer 버전 |
| `author` | string | 공통 | 예 | 제작자/조직명 |
| `author_url` | string | 공통 | 권장 | 제작자 사이트 URL |
| `icon` | string | 공통 | 권장 | Bootstrap Icons 클래스명 |
| `type` | string | Plugin | 예 | 항상 `plugin` |
| `category` | string | Plugin | 권장 | 플러그인 분류 |
| `default` | bool | 공통 | 선택 | 신규 설치 시 기본 활성화 여부 |
| `hidden` | bool | Plugin | 선택 | 확장 관리 화면에서 숨김 |
| `super_only` | bool | Plugin | 선택 | 최상위 도메인만 직접 제어, 하위 도메인은 자동 승계 |
| `requires` | object | 공통 | 권장 | 의존성 버전 범위 |

## `requires` 형식

최소한 `core` 요구 버전은 명시한다.

```json
{
    "requires": {
        "core": ">=1.0.0"
    }
}
```

Package 의존성이 있으면 함께 적는다.

```json
{
    "requires": {
        "core": ">=1.0.0",
        "package:Shop": ">=1.0.0"
    }
}
```

## Package manifest 예시

```json
{
    "name": "MyPackage",
    "label": "내 패키지",
    "description": "패키지 설명",
    "version": "1.0.0",
    "author": "Mublo",
    "author_url": "https://github.com/wizcompany/Mublo",
    "icon": "bi-box",
    "default": false,
    "requires": {
        "core": ">=1.0.0"
    }
}
```

## Plugin manifest 예시

```json
{
    "name": "MyPlugin",
    "label": "내 플러그인",
    "description": "플러그인 설명",
    "version": "1.0.0",
    "author": "Mublo",
    "author_url": "https://github.com/wizcompany/Mublo",
    "type": "plugin",
    "category": "content",
    "icon": "bi-puzzle",
    "default": false,
    "hidden": false,
    "super_only": false,
    "requires": {
        "core": ">=1.0.0"
    }
}
```

## 권장 category 값

`category`는 강제값은 아니지만, 아래 정도로 맞추는 편이 좋다.

| 값 | 용도 예시 |
|----|-----------|
| `content` | Banner, Faq, Popup, Widget |
| `member` | MemberPoint, SnsLogin |
| `marketing` | Survey, VisitorStats |
| `infrastructure` | 운영 연동, 배포 보조, 외부 시스템 연결 플러그인 |
| `payment` | 결제 게이트웨이 플러그인 |
| `messaging` | 알림톡, 문자, 메일 연동 플러그인 |

## 레거시 키

아래 키는 현재 코어 manifest 표준으로 보지 않는다.

| 키 | 상태 | 비고 |
|----|------|------|
| `title` | 사용 중지 | `label`로 대체 |
| `provider` | 불필요 | Provider는 디렉토리/클래스 규칙으로 찾는다 |
| `hidden` | 운영 키로 유지 | 인프라성 플러그인을 확장 관리 화면에서 숨길 때 사용 |

기존 확장에 위 키가 남아 있어도 동작할 수는 있지만, 새 확장과 정리 대상 확장에는 사용하지 않는다.

## 실무 기준

- Package는 `type`을 넣지 않는다.
- Plugin은 `type: "plugin"`을 명시한다.
- `author_url`과 `requires.core`는 빠뜨리지 않는다.
- 인프라성 플러그인은 `hidden: true` + `super_only: true` 조합을 우선 검토한다.
- 인프라성 플러그인만 `super_only`를 검토한다.
- manifest는 설명용 파일이 아니라 설치/관리 UI의 입력값이므로, 임시 키를 넣지 않는다.

## 관련 문서

- [패키지 만들기](package-development.md)
- [플러그인 만들기](plugin-development.md)
