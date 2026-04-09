# SnsLogin

Mublo Framework SNS 로그인 플러그인입니다.

## Overview

- 네이버/카카오/Google OAuth 로그인
- SNS 계정 연결 및 해제
- 관리자 제공자 설정 및 연결 계정 관리

## Dependency

- Mublo Core `>=1.0.0`

## Install

- 설치 라우트: `POST /admin/sns-login/install`
- 관리자 진입점: `/admin/sns-login/settings`

## Main Routes

- Front
  - `GET /sns-login/auth/{provider}`
  - `GET /sns-login/callback/{provider}`
  - `POST /sns-login/unlink`
  - `GET|POST /sns-login/profile/complete`
- Admin
  - `GET|POST /admin/sns-login/settings`
  - `GET /admin/sns-login/accounts`

## Notes

- 관리자 라우트는 `AdminMiddleware`를 사용합니다.
- 계정 연결 해제는 `AuthMiddleware`를 사용합니다.
- 실제 운영에는 각 SNS 제공자 앱 설정이 필요합니다.
