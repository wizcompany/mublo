/**
 * ============================================================
 * MubloModal.js - Mublo Framework 모달 시스템
 * ============================================================
 *
 * 인스턴스 기반 모달 시스템.
 *
 * 의존 (선택): MubloRequest.js (Remote Load 사용 시)
 *
 * 인스턴스 API:
 *   const modal = new MubloModal({ id, title, content, url, ... })
 *   modal.open()
 *   modal.close()
 *   modal.setContent(html)
 *   modal.setLoading(bool)
 *
 * 편의 정적 메서드:
 *   MubloModal.alert(message, title)
 *   MubloModal.confirm(message, title) → Promise<boolean>
 *
 * ============================================================
 */

class MubloModal {

    static _cssLoaded = false;
    static _instances = new Map();

    /**
     * @param {Object} options
     * @param {string} options.id          모달 고유 ID
     * @param {string} [options.title]     모달 제목 (빈 문자열이면 헤더 생략)
     * @param {string} [options.className] 추가 CSS 클래스 (modal-sm, modal-lg, modal-xl, modal-full)
     * @param {string} [options.content]   모달 본문 HTML
     * @param {string} [options.url]       Remote Load URL (MubloRequest 필요)
     * @param {string} [options.footer]    모달 푸터 HTML
     * @param {Function} [options.onBeforeOpen]
     * @param {Function} [options.onAfterOpen]
     * @param {Function} [options.onBeforeClose]
     */
    constructor(options = {}) {
        this.id = options.id || 'mubloModal_' + Date.now();
        this.title = options.title ?? '';
        this.className = options.className || '';
        this.content = options.content || '';
        this.url = options.url || null;
        this.footer = options.footer || '';
        this.onBeforeOpen = options.onBeforeOpen || null;
        this.onAfterOpen = options.onAfterOpen || null;
        this.onBeforeClose = options.onBeforeClose || null;

        this._element = null;
    }

    /* =========================================================
     * 인스턴스 메서드
     * ========================================================= */

    open() {
        if (this.onBeforeOpen && this.onBeforeOpen() === false) return;

        MubloModal._loadCSS();
        this._removeExisting();
        this._createElement();

        if (this.url) {
            this._loadRemote();
        }

        MubloModal._instances.set(this.id, this);
    }

    close() {
        if (!this._element) return;
        if (this.onBeforeClose && this.onBeforeClose() === false) return;

        const content = this._element.querySelector('.customModal-content');
        if (content) content.classList.remove('open');

        setTimeout(() => {
            if (this._element) {
                this._element.style.display = 'none';
                this._element.remove();
                this._element = null;
            }
            MubloModal._instances.delete(this.id);
            MubloModal._updateScrollLock();
        }, 200);
    }

    setContent(html) {
        if (!this._element) return;
        const body = this._element.querySelector('.customModal-body');
        if (body) body.innerHTML = html;
    }

    setLoading(show) {
        if (!this._element) return;
        const body = this._element.querySelector('.customModal-body');
        if (!body) return;

        if (show) {
            body.innerHTML = '<div class="customModal-loading"></div>';
        }
    }

    /* =========================================================
     * 내부 메서드
     * ========================================================= */

    _removeExisting() {
        const existing = document.getElementById(this.id);
        if (existing) existing.remove();
    }

    _createElement() {
        const html = `
            <div id="${this.id}" class="customModal ${this.className}">
                <div class="customModal-dialog">
                    <div class="customModal-content">
                        ${this.title ? `<div class="customModal-header"><div class="header-title">${this.title}</div><button type="button" class="closex"></button></div>` : ''}
                        <div class="customModal-body">${this.url ? '<div class="customModal-loading"></div>' : this.content}</div>
                        ${this.footer ? `<div class="customModal-footer">${this.footer}</div>` : ''}
                    </div>
                </div>
            </div>`;

        document.body.insertAdjacentHTML('beforeend', html);

        this._element = document.getElementById(this.id);
        this._element.style.display = 'flex';

        // 스크롤 위치 보존 후 잠금
        if (!document.documentElement.classList.contains('noscroll')) {
            const scrollY = window.scrollY;
            document.documentElement.style.top = `-${scrollY}px`;
            document.documentElement.classList.add('noscroll');
        }

        // 애니메이션 트리거 (다음 프레임)
        requestAnimationFrame(() => {
            const content = this._element?.querySelector('.customModal-content');
            if (content) content.classList.add('open');
            if (this.onAfterOpen) this.onAfterOpen();
        });
    }

    async _loadRemote() {
        if (typeof MubloRequest === 'undefined') {
            console.error('[MubloModal] Remote Load requires MubloRequest.js');
            this.setContent('<p>MubloRequest.js가 로드되지 않았습니다.</p>');
            return;
        }

        try {
            const result = await MubloRequest.requestQuery(this.url);
            this.setContent(result.data?.html || result.data || '');
        } catch (e) {
            this.setContent('<p>데이터를 불러오지 못했습니다.</p>');
            console.error('[MubloModal] Remote Load Error:', e);
        }
    }

    /* =========================================================
     * 편의 정적 메서드
     * ========================================================= */

    /**
     * 알림 모달
     *
     * @param {string} message  메시지
     * @param {string} [title]  제목 (기본: '알림')
     * @returns {MubloModal}
     */
    static alert(message, title = '알림') {
        const modal = new MubloModal({
            title,
            content: `<p>${message}</p>`,
            className: 'modal-sm',
            footer: '<button type="button" class="btn btn-primary closex">확인</button>',
        });
        modal.open();
        return modal;
    }

    /**
     * 확인 모달 (Promise 반환)
     *
     * @param {string} message  메시지
     * @param {string} [title]  제목 (기본: '확인')
     * @returns {Promise<boolean>}
     */
    static confirm(message, title = '확인') {
        return new Promise((resolve) => {
            const id = 'mubloConfirm_' + Date.now();
            const modal = new MubloModal({
                id,
                title,
                content: `<p>${message}</p>`,
                className: 'modal-sm',
                footer: `
                    <button type="button" class="btn btn-secondary" data-action="cancel">취소</button>
                    <button type="button" class="btn btn-primary" data-action="confirm">확인</button>
                `,
                onBeforeClose: () => {
                    resolve(false);
                },
            });

            modal.open();

            // 버튼 이벤트 바인딩
            const el = document.getElementById(id);
            if (el) {
                el.querySelector('[data-action="confirm"]')?.addEventListener('click', () => {
                    modal.onBeforeClose = null; // cancel 콜백 방지
                    modal.close();
                    resolve(true);
                });
                el.querySelector('[data-action="cancel"]')?.addEventListener('click', () => {
                    modal.onBeforeClose = null;
                    modal.close();
                    resolve(false);
                });
            }
        });
    }

    /* =========================================================
     * CSS 로드 / 스크롤 관리
     * ========================================================= */

    static _loadCSS() {
        if (MubloModal._cssLoaded) return;
        if (document.querySelector('link[href*="components/modal.css"]')) {
            MubloModal._cssLoaded = true;
            return;
        }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.id = 'mubloModalCSS';
        link.href = '/assets/css/components/modal.css';
        document.head.appendChild(link);
        MubloModal._cssLoaded = true;
    }

    static _updateScrollLock() {
        if (!document.querySelector('.customModal')) {
            const scrollY = parseInt(document.documentElement.style.top || '0') * -1;
            document.documentElement.classList.remove('noscroll');
            document.documentElement.style.top = '';
            window.scrollTo(0, scrollY);
        }
    }

    /* =========================================================
     * 이벤트 위임 (닫기)
     * ========================================================= */

    static _initEventDelegation() {
        document.addEventListener('click', function (e) {
            if (
                e.target.classList.contains('closex') ||
                e.target.classList.contains('customModal') ||
                e.target.classList.contains('layer_btn_close')
            ) {
                const el = e.target.closest('.customModal');
                if (!el) return;
                const instance = MubloModal._instances.get(el.id);
                if (instance) {
                    instance.close();
                } else {
                    const content = el.querySelector('.customModal-content');
                    if (content) content.classList.remove('open');
                    setTimeout(() => { el.remove(); MubloModal._updateScrollLock(); }, 200);
                }
            }
        });
    }
}

// 자동 초기화
document.addEventListener('DOMContentLoaded', () => MubloModal._initEventDelegation());
