/**
 * ============================================================
 * MubloRequest.js
 * (c) 2025 Mublo
 * Author: Mublo
 * ============================================================
 *
 * MubloRequest 는
 * Mublo 프레임워크 전반(Front / Admin 공용)에서 사용하는
 * **클라이언트 사이드 공통 코어 모듈**이다.
 *
 * 이 파일은
 * "AJAX 요청 + CSRF 관리 + 로딩 UX + 콜백 + 렌더러"
 * 를 하나의 일관된 규칙으로 통합한다.
 *
 * ------------------------------------------------------------
 * 핵심 설계 철학
 * ------------------------------------------------------------
 *
 * 1. HTML 은 선언만 한다
 *    - data-* 속성으로 의도만 표현
 *    - JS 로직을 HTML에 직접 쓰지 않는다
 *
 * 2. JS 는 해석만 한다
 *    - 버튼 클래스 / data-* 규칙을 해석하여 동작 수행
 *    - 개별 페이지 로직에 의존하지 않는다
 *
 * 3. 요청 방식은 명확히 구분한다
 *    - JSON      : application/json
 *    - FORM      : FormData
 *    - QUERY     : GET QueryString
 *
 * ------------------------------------------------------------
 * 주요 기능 요약
 * ------------------------------------------------------------
 *
 * [1] 공통 Ajax 요청 엔진
 *  - sendRequest()
 *  - PayloadType(JSON / FORM / QUERY) 기반 전송
 *  - CSRF 자동 첨부
 *  - 재시도 / 타임아웃 / AbortController 지원
 *
 * [2] 폼 자동 제출 처리
 *  - .mublo-submit 클래스를 가진 버튼 자동 감지
 *  - <form> + FormData 기반 전송
 *  - 에디터(MubloEditor / CKEditor / TinyMCE) 자동 동기화
 *
 * [3] 전역 로딩 UX 관리
 *  - 다중 요청 대응
 *  - Progress Overlay 자동 표시/해제
 *
 * [4] 콜백 & 렌더러 시스템
 *  - registerCallback / executeCallback
 *  - registerRenderer / render
 *  - 서버 응답(JSON)과 화면 렌더링 로직 분리
 *
 * ------------------------------------------------------------
 * 자동 초기화 동작
 * ------------------------------------------------------------
 *
 * DOMContentLoaded 시 자동 실행:
 *  - Progress 스타일 삽입
 *  - Progress 엘리먼트 생성
 *  - 버튼 이벤트 위임 등록
 *
 * 별도의 init 호출 없이도 기본 동작한다.
 *
 * ------------------------------------------------------------
 * HTML 사용 예시 (폼 제출)
 * ------------------------------------------------------------
 *
 * <button
 *   class="mublo-submit"
 *   data-target="/api/v1/board/write"
 *   data-callback="afterWrite"
 *   data-container="list-area"
 *   data-loading="true">
 *   저장
 * </button>
 *
 * ------------------------------------------------------------
 * JS 직접 호출 예시 (JSON)
 * ------------------------------------------------------------
 *
 * MubloRequest.requestJson('/api/v1/goods/getList', {
 *   page: 1
 * }, {
 *   loading: true
 * });
 *
 * ------------------------------------------------------------
 * 서버 응답 기본 형식 (권장)
 * ------------------------------------------------------------
 *
 * {
 *   result  : "success" | "error",
 *   message : "처리 결과 메시지",
 *   data    : {
 *     // 렌더러에서 사용할 실제 데이터
 *   }
 * }
 *
 * ※ data 구조는 렌더러별로 자유롭게 정의 가능
 *
 * ============================================================
 
 */

const MubloRequest = (() => {
    /* =========================================================
     * Payload Type 정의
     * ========================================================= */
    const PayloadType = {
        JSON: 'json',   // application/json
        FORM: 'form',   // FormData
        QUERY: 'query', // GET query string
    };

    let cachedCsrfToken = null;
    let csrfPromise = null;
    let activeRequestCount = 0;
    const callbacks = {};
    const renderers = {};
    const pendingRequests = new Map();

    const config = {
        apiBaseUrl: window.API_BASE_URL || '/api/v1',
        csrfTokenEndpoint: '/csrf/token',
        timeout: 30000,
        maxRetries: 3,
        retryableStatuses: [419, 503],
        maxFileSize: 10 * 1024 * 1024,
        strictResponseFormat: false,
        preventDuplicateRequests: false,
        debug: false,
        errorHandler: null,
        responseInterceptor: null,
        validationErrorDisplay: false,
        progressElement: null,
        showProgress: null,
        formValidator: null,
        onRequestStart: null,
        onRequestComplete: null,

        log(...args) {
            if (this.debug) {
                console.log('[MubloRequest]', ...args);
            }
        }
    };

    // -----------------------------------
    // 유틸리티 함수
    // -----------------------------------

    const debounce = (func, wait) => {
        let timeout;
        const debounced = function executedFunction(...args) {
            const later = () => {
                timeout = null;
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };

        debounced.cancel = () => {
            clearTimeout(timeout);
            timeout = null;
        };

        return debounced;
    };

    const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

    const throttle = (func, limit) => {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    };

    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        const div = document.createElement('div');
        div.textContent = unsafe;
        return div.innerHTML;
    }

    // -----------------------------------
    // 초기화
    // -----------------------------------
    function init() {
        addProgressStyles();
        addProgressElement();
        document.addEventListener('click', handleButtonClick);
        config.log('Initialized');
    }

    function addProgressStyles() {
        const style = document.createElement('style');
        style.textContent = `
            #progress { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 9999; background: rgba(0, 0, 0, 0.25); }
            #progress:after { content: ""; position: fixed; top: calc(50% - 30px); left: calc(50% - 30px); border: 6px solid rgba(var(--bs-body-color-rgb), 0.5); border-top-color: rgba(var(--bs-body-color-rgb), 0.25); border-bottom-color: rgba(var(--bs-body-color-rgb), 0.25); border-radius: 50%; width: 60px; height: 60px; animation: spin 1s linear infinite; }
            @keyframes spin { 0%{transform:rotate(0);}100%{transform:rotate(360deg);} }
        `;
        document.head.appendChild(style);
    }

    function addProgressElement() {
        if (!document.getElementById('progress') && !config.progressElement) {
            const el = document.createElement('div');
            el.id = 'progress';
            document.body.appendChild(el);
        }
    }

    function showProgressElement() {
        if (config.showProgress && typeof config.showProgress === 'function') {
            config.showProgress(true);
            return;
        }
        const el = config.progressElement || document.getElementById('progress');
        if (el) el.style.display = 'block';
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        document.body.style.overflow = 'hidden';
        document.body.style.paddingRight = scrollbarWidth + 'px';
    }

    function hideProgressElement() {
        if (config.showProgress && typeof config.showProgress === 'function') {
            config.showProgress(false);
            return;
        }
        const el = config.progressElement || document.getElementById('progress');
        if (el) el.style.display = 'none';
        document.body.style.overflow = '';
        document.body.style.paddingRight = '';
    }

    function toggleProgress(show = true) {
        if (show) {
            activeRequestCount++;
            if (activeRequestCount === 1) {
                showProgressElement();
            }
        } else {
            activeRequestCount = Math.max(0, activeRequestCount - 1);
            if (activeRequestCount === 0) {
                hideProgressElement();
            }
        }
    }

    // -----------------------------------
    // CSRF 토큰 관리
    // -----------------------------------
    async function getCsrfToken() {
        // 1. 이미 캐시된 토큰이 있으면 즉시 반환
        if (cachedCsrfToken) {
            config.log('Using cached CSRF token');
            return cachedCsrfToken;
        }
        
        // 2. 현재 토큰을 가져오는 중이라면 진행 중인 Promise를 반환
        if (csrfPromise) {
            config.log('Waiting for pending CSRF fetch...');
            return csrfPromise;
        }

        // 3. 토큰도 없고 진행 중인 요청도 없다면 새로 요청 시작
        csrfPromise = (async () => {
            try {
                const url = `${config.apiBaseUrl}${config.csrfTokenEndpoint}`;
                config.log('Fetching CSRF token from:', url);

                const res = await fetch(url);
                if (!res.ok) throw new Error(`CSRF fetch failed: ${res.status}`);
                
                const json = await res.json();
                if (!json || !json.data?.token) throw new Error('Invalid CSRF response');

                cachedCsrfToken = json.data.token;
                config.log('CSRF token cached');
                return cachedCsrfToken;
            } catch (err) {
                console.error('[MubloRequest] CSRF Token Fetch Error:', err);
                throw err;
            } finally {
                // 요청이 성공하든 실패하든 대기열(Promise)은 비워줌
                csrfPromise = null;
            }
        })();

        return csrfPromise;
    }

    function resetCsrfToken() {
        config.log('Resetting CSRF token');
        cachedCsrfToken = null;
        csrfPromise = null;
    }

    // -----------------------------------
    // 에디터 내용 동기화
    // -----------------------------------

    function syncAllEditors() {
        // MubloEditor 동기화 (우선 처리)
        if (typeof MubloEditor !== 'undefined' && MubloEditor.syncAll) {
            try {
                MubloEditor.syncAll();
                config.log('Synced all MubloEditor instances');
            } catch (e) {
                console.warn('[MubloEditor Sync Error]:', e);
            }
        }

        // 기존 에디터 동기화 (SmartEditor2, CKEditor, TinyMCE)
        const editors = document.querySelectorAll('.editor-form, textarea[id^="wr_content"], textarea.smarteditor2');

        const syncStrategies = [
            {
                check: (id) => window.oEditors?.getById?.[id],
                sync: (id) => window.oEditors.getById[id].exec("UPDATE_CONTENTS_FIELD", []),
                name: 'SmartEditor2'
            },
            {
                check: (id) => window.CKEDITOR?.instances?.[id],
                sync: (id) => window.CKEDITOR.instances[id].updateElement(),
                name: 'CKEditor'
            },
            {
                check: (id) => window.tinymce?.get(id),
                sync: (id) => window.tinymce.get(id).save(),
                name: 'TinyMCE'
            }
        ];

        editors.forEach(ed => {
            const editorId = ed.id;
            if (!editorId) return;

            for (const strategy of syncStrategies) {
                try {
                    if (strategy.check(editorId)) {
                        strategy.sync(editorId);
                        config.log(`Synced ${strategy.name} editor:`, editorId);
                        break;
                    }
                } catch (e) {
                    console.warn(`[${strategy.name} Sync Error] ${editorId}:`, e);
                }
            }
        });
    }

    // -----------------------------------
    // FormData 검증
    // -----------------------------------

    function validateFormData(formData) {
        if (!(formData instanceof FormData)) {
            throw new Error('FORM payload requires FormData');
        }

        for (let [key, value] of formData.entries()) {
            if (value instanceof File) {
                config.log(`File detected: ${key} = ${value.name} (${value.size} bytes)`);

                if (value.size > config.maxFileSize) {
                    const maxSizeMB = (config.maxFileSize / 1024 / 1024).toFixed(1);
                    const fileSizeMB = (value.size / 1024 / 1024).toFixed(1);
                    throw new Error(
                        `파일 "${value.name}" 크기(${fileSizeMB}MB)가 최대 허용 크기(${maxSizeMB}MB)를 초과합니다.`
                    );
                }
            }
        }
    }

    // -----------------------------------
    // 응답 데이터 유효성 검사
    // -----------------------------------
    
    function validateResponse(json) {
        // 1. 기본 객체 여부 확인
        if (!json || typeof json !== 'object') {
            throw new Error('서버 응답이 올바른 객체 형식이 아닙니다.');
        }

        // 2. 서버에서 정의한 공통 응답 규격 확인 (result: success/error)
        // 머블로 프레임워크의 표준 규격을 강제하거나 권장합니다.
        const hasResult = 'result' in json;
        const isSuccess = json.result === 'success';

        if (config.strictResponseFormat) {
            if (!hasResult) {
                throw new Error('응답 규격 위반: "result" 필드가 누락되었습니다.');
            }
            if (!('message' in json)) {
                console.warn('[MubloRequest] "message" 필드가 누락되었습니다.');
            }
        }

        // 3. 비즈니스 로직 에러 처리
        // HTTP 상태 코드는 200(OK)이지만, 결과가 'error'인 경우를 처리합니다.
        if (hasResult && !isSuccess) {
            const businessError = new Error(json.message || '요청 처리 중 오류가 발생했습니다.');
            businessError.status = json.status || 200; // 응답 내 별도 상태코드가 있다면 활용
            businessError.response = json;
            throw businessError;
        }

        // 4. 데이터 필드 보장 (Optional)
        // 렌더러에서 data.items 등을 쓸 때 undefined 에러 방지를 위해 기본값 할당
        if (isSuccess && !json.data) {
            json.data = {};
        }

        return json;
    }

    // -----------------------------------
    // 공통 Ajax 요청 처리
    // -----------------------------------

    async function sendRequest({
        method = 'GET',
        url,
        payloadType = PayloadType.JSON,
        data = null,
        loading = false,
        retryCount = 0,
    }) {
        const requestKey = `${method}:${url}`;

        if (config.preventDuplicateRequests && pendingRequests.has(requestKey)) {
            const error = new Error('Duplicate request prevented');
            error.isDuplicate = true;
            throw error;
        }

        const controller = new AbortController();
        pendingRequests.set(requestKey, controller);

        const timeoutId = setTimeout(() => {
            config.log('Request timeout:', url);
            controller.abort();
        }, config.timeout);

        try {
            const csrfToken = await getCsrfToken();

            const options = {
                method,
                headers: {
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                signal: controller.signal,
            };

            if (method !== 'GET') {
                if (payloadType === PayloadType.JSON) {
                    options.headers['Content-Type'] = 'application/json';
                    options.body = JSON.stringify(data ?? {});
                    config.log('Request payload (JSON):', data);
                }

                if (payloadType === PayloadType.FORM) {
                    validateFormData(data);
                    options.body = data;
                    config.log('Request payload (FormData)');
                }
            } else if (payloadType === PayloadType.QUERY && data) {
                const params = new URLSearchParams(data);
                url += (url.includes('?') ? '&' : '?') + params.toString();
                config.log('Request payload (Query):', data);
            }

            config.log('Sending request:', { method, url, payloadType, retryCount });

            if (config.onRequestStart && typeof config.onRequestStart === 'function') {
                config.onRequestStart({ method, url, payloadType });
            }

            if (config.validationErrorDisplay) clearValidationErrors();

            if (loading) toggleProgress(true);

            const response = await fetch(url, options);

            let json = null;
            const contentType = response.headers.get('content-type') || '';


            if (contentType.includes('application/json')) {
                try {
                    json = await response.json();
                } catch (e) {
                    json = {
                        result: 'error',
                        message: 'Invalid JSON response',
                    };
                }
            } else {
                // JSON이 아닌 응답 (HTML, text 등)
                const text = await response.text();
                json = {
                    result: 'error',
                    message: 'Non-JSON response received',
                    raw: text,
                };
            }

            if (config.onRequestComplete && typeof config.onRequestComplete === 'function') {
                config.onRequestComplete({ method, url, payloadType, status: response.status });
            }

            if (!response.ok) {
                if (
                    config.retryableStatuses.includes(response.status) &&
                    retryCount < config.maxRetries
                ) {
                    config.log(`Retrying request (${retryCount + 1}/${config.maxRetries}):`, url);

                    if (response.status === 419) resetCsrfToken();

                    const backoffDelay = Math.pow(2, retryCount) * 1000;
                    await delay(backoffDelay);

                    return sendRequest({
                        method,
                        url,
                        payloadType,
                        data,
                        loading,
                        retryCount: retryCount + 1,
                    });
                }

                const error = new Error(json.message || 'Request failed');
                error.status = response.status;
                error.url = url;
                error.response = json;
                throw error;
            }

            // Response Interceptor 적용
            if (config.responseInterceptor && typeof config.responseInterceptor === 'function') {
                json = config.responseInterceptor(json, response);
                config.log('Response interceptor applied');
            }

            return validateResponse(json);

        } catch (e) {
            if (e.name === 'AbortError') {
                config.log('Request aborted:', url);
                const error = new Error('Request timeout');
                error.isTimeout = true;
                error.url = url;
                handleError(error);
                throw error;
            }

            if (!e.isDuplicate) {
                handleError(e);
            }
            throw e;
        } finally {
            clearTimeout(timeoutId);
            pendingRequests.delete(requestKey);
            if (loading) toggleProgress(false);
        }
    }

    // -----------------------------------
    // 폼 기반 Ajax 요청
    // -----------------------------------

    async function submitForm(button) {
        const form = button.closest('form');
        const url = button.dataset.target;
        const callback = button.dataset.callback;
        const containerId = button.dataset.container;
        const loading = button.dataset.loading === 'true';

        if (!form || !url) {
            console.warn('[MubloRequest] Form or target URL not found');
            return;
        }

        config.log('Submitting form:', { url, callback, containerId, loading });

        syncAllEditors();

        if (config.formValidator && !config.formValidator(form)) {
            config.log('Form validation failed');
            return;
        }

        button.disabled = true;

        try {
            const formData = new FormData(form);

            const result = await sendRequest({
                method: 'POST',
                url,
                payloadType: PayloadType.FORM,
                data: formData,
                loading,
            });

            if (callback) {
                await executeCallback(callback, result, containerId);
            }
        } catch (e) {
            config.log('Form submission error:', e);
        } finally {
            button.disabled = false;
        }
    }

    const debouncedSubmitForm = debounce(submitForm, 300);

    function requestJson(url, data = {}, options = {}) {
        return sendRequest({
            method: 'POST',
            url,
            payloadType: PayloadType.JSON,
            data,
            ...options,
        });
    }

    function requestQuery(url, params = {}, options = {}) {
        return sendRequest({
            method: 'GET',
            url,
            payloadType: PayloadType.QUERY,
            data: params,
            ...options,
        });
    }

    // -----------------------------------
    // 공통 에러 핸들러
    // -----------------------------------

    function createErrorInfo(error) {
        return {
            message: error?.message || '요청 처리 중 오류가 발생했습니다.',
            status: error?.status,
            url: error?.url,
            isTimeout: error?.isTimeout || false,
            isDuplicate: error?.isDuplicate || false,
            timestamp: new Date().toISOString(),
            response: error?.response,
        };
    }

    function handleError(error) {
        const errorInfo = createErrorInfo(error);

        console.error('[MubloRequest Error]', errorInfo);

        if (config.errorHandler && typeof config.errorHandler === 'function') {
            config.errorHandler(errorInfo);
            return;
        }

        if (error?.isDuplicate) {
            return;
        }

        if (error?.isTimeout) {
            showAlert('요청 시간이 초과되었습니다. 다시 시도해주세요.', 'warning');
            return;
        }

        switch (error?.status) {
            case 401:
                showAlert('로그인이 필요합니다.', 'warning', {
                    buttonText: '로그인',
                    onClose: function() { location.href = '/login'; }
                });
                break;

            case 403:
                showAlert('접근 권한이 없습니다.', 'error');
                break;

            case 404:
                showAlert('요청한 리소스를 찾을 수 없습니다.', 'warning');
                break;

            case 419:
                showAlert('세션이 만료되었습니다.', 'warning', {
                    buttonText: '새로고침',
                    onClose: function() { location.reload(); }
                });
                break;

            case 422:
                if (config.validationErrorDisplay && error?.response?.data?.errors) {
                    displayValidationErrors(error.response.data.errors);
                } else {
                    showAlert(errorInfo.message || '입력 데이터가 올바르지 않습니다.', 'warning');
                }
                break;

            case 500:
            case 503:
                showAlert('서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', 'error');
                break;

            default:
                showAlert(errorInfo.message, 'error');
        }
    }

    // -----------------------------------
    // 422 유효성 오류 자동 매핑
    // -----------------------------------

    function clearValidationErrors() {
        document.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
        });
        document.querySelectorAll('.invalid-feedback[data-mublo-validation]').forEach(el => {
            el.remove();
        });
    }

    function displayValidationErrors(errors) {
        clearValidationErrors();

        if (!errors || typeof errors !== 'object') return;

        let firstErrorElement = null;

        Object.entries(errors).forEach(([field, messages]) => {
            const message = Array.isArray(messages) ? messages[0] : messages;

            // formData[field] 또는 field 형태로 매칭
            const selectors = [
                `[name="${field}"]`,
                `[name="formData[${field}]"]`,
                `[name="${field}[]"]`,
                `[name="formData[${field}][]"]`,
            ];

            let input = null;
            for (const sel of selectors) {
                input = document.querySelector(sel);
                if (input) break;
            }

            if (!input) return;

            input.classList.add('is-invalid');

            // 이미 피드백이 있으면 스킵
            if (input.parentElement.querySelector('.invalid-feedback[data-mublo-validation]')) return;

            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.setAttribute('data-mublo-validation', '1');
            feedback.style.display = 'block';
            feedback.textContent = message;
            input.parentElement.appendChild(feedback);

            if (!firstErrorElement) firstErrorElement = input;
        });

        if (firstErrorElement) {
            firstErrorElement.focus();
            firstErrorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // -----------------------------------
    // 콜백 & 렌더러 시스템
    // -----------------------------------

    function registerCallback(name, fn, options = { override: true, exposeGlobally: true }) {
        if (typeof fn !== 'function') {
            console.error(`[MubloRequest] Invalid callback function: ${name}`);
            return false;
        }

        if (callbacks[name] && !options.override) {
            console.warn(`[MubloRequest] Callback already exists: ${name}`);
            return false;
        }

        callbacks[name] = fn;
        config.log(`Callback registered: ${name}`);

        if (options.exposeGlobally && (!window[name] || options.override)) {
            window[name] = fn;
        }

        return true;
    }

    async function executeCallback(name, data, containerId) {
        config.log(`Executing callback: ${name}`, { data, containerId });

        if (callbacks[name]) return await callbacks[name](data, containerId);
        if (typeof window[name] === 'function') return await window[name](data, containerId);

        console.warn(`콜백 [${name}]를 찾을 수 없습니다.`);
    }

    function registerRenderer(name, fn) {
        if (typeof fn !== 'function') {
            console.error('[MubloRequest] Invalid renderer function');
            return false;
        }

        renderers[name] = fn;
        config.log(`Renderer registered: ${name}`);

        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            console.info(`[MubloRequest] Renderer '${name}' registered. Remember to sanitize HTML!`);
        }

        return true;
    }

    function render(name, container, data, ...args) {
        config.log(`Rendering: ${name}`, { container, data });

        if (renderers[name]) return renderers[name](container, data, ...args);
        console.warn(`렌더러 [${name}]를 찾을 수 없습니다.`);
    }

    // -----------------------------------
    // 이벤트 위임
    // -----------------------------------

    function handleButtonClick(e) {
        // 폼 컨트롤 요소 클릭은 무시 (파일 선택, 셀렉트 등 정상 동작 보장)
        const tag = e.target.tagName;
        if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA' || tag === 'LABEL' || tag === 'OPTION') {
            return;
        }

        const btn = e.target.closest('.mublo-submit');
        if (btn) {
            e.preventDefault();
            config.log('Submit button clicked:', btn.dataset.target);

            // data-confirm 처리: 커스텀 모달 사용
            const confirmMessage = btn.dataset.confirm;
            if (confirmMessage) {
                showConfirm(confirmMessage.replace(/\\n/g, '\n'), function() {
                    debouncedSubmitForm(btn);
                }, { type: 'warning' });
                return;
            }

            debouncedSubmitForm(btn);
        }
    }

    // -----------------------------------
    // 메모리 정리 함수
    // -----------------------------------

    function destroy() {
        config.log('Destroying MubloRequest');

        document.removeEventListener('click', handleButtonClick);

        if (debouncedSubmitForm.cancel) {
            debouncedSubmitForm.cancel();
        }

        pendingRequests.forEach((controller) => {
            controller.abort();
        });
        pendingRequests.clear();

        const progressEl = document.getElementById('progress');
        if (progressEl) progressEl.remove();

        cachedCsrfToken = null;
        activeRequestCount = 0;

        config.log('Destroyed');
    }

    // -----------------------------------
    // 설정 변경 함수
    // -----------------------------------

    function configure(options) {
        Object.assign(config, options);
        config.log('Configuration updated:', options);
    }

    function getConfig() {
        return { ...config };
    }

    // -----------------------------------
    // Toast 알림
    // -----------------------------------

    let _toastContainer = null;

    function _ensureToastContainer() {
        if (_toastContainer && document.body.contains(_toastContainer)) return _toastContainer;
        _toastContainer = document.createElement('div');
        _toastContainer.id = 'mublo-toast-container';
        _toastContainer.setAttribute('aria-live', 'polite');
        document.body.appendChild(_toastContainer);

        if (!document.getElementById('mublo-toast-style')) {
            const style = document.createElement('style');
            style.id = 'mublo-toast-style';
            style.textContent = `
#mublo-toast-container{position:fixed;top:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;pointer-events:none}
.mublo-toast{pointer-events:auto;display:flex;align-items:center;gap:10px;padding:12px 20px;border-radius:8px;font-size:14px;font-weight:500;color:#fff;box-shadow:0 4px 12px rgba(0,0,0,.15);opacity:0;transform:translateX(40px);transition:opacity .3s,transform .3s;max-width:400px;word-break:keep-all}
.mublo-toast--visible{opacity:1;transform:translateX(0)}
.mublo-toast--success{background:#198754}
.mublo-toast--error{background:#dc3545}
.mublo-toast--info{background:#0d6efd}
.mublo-toast--warning{background:#fd7e14}
.mublo-toast__icon{flex-shrink:0;font-size:18px}
.mublo-toast__close{margin-left:auto;background:none;border:none;color:rgba(255,255,255,.7);font-size:18px;cursor:pointer;padding:0 0 0 8px;line-height:1}
.mublo-toast__close:hover{color:#fff}`;
            document.head.appendChild(style);
        }
        return _toastContainer;
    }

    const _toastIcons = {
        success: '&#10003;',
        error: '&#10007;',
        info: '&#8505;',
        warning: '&#9888;',
    };

    /**
     * 토스트 알림 표시
     * @param {string} message
     * @param {'success'|'error'|'info'|'warning'} type
     * @param {number} duration ms (기본 3000)
     */
    function showToast(message, type, duration) {
        type = type || 'info';
        duration = duration || 3000;
        const container = _ensureToastContainer();

        const toast = document.createElement('div');
        toast.className = 'mublo-toast mublo-toast--' + type;
        toast.innerHTML =
            '<span class="mublo-toast__icon">' + (_toastIcons[type] || '') + '</span>' +
            '<span>' + escapeHtml(message) + '</span>' +
            '<button class="mublo-toast__close" type="button">&times;</button>';

        toast.querySelector('.mublo-toast__close').addEventListener('click', function() { removeToast(toast); });
        container.appendChild(toast);

        requestAnimationFrame(function() {
            requestAnimationFrame(function() { toast.classList.add('mublo-toast--visible'); });
        });

        setTimeout(function() { removeToast(toast); }, duration);
    }

    function removeToast(toast) {
        if (!toast || !toast.parentNode) return;
        toast.classList.remove('mublo-toast--visible');
        setTimeout(function() { toast.remove(); }, 300);
    }

    // -----------------------------------
    // 모달 알림 (alert 대체)
    // -----------------------------------

    /**
     * 중앙 모달 알림
     * @param {string} message
     * @param {'error'|'warning'|'info'|'success'} type
     * @param {object} options  { title, buttonText, onClose }
     */
    function showAlert(message, type, options) {
        type = type || 'info';
        options = options || {};

        // 기존 모달 제거
        var existing = document.getElementById('mublo-alert-overlay');
        if (existing) existing.remove();

        if (!document.getElementById('mublo-alert-style')) {
            var style = document.createElement('style');
            style.id = 'mublo-alert-style';
            style.textContent =
                '#mublo-alert-overlay{position:fixed;inset:0;z-index:10000;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);opacity:0;transition:opacity .2s}' +
                '#mublo-alert-overlay.--visible{opacity:1}' +
                '.mublo-alert{background:#fff;border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.2);max-width:400px;width:90%;padding:28px 24px 20px;text-align:center;transform:scale(.9);transition:transform .2s}' +
                '#mublo-alert-overlay.--visible .mublo-alert{transform:scale(1)}' +
                '.mublo-alert__icon{font-size:40px;margin-bottom:12px;line-height:1}' +
                '.mublo-alert__icon--error{color:#dc3545}' +
                '.mublo-alert__icon--warning{color:#fd7e14}' +
                '.mublo-alert__icon--info{color:#0d6efd}' +
                '.mublo-alert__icon--success{color:#198754}' +
                '.mublo-alert__title{font-size:16px;font-weight:700;color:#212529;margin-bottom:8px}' +
                '.mublo-alert__msg{font-size:14px;color:#495057;line-height:1.6;margin-bottom:20px;word-break:keep-all}' +
                '.mublo-alert__btn{display:inline-block;padding:8px 32px;border:none;border-radius:6px;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .15s}' +
                '.mublo-alert__btn:hover{opacity:.85}' +
                '.mublo-alert__btn--error{background:#dc3545;color:#fff}' +
                '.mublo-alert__btn--warning{background:#fd7e14;color:#fff}' +
                '.mublo-alert__btn--info{background:#0d6efd;color:#fff}' +
                '.mublo-alert__btn--success{background:#198754;color:#fff}';
            document.head.appendChild(style);
        }

        var icons = { error: '&#10007;', warning: '&#9888;', info: '&#8505;', success: '&#10003;' };
        var titles = { error: '오류', warning: '알림', info: '안내', success: '완료' };

        var overlay = document.createElement('div');
        overlay.id = 'mublo-alert-overlay';
        overlay.innerHTML =
            '<div class="mublo-alert">' +
                '<div class="mublo-alert__icon mublo-alert__icon--' + type + '">' + (icons[type] || '') + '</div>' +
                '<div class="mublo-alert__title">' + escapeHtml(options.title || titles[type] || '') + '</div>' +
                '<div class="mublo-alert__msg">' + escapeHtml(message) + '</div>' +
                '<button class="mublo-alert__btn mublo-alert__btn--' + type + '">' + escapeHtml(options.buttonText || '확인') + '</button>' +
            '</div>';

        var closeAlert = function() {
            overlay.classList.remove('--visible');
            setTimeout(function() { overlay.remove(); }, 200);
            if (typeof options.onClose === 'function') options.onClose();
        };

        overlay.querySelector('.mublo-alert__btn').addEventListener('click', closeAlert);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) closeAlert(); });

        document.body.appendChild(overlay);
        requestAnimationFrame(function() {
            requestAnimationFrame(function() { overlay.classList.add('--visible'); });
        });

        // ESC 키로 닫기
        var escHandler = function(e) {
            if (e.key === 'Escape') { closeAlert(); document.removeEventListener('keydown', escHandler); }
        };
        document.addEventListener('keydown', escHandler);
    }

    /**
     * 중앙 확인/취소 모달 (confirm 대체)
     * @param {string} message
     * @param {function} onConfirm 확인 시 콜백
     * @param {object} options { title, confirmText, cancelText, type }
     */
    function showConfirm(message, onConfirm, options) {
        options = options || {};
        var type = options.type || 'info';

        var existing = document.getElementById('mublo-alert-overlay');
        if (existing) existing.remove();

        // showAlert에서 이미 스타일 주입했으므로 재사용
        if (!document.getElementById('mublo-alert-style')) {
            showAlert('', 'info'); // 스타일 주입용
            document.getElementById('mublo-alert-overlay').remove();
        }

        var icons = { error: '&#10007;', warning: '&#9888;', info: '&#8505;', success: '&#10003;' };
        var titles = { error: '확인', warning: '확인', info: '확인', success: '확인' };

        var overlay = document.createElement('div');
        overlay.id = 'mublo-alert-overlay';
        overlay.innerHTML =
            '<div class="mublo-alert">' +
                '<div class="mublo-alert__icon mublo-alert__icon--' + type + '">' + (icons[type] || '') + '</div>' +
                '<div class="mublo-alert__title">' + escapeHtml(options.title || titles[type]) + '</div>' +
                '<div class="mublo-alert__msg">' + escapeHtml(message) + '</div>' +
                '<div style="display:flex;gap:8px;justify-content:center">' +
                    '<button class="mublo-alert__btn" style="background:#6c757d;color:#fff" data-role="cancel">' + escapeHtml(options.cancelText || '취소') + '</button>' +
                    '<button class="mublo-alert__btn mublo-alert__btn--' + type + '" data-role="confirm">' + escapeHtml(options.confirmText || '확인') + '</button>' +
                '</div>' +
            '</div>';

        var close = function() {
            overlay.classList.remove('--visible');
            setTimeout(function() { overlay.remove(); }, 200);
            document.removeEventListener('keydown', escHandler);
        };

        overlay.querySelector('[data-role="cancel"]').addEventListener('click', close);
        overlay.querySelector('[data-role="confirm"]').addEventListener('click', function() {
            close();
            if (typeof onConfirm === 'function') onConfirm();
        });
        overlay.addEventListener('click', function(e) { if (e.target === overlay) close(); });

        var escHandler = function(e) { if (e.key === 'Escape') close(); };
        document.addEventListener('keydown', escHandler);

        document.body.appendChild(overlay);
        requestAnimationFrame(function() {
            requestAnimationFrame(function() { overlay.classList.add('--visible'); });
        });
    }

    // -----------------------------------
    // 외부 노출 API
    // -----------------------------------
    return {
        init,
        sendRequest,
        submitForm,
        requestJson,
        requestQuery,

        registerCallback,
        executeCallback,
        registerRenderer,
        render,

        debounce,
        throttle,
        syncAllEditors,
        escapeHtml,

        configure,
        getConfig,
        destroy,

        getCsrfToken,
        resetCsrfToken,
        clearValidationErrors,

        showToast,
        showAlert,
        showConfirm,

        PayloadType,
    };
})();

document.addEventListener('DOMContentLoaded', () => MubloRequest.init());
