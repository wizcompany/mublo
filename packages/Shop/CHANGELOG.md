# 변경 이력

이 파일은 Mublo Shop 패키지의 변경 사항을 기록합니다.
[Semantic Versioning](https://semver.org/lang/ko/) 규칙을 따릅니다.

## [Unreleased]

## [1.0.0] - 2026-04-05

### 추가
- **상품 관리**: 기본/선택/단독/텍스트 옵션 4가지 모드, 회원 등급별 가격, 상품 이미지 다중 업로드
- **장바구니**: 세션/회원 통합 장바구니, 수량 변경, 중복 아이템 병합(upsert)
- **주문 시스템**: FSM 기반 주문 상태 관리 (`OrderStateResolver`), 상태별 전이 검증
- **결제 연동**: `ContractRegistry` 기반 1:N PG 연동 (`PaymentGatewayInterface`), 금액·소유권·이중결제 검증
- **쿠폰 시스템**: 정액/정률 할인, 발행 기간/한도 제어, 프로모션 코드 등록, 복원(환불) 처리
- **배송 관리**: 배송 템플릿(무료/고정/조건부/수량별/무게별), 배송사 연동, 운송장 추적 (`ShipmentService`)
- **기획전**: 기간 설정, 상품·카테고리 연결, 슬러그 URL (`ExhibitionService`)
- **구매후기/상품문의**: 별점, 이미지 첨부, 상태 관리 (답변 대기/완료)
- **위시리스트**: 회원별 저장, 장바구니 이동
- **PII 암호화**: 주문자·수령인 개인정보 AES-256-GCM 암호화, Blind Index 검색
- **블록 연동**: 상품 목록·리뷰 블록 렌더러 (`GoodsRenderer`, `ReviewRenderer`)
- **관리자 UI**: 주문/상품/쿠폰/배송/기획전/통계 대시보드
- **이벤트**: `PaymentCompletedEvent`, `PaymentMismatchEvent`, `OrderStatusChangedEvent` 등
- **단위 테스트**: OrderService, CartService, CartCheckoutService, CouponService, PaymentService 커버리지

[Unreleased]: https://github.com/mublo-framework/shop/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/mublo-framework/shop/releases/tag/v1.0.0
