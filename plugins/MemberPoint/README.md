# MemberPoint

Mublo Framework 회원 포인트 플러그인입니다.

## Overview

- 회원 포인트 적립/차감 이력 관리
- 관리자 수동 포인트 조정
- 회원 마이페이지 포인트 내역 제공

## Dependency

- Mublo Core `>=1.0.0`

## Install

- 설치 라우트: `POST /admin/member-point/install`
- 관리자 진입점: `/admin/member-point/history`

## Main Routes

- Front
  - `GET /member-point/my`
- Admin
  - `GET /admin/member-point/history`
  - `GET /admin/member-point/adjust`
  - `GET|POST /admin/member-point/member-settings`

## Notes

- 관리자 라우트는 `AdminMiddleware`를 사용합니다.
- 포인트 내역 상세/검증/회원 검색용 관리자 API가 포함됩니다.
