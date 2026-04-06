/**
 * BE.EditorHtml — HTML 콘텐츠 렌더링 + 에디터 초기화
 *
 * editor-panel.js에서 분리. BE.EditorPanel이 호출.
 */
window.BE = window.BE || {};

(function () {
    'use strict';

    function debounce(fn, delay) {
        let timer;
        return function (...args) { clearTimeout(timer); timer = setTimeout(() => fn.apply(this, args), delay); };
    }

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    const EditorHtml = {

        _activeSlideIndex: 0,

        render(col, index) {
            const cc = col.content_config || {};
            const mode = cc.mode || 'single';
            const isSlide = mode === 'slide';

            // 모드 전환 토글
            const modeToggle = `
                <div class="be-editor__mode-toggle">
                    <button type="button" class="be-mode-btn${!isSlide ? ' active' : ''}" data-html-mode="single">단일</button>
                    <button type="button" class="be-mode-btn${isSlide ? ' active' : ''}" data-html-mode="slide">슬라이드</button>
                </div>`;

            if (!isSlide) {
                // 단일 모드 (기존)
                return modeToggle + `
                    <div class="be-editor__html-layout">
                        <div class="be-editor__html-top">
                            <div class="be-editor__pane-label">HTML</div>
                            <textarea class="be-editor__textarea" id="be-html-editor">${esc(cc.html || '')}</textarea>
                        </div>
                        <div class="be-editor__html-bottom">
                            <div class="be-editor__html-bottom-left">
                                <div class="be-editor__pane-label">CSS</div>
                                <textarea class="be-editor__textarea" id="be-html-css">${esc(cc.css || '')}</textarea>
                            </div>
                            <div class="be-editor__html-bottom-right">
                                <div class="be-editor__pane-label">JavaScript</div>
                                <textarea class="be-editor__textarea" id="be-html-js">${esc(cc.js || '')}</textarea>
                            </div>
                        </div>
                    </div>`;
            }

            // 슬라이드 모드
            const slides = cc.slides || [{ html: '' }];
            const activeSlide = this._activeSlideIndex ?? 0;
            const clampedActive = Math.min(activeSlide, slides.length - 1);

            const slideTabs = slides.map((s, i) =>
                `<button type="button" class="be-slide-tab${i === clampedActive ? ' active' : ''}" data-slide-idx="${i}">${i + 1}</button>`
            ).join('');

            const slideSettings = `
                <div class="be-slide-settings">
                    <label class="be-slide-setting-item">
                        <span>자동재생(ms)</span>
                        <input type="number" id="be-slide-autoplay" value="${cc.pc_autoplay || 0}" min="0" step="500" class="form-control form-control" style="width:80px">
                    </label>
                    <label class="be-slide-setting-item">
                        <input type="checkbox" id="be-slide-loop" ${cc.pc_loop ? 'checked' : ''}> 반복
                    </label>
                </div>`;

            return modeToggle + `
                <div class="be-editor__html-layout be-editor__html-layout--slide">
                    <div class="be-editor__slide-bar">
                        <div class="be-slide-tabs">${slideTabs}</div>
                        <button type="button" class="be-slide-add" id="be-slide-add" title="슬라이드 추가">+</button>
                        <button type="button" class="be-slide-del" id="be-slide-del" title="현재 슬라이드 삭제">&times;</button>
                        ${slideSettings}
                    </div>
                    <div class="be-editor__html-top">
                        <div class="be-editor__pane-label">슬라이드 ${clampedActive + 1} HTML</div>
                        <textarea class="be-editor__textarea" id="be-html-editor">${esc(slides[clampedActive]?.html || '')}</textarea>
                    </div>
                    <div class="be-editor__html-bottom">
                        <div class="be-editor__html-bottom-left">
                            <div class="be-editor__pane-label">공통 CSS</div>
                            <textarea class="be-editor__textarea" id="be-html-css">${esc(cc.css || '')}</textarea>
                        </div>
                        <div class="be-editor__html-bottom-right">
                            <div class="be-editor__pane-label">공통 JavaScript</div>
                            <textarea class="be-editor__textarea" id="be-html-js">${esc(cc.js || '')}</textarea>
                        </div>
                    </div>
                </div>`;
        },

        initEditor(index, panelEl) {
            const Store = BE.Store;
            const self = this;
            const cc = Store.getColumn(index).content_config || {};
            const mode = cc.mode || 'single';
            const textarea = document.getElementById('be-html-editor');
            const cssArea = document.getElementById('be-html-css');
            const jsArea = document.getElementById('be-html-js');
            if (!textarea) return;

            let editorInstance = null;

            // ── 모드 전환 ──
            panelEl.querySelectorAll('[data-html-mode]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const newMode = btn.dataset.htmlMode;
                    const curCc = { ...(Store.getColumn(index).content_config || {}) };

                    if (newMode === 'slide' && curCc.mode !== 'slide') {
                        curCc.mode = 'slide';
                        curCc.slides = [{ html: curCc.html || '' }];
                        curCc.pc_autoplay = curCc.pc_autoplay || 5000;
                        curCc.mo_autoplay = curCc.mo_autoplay || 3000;
                        curCc.pc_loop = curCc.pc_loop ?? true;
                        curCc.mo_loop = curCc.mo_loop ?? true;
                        self._activeSlideIndex = 0;
                    } else if (newMode === 'single' && curCc.mode === 'slide') {
                        curCc.mode = 'single';
                        curCc.html = (curCc.slides && curCc.slides[0]) ? curCc.slides[0].html : '';
                        delete curCc.slides;
                    }

                    Store.updateColumn(index, { content_config: curCc });
                    BE.EditorPanel.render();
                });
            });

            // ── 슬라이드 모드 전용 이벤트 ──
            if (mode === 'slide') {
                panelEl.querySelectorAll('.be-slide-tab').forEach(tab => {
                    tab.addEventListener('click', () => {
                        this._saveCurrentSlideHtml(index, editorInstance);
                        self._activeSlideIndex = parseInt(tab.dataset.slideIdx);
                        BE.EditorPanel.render();
                    });
                });

                document.getElementById('be-slide-add')?.addEventListener('click', () => {
                    this._saveCurrentSlideHtml(index, editorInstance);
                    const curCc = { ...(Store.getColumn(index).content_config || {}) };
                    curCc.slides = curCc.slides || [];
                    curCc.slides.push({ html: '' });
                    self._activeSlideIndex = curCc.slides.length - 1;
                    Store.updateColumn(index, { content_config: curCc });
                    BE.EditorPanel.render();
                });

                document.getElementById('be-slide-del')?.addEventListener('click', () => {
                    const curCc = { ...(Store.getColumn(index).content_config || {}) };
                    if (!curCc.slides || curCc.slides.length <= 1) return;
                    curCc.slides.splice(self._activeSlideIndex, 1);
                    self._activeSlideIndex = Math.min(self._activeSlideIndex, curCc.slides.length - 1);
                    Store.updateColumn(index, { content_config: curCc });
                    BE.EditorPanel.render();
                });

                document.getElementById('be-slide-autoplay')?.addEventListener('change', (e) => {
                    const val = parseInt(e.target.value) || 0;
                    const curCc = { ...(Store.getColumn(index).content_config || {}) };
                    curCc.pc_autoplay = val;
                    curCc.mo_autoplay = val;
                    Store.updateColumn(index, { content_config: curCc });
                });
                document.getElementById('be-slide-loop')?.addEventListener('change', (e) => {
                    const curCc = { ...(Store.getColumn(index).content_config || {}) };
                    curCc.pc_loop = e.target.checked;
                    curCc.mo_loop = e.target.checked;
                    Store.updateColumn(index, { content_config: curCc });
                });
            }

            // ── HTML 에디터 초기화 (공통) ──
            const syncToStore = debounce(() => {
                const curCc = { ...(Store.getColumn(index).content_config || {}) };
                const htmlValue = editorInstance ? editorInstance.getHTML() : textarea.value;

                if (curCc.mode === 'slide') {
                    curCc.slides = curCc.slides || [];
                    const si = self._activeSlideIndex;
                    if (!curCc.slides[si]) curCc.slides[si] = {};
                    curCc.slides[si].html = htmlValue;
                } else {
                    curCc.html = htmlValue;
                }
                if (cssArea) curCc.css = cssArea.value;
                if (jsArea) curCc.js = jsArea.value;
                Store.updateColumn(index, { content_config: curCc });
            }, 500);

            if (typeof MubloEditor !== 'undefined') {
                try {
                    if (MubloEditor.get('be-html-editor')) {
                        MubloEditor.destroy('be-html-editor');
                    }
                    editorInstance = MubloEditor.create('#be-html-editor', {
                        height: 300,
                        onChange: syncToStore,
                    });
                    if (editorInstance?.wrapper) {
                        editorInstance.wrapper.style.height = '100%';
                        editorInstance.wrapper.style.display = 'flex';
                        editorInstance.wrapper.style.flexDirection = 'column';
                    }
                    if (editorInstance?.contentArea) {
                        editorInstance.contentArea.style.flex = '1';
                        editorInstance.contentArea.style.height = 'auto';
                    }
                } catch (e) {
                    console.warn('[BE] MubloEditor 초기화 실패, textarea 폴백:', e);
                    textarea.addEventListener('input', syncToStore);
                }
            } else {
                textarea.addEventListener('input', syncToStore);
            }

            cssArea?.addEventListener('input', syncToStore);
            jsArea?.addEventListener('input', syncToStore);
        },

        _saveCurrentSlideHtml(index, editorInstance) {
            const Store = BE.Store;
            const cc = { ...(Store.getColumn(index).content_config || {}) };
            if (cc.mode !== 'slide' || !cc.slides) return;
            const si = this._activeSlideIndex;
            if (!cc.slides[si]) cc.slides[si] = {};
            const textarea = document.getElementById('be-html-editor');
            cc.slides[si].html = editorInstance ? editorInstance.getHTML() : (textarea?.value || '');
            Store.updateColumn(index, { content_config: cc });
        },
    };

    BE.EditorHtml = EditorHtml;
})();
