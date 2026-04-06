# 설정 파일 레퍼런스

설정 파일은 설치 시 자동 생성됩니다. `config/` 디렉토리에 위치합니다.

## config/app.php

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `name` | string | 앱 이름 | 'Mublo' |
| `env` | string | 환경 (local/production) | .env `APP_ENV` |
| `debug` | bool | 디버그 모드 | .env `APP_DEBUG` |
| `timezone` | string | 타임존 | 'Asia/Seoul' |
| `encrypt_key` | string | 암호화 키 (64바이트 hex) | 설치 시 자동 생성 |

## config/database.php

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `driver` | string | DB 드라이버 | 'mysql' |
| `host` | string | DB 호스트 | 설치 시 입력 |
| `port` | int | DB 포트 | 3306 |
| `database` | string | 데이터베이스명 | 설치 시 입력 |
| `username` | string | DB 사용자 | 설치 시 입력 |
| `password` | string | DB 비밀번호 (암호화) | 설치 시 입력 |
| `charset` | string | 문자셋 | 'utf8mb4' |
| `collation` | string | 콜레이션 | 'utf8mb4_unicode_ci' |
| `_encrypted` | bool | 비밀번호 암호화 여부 | true |
| `_encrypt_key` | string | 복호화 키 | 자동 생성 |

## config/security.php

### password

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `algo` | int | 해시 알고리즘 | PASSWORD_DEFAULT |
| `cost` | int | bcrypt 비용 | 12 |

### csrf

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `token_ttl` | int | 토큰 유효시간 (초) | 3600 |
| `token_key` | string | CSRF 키 | 설치 시 생성 |

### session

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `lifetime` | int | 세션 수명 (분) | 120 |
| `httponly` | bool | HttpOnly 쿠키 | true |
| `samesite` | string | SameSite 정책 | 'Lax' |

### login_rate_limiting

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `enabled` | bool | Rate Limiting 활성화 | true |
| `max_attempts_per_user` | int | 사용자별 최대 시도 | 5 |
| `max_attempts_per_ip` | int | IP별 최대 시도 | 20 |
| `decay_seconds` | int | 시도 카운트 리셋 시간 | 900 (15분) |
| `lockout_seconds` | int | 잠금 시간 | 600 (10분) |

### encryption

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `field_key` | string | 필드 암호화 키 (AES-256-GCM) | 설치 시 생성 |

### search

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `pepper` | string | Blind Index pepper (HMAC-SHA256) | 설치 시 생성 |

### cache / session 드라이버

.env에서 설정:

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `cache_driver` | string | 캐시 드라이버 (file/redis) | 'file' |
| `session_driver` | string | 세션 드라이버 (file/redis) | 'file' |

### redis

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `redis.host` | string | Redis 호스트 | '127.0.0.1' |
| `redis.port` | int | Redis 포트 | 6379 |
| `redis.password` | string | Redis 비밀번호 | '' |

### trusted_proxies

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `trusted_proxies` | array | 신뢰 프록시 IP 목록 | .env `TRUSTED_PROXIES` |

## config/mail.php

| 키 | 타입 | 설명 | 기본값 |
|----|------|------|--------|
| `driver` | string | 메일 드라이버 (mail/smtp) | .env `MAIL_DRIVER` ('mail') |
| `from_address` | string | 발신자 이메일 | .env `MAIL_FROM_ADDRESS` |
| `from_name` | string | 발신자 이름 | .env `MAIL_FROM_NAME` |
| `smtp.host` | string | SMTP 호스트 | .env `MAIL_SMTP_HOST` |
| `smtp.port` | int | SMTP 포트 | .env `MAIL_SMTP_PORT` |
| `smtp.encryption` | string | 암호화 (tls/ssl) | .env `MAIL_SMTP_ENCRYPTION` |
| `smtp.username` | string | SMTP 사용자 | .env `MAIL_SMTP_USERNAME` |
| `smtp.password` | string | SMTP 비밀번호 | .env `MAIL_SMTP_PASSWORD` |

## .env 환경 변수

```env
# 앱
APP_DEBUG=false              # 디버그 모드 (운영: false)
APP_ENV=production           # 환경명

# 캐시/세션
CACHE_DRIVER=file            # file | redis
SESSION_DRIVER=file          # file | redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=

# 메일
MAIL_DRIVER=mail             # mail | smtp
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME=Mublo
MAIL_SMTP_HOST=
MAIL_SMTP_PORT=587
MAIL_SMTP_ENCRYPTION=tls
MAIL_SMTP_USERNAME=
MAIL_SMTP_PASSWORD=

# 프록시
TRUSTED_PROXIES=             # 쉼표 구분 IP 목록
```

## 경로 상수 (bootstrap.php)

| 상수 | 값 |
|------|-----|
| `MUBLO_ROOT_PATH` | 프로젝트 루트 |
| `MUBLO_CONFIG_PATH` | config/ |
| `MUBLO_STORAGE_PATH` | storage/ |
| `MUBLO_PUBLIC_PATH` | public/ |
| `MUBLO_PUBLIC_STORAGE_PATH` | public/storage/ |
| `MUBLO_PLUGIN_PATH` | plugins/ |
| `MUBLO_PACKAGE_PATH` | packages/ |
| `MUBLO_ASSET_URI` | /assets |

---

[< 레퍼런스 목록](README.md)
