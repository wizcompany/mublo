# 클라이언트 AJAX 시스템

Mublo의 클라이언트 사이드 통신은 두 모듈이 담당합니다.

| 모듈 | 역할 | 파일 |
|------|------|------|
| **MubloRequest** | AJAX 요청, CSRF 관리, 폼 제출, 로딩 UX | `public/assets/js/MubloRequest.js` |
| **MubloModal** | 모달 다이얼로그 (alert, confirm, 커스텀) | `public/assets/js/MubloModal.js` |

두 모듈 모두 Front/Admin 공용이며, 별도 빌드 없이 `<script>` 태그로 로드됩니다.

---

## MubloRequest

### 설계 원칙

1. **Promise 기반** — 모든 요청 메서드가 Promise를 반환합니다
2. **CSRF 자동 관리** — 토큰 획득/캐싱/갱신을 내부에서 처리합니다
3. **에러 자동 처리** — HTTP 에러와 비즈니스 에러 모두 alert를 자동 표시합니다
4. **선언형 폼** — `data-*` 속성으로 폼 동작을 선언하면 JS가 해석합니다

### 요청 메서드

| 메서드 | HTTP | Content-Type | 용도 |
|--------|------|--------------|------|
| `requestJson(url, data, options)` | POST | `application/json` | API 호출, 데이터 전송 |
| `requestQuery(url, params, options)` | GET | QueryString | 데이터 조회, 목록 로드 |
| `sendRequest(config)` | 자유 | PayloadType 기반 | 파일 업로드 등 저수준 |
| `submitForm(button)` | POST | `multipart/form-data` | 폼 자동 제출 |

### JSON 요청

가장 일반적인 패턴입니다.

```javascript
MubloRequest.requestJson('/shop/cart/add', {
    goods_id: 123,
    quantity: 1
}).then(function(res) {
    // .then()에 도달 = 성공 확정 (result === 'success' 보장)
    alert(res.message);
    location.href = '/shop/cart';
}).catch(function(err) {
    // 에러 alert는 이미 자동 표시됨
    // 추가 처리가 필요한 경우에만 catch 작성
    console.error(err.message);
});
```

### GET 쿼리 요청

```javascript
MubloRequest.requestQuery('/shop/products', {
    page: 1,
    category: 'shoes'
}).then(function(res) {
    renderList(res.data.items);
});
```

### 옵션 (3번째 인자)

```javascript
MubloRequest.requestJson('/admin/settings/save', data, {
    loading: true   // 프로그레스 오버레이 표시
});
```

| 키 | 타입 | 기본값 | 설명 |
|----|------|--------|------|
| `loading` | boolean | `false` | 프로그레스 오버레이 표시 |
| `method` | string | — | HTTP 메서드 오버라이드 |
| `retryCount` | number | `0` | 재시도 카운트 (내부용) |

이 외의 키는 무시됩니다.

### 폼 제출 (선언형)

HTML에 `data-*` 속성을 선언하면 MubloRequest가 자동으로 처리합니다.

```html
<form action="#">
    <input name="formData[title]" value="...">
    <input name="formData[content]" value="...">

    <button type="button"
            class="mublo-submit"
            data-target="/admin/board/write"
            data-callback="afterWrite"
            data-loading="true"
            data-confirm="저장하시겠습니까?">
        저장
    </button>
</form>
```

| 속성 | 필수 | 설명 |
|------|------|------|
| `class="mublo-submit"` | O | 이벤트 위임으로 자동 감지 |
| `data-target` | O | 요청 URL |
| `data-callback` | — | 성공 후 실행할 콜백 이름 |
| `data-container` | — | 콜백에 전달할 컨테이너 ID |
| `data-loading` | — | `"true"` 시 프로그레스 표시 |
| `data-confirm` | — | 확인 다이얼로그 메시지 |

### 파일 업로드 (sendRequest)

FormData를 직접 구성하여 전송합니다.

```javascript
var formData = new FormData();
formData.append('file', fileInput.files[0]);

MubloRequest.sendRequest({
    method: 'POST',
    url: '/upload',
    payloadType: MubloRequest.PayloadType.FORM,
    data: formData,
    loading: true
}).then(function(res) {
    console.log(res.data.url);
});
```

### 콜백과 렌더러

반복적인 후처리를 이름으로 등록해두고, 폼 제출 결과에서 호출합니다.

```javascript
// 콜백 등록
MubloRequest.registerCallback('afterWrite', function(result, containerId) {
    alert(result.message);
    location.href = '/board/list';
});

// 렌더러 등록 (데이터 → DOM)
MubloRequest.registerRenderer('productList', function(container, data) {
    document.getElementById(container).innerHTML =
        data.items.map(renderItem).join('');
});
```

### 유틸리티

| 메서드 | 설명 |
|--------|------|
| `getCsrfToken()` | CSRF 토큰 반환 (Promise) |
| `resetCsrfToken()` | 토큰 캐시 초기화 |
| `syncAllEditors()` | MubloEditor/CKEditor/TinyMCE 동기화 |
| `escapeHtml(str)` | HTML 이스케이프 |
| `debounce(fn, wait)` | 디바운스 |
| `throttle(fn, limit)` | 쓰로틀 |
| `configure(options)` | 런타임 설정 변경 |

### 에러 처리 흐름

```
서버 응답
  |
  +-- HTTP 에러 (4xx, 5xx)
  |     +-- 419/503 → 자동 재시도 (최대 3회, 지수 백오프)
  |     +-- 기타 → alert 자동 표시 → .catch()
  |
  +-- HTTP 200
        +-- result: 'success' → .then()
        +-- result: 'error'  → alert 자동 표시 → .catch()
```

`.then()`에 도달하면 반드시 성공 응답입니다.

### 서버 응답 형식

Controller에서 `JsonResponse`를 사용합니다.

```php
// 성공
JsonResponse::success($data, '처리 완료');
// → { "result": "success", "message": "처리 완료", "data": { ... } }

// 실패
JsonResponse::error('오류 메시지');
// → { "result": "error", "message": "오류 메시지" }
```

### 피해야 할 패턴

**onSuccess/onError 콜백** — 작동하지 않습니다.

```javascript
// --- 잘못된 코드 ---
MubloRequest.requestJson('/api/test', data, {
    onSuccess: function(res) { ... },  // 무시됨
    onError: function(err) { ... }     // 무시됨
});

// --- 올바른 코드 ---
MubloRequest.requestJson('/api/test', data)
    .then(function(res) { ... })
    .catch(function(err) { ... });
```

**result 중복 체크** — `.then()`은 이미 성공이 보장됩니다.

```javascript
// --- 불필요 ---
.then(function(res) {
    if (res.result === 'success') { ... }  // 항상 true
});

// --- 간결한 코드 ---
.then(function(res) {
    alert(res.message);  // 성공 확정
});
```

**존재하지 않는 메서드** — `MubloRequest.request()`는 없습니다. `requestJson()` 또는 `requestQuery()`를 사용하세요.

### 설정 커스터마이징

```javascript
MubloRequest.configure({
    debug: true,                      // 디버그 로그 출력
    timeout: 60000,                   // 타임아웃 60초
    maxFileSize: 20 * 1024 * 1024,    // 파일 크기 제한 20MB
    strictResponseFormat: true,       // result/message 필드 강제
    preventDuplicateRequests: true,   // 중복 요청 방지
    errorHandler: function(info) {    // 커스텀 에러 핸들러
        myToast.show(info.message);
    }
});
```

---

## MubloModal

인스턴스 기반 모달 시스템입니다. CSS는 첫 사용 시 자동 로드됩니다.

### 기본 사용법

```javascript
const modal = new MubloModal({
    id: 'myModal',           // 고유 ID (생략 시 자동 생성)
    title: '상품 상세',       // 빈 문자열이면 헤더 생략
    content: '<p>본문</p>',   // HTML 문자열
    className: 'modal-lg',   // 사이즈 클래스
    footer: '<button class="btn btn-primary closex">닫기</button>'
});
modal.open();
```

### 생성자 옵션

| 옵션 | 타입 | 기본값 | 설명 |
|------|------|--------|------|
| `id` | string | 자동 생성 | 모달 고유 ID |
| `title` | string | `''` | 제목 (빈 문자열이면 헤더 생략) |
| `className` | string | `''` | 추가 CSS 클래스 |
| `content` | string | `''` | 본문 HTML |
| `url` | string | `null` | 원격 콘텐츠 URL (MubloRequest 필요) |
| `footer` | string | `''` | 푸터 HTML |
| `onBeforeOpen` | Function | — | 열기 전 콜백 (`false` 반환 시 취소) |
| `onAfterOpen` | Function | — | 열린 후 콜백 |
| `onBeforeClose` | Function | — | 닫기 전 콜백 (`false` 반환 시 취소) |

### 사이즈 클래스

| className | 최대 너비 |
|-----------|-----------|
| (기본) | 600px |
| `modal-sm` | 400px |
| `modal-lg` | 800px |
| `modal-xl` | 1100px |
| `modal-full` | 화면 전체 |

### 인스턴스 메서드

```javascript
modal.open();              // 모달 열기
modal.close();             // 모달 닫기 (애니메이션 후 제거)
modal.setContent(html);    // 본문 교체
modal.setLoading(true);    // 로딩 스피너 표시
```

### 원격 콘텐츠 로드

`url`을 지정하면 모달이 열릴 때 자동으로 데이터를 가져옵니다. MubloRequest.js가 필요합니다.

```javascript
const modal = new MubloModal({
    title: '약관 보기',
    url: '/api/terms?type=privacy',   // GET 요청, result.data.html 을 본문에 표시
    className: 'modal-lg'
});
modal.open();
// 로딩 스피너 → 데이터 로드 → 본문 자동 교체
```

서버 응답의 `data.html` 또는 `data`가 본문으로 삽입됩니다.

### 정적 메서드 — alert

간단한 알림창입니다. 인스턴스를 반환합니다.

```javascript
MubloModal.alert('저장되었습니다.');
MubloModal.alert('삭제할 수 없습니다.', '오류');  // 제목 지정
```

### 정적 메서드 — confirm

확인/취소 다이얼로그입니다. `Promise<boolean>`을 반환합니다.

```javascript
const ok = await MubloModal.confirm('정말 삭제하시겠습니까?');
if (ok) {
    MubloRequest.requestJson('/admin/item/delete', { id: 123 })
        .then(function() { location.reload(); });
}
```

`async/await` 없이도 사용 가능합니다.

```javascript
MubloModal.confirm('삭제하시겠습니까?', '삭제 확인').then(function(ok) {
    if (!ok) return;
    // 삭제 처리
});
```

### 닫기 동작

다음 요소를 클릭하면 모달이 자동으로 닫힙니다 (이벤트 위임).

- `.closex` 클래스를 가진 요소 (헤더 X 버튼, 푸터 버튼 등)
- `.customModal` 오버레이 (모달 바깥 영역)
- `.layer_btn_close` 클래스를 가진 요소

```html
<!-- 푸터에 닫기 버튼 추가 -->
<button class="btn btn-secondary closex">닫기</button>

<!-- 본문 안에서도 닫기 가능 -->
<a href="#" class="closex">창 닫기</a>
```

### 라이프사이클 콜백

```javascript
const modal = new MubloModal({
    title: '편집',
    content: '<form id="editForm">...</form>',
    onBeforeOpen: function() {
        // false 반환 시 열지 않음
        if (!hasPermission) return false;
    },
    onAfterOpen: function() {
        // DOM이 준비된 후 실행
        document.getElementById('editForm').querySelector('input').focus();
    },
    onBeforeClose: function() {
        // false 반환 시 닫지 않음
        if (hasUnsavedChanges) {
            return confirm('변경사항을 버리시겠습니까?');
        }
    }
});
```

### 실전 예시 — 커스텀 폼 모달

```javascript
function openEditModal(itemId) {
    const modal = new MubloModal({
        id: 'editModal',
        title: '항목 수정',
        className: 'modal-lg',
        url: '/admin/item/edit-form?id=' + itemId,
        footer: `
            <button type="button" class="btn btn-secondary closex">취소</button>
            <button type="button" class="btn btn-primary" id="btn-edit-save">저장</button>
        `,
        onAfterOpen: function() {
            document.getElementById('btn-edit-save')?.addEventListener('click', function() {
                var form = document.getElementById('editModal').querySelector('form');
                if (!form) return;

                var formData = new FormData(form);
                MubloRequest.sendRequest({
                    method: 'POST',
                    url: '/admin/item/edit',
                    payloadType: MubloRequest.PayloadType.FORM,
                    data: formData,
                    loading: true
                }).then(function() {
                    modal.close();
                    location.reload();
                });
            });
        }
    });
    modal.open();
}
```

### 복수 모달

여러 모달을 동시에 띄울 수 있습니다. ID만 다르면 됩니다.

```javascript
const modal1 = new MubloModal({ id: 'list', title: '목록', ... });
const modal2 = new MubloModal({ id: 'detail', title: '상세', ... });
modal1.open();
modal2.open();  // modal1 위에 표시
```

스크롤 잠금은 모든 모달이 닫힌 후 자동 해제됩니다.

---

## CSRF 토큰

두 모듈 모두 CSRF 토큰을 자동으로 처리합니다.

- **MubloRequest** — 모든 요청에 `X-CSRF-Token` 헤더를 자동 첨부합니다
- **MubloModal** — 원격 로드 시 MubloRequest를 통해 자동 처리됩니다

개발자가 직접 토큰을 다룰 필요는 없습니다. `fetch`를 직접 사용해야 하는 경우에만 수동으로 획득합니다.

```javascript
const token = await MubloRequest.getCsrfToken();
fetch('/api/custom', {
    headers: { 'X-CSRF-Token': token }
});
```

---

[< 개발자 가이드](README.md)
