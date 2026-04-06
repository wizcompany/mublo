/**
 * BE.EditorPanel — 하단 편집 영역 (타입 라우터)
 *
 * 타입별 렌더링은 editor-html.js, editor-media.js, editor-items.js에 위임.
 */
window.BE = window.BE || {};

(function () {
    'use strict';

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    const Panel = {
        el: null,
        config: {},

        init(container, config) {
            this.el = container;
            this.config = config;
        },

        render() {
            const Store = BE.Store;
            const sel = Store.getSelection();

            if (sel.type !== 'column' || sel.columnIndex === null) {
                this.el.innerHTML = `<div class="be-editor__empty">
                    <i class="bi bi-cursor-fill"></i>
                    <div class="small mt-1">미리보기에서 칸을 클릭하여 편집하세요</div>
                </div>`;
                return;
            }

            const col = Store.getColumn(sel.columnIndex);
            if (!col) return;

            const type = col.content_type || '';
            const typeLabel = this._getTypeLabel(type);

            // 헤더
            let headerHtml = `
                <div class="be-editor__header">
                    <span class="be-editor__header-title">
                        ${sel.columnIndex + 1}칸 — ${esc(typeLabel)}
                        ${col.content_skin ? ` <span class="badge bg-light text-dark fw-normal">${esc(col.content_skin)}</span>` : ''}
                    </span>
                </div>`;

            // 타입별 본문 — 서브모듈에 위임
            let bodyHtml = '';
            switch (type) {
                case 'html':    bodyHtml = BE.EditorHtml.render(col, sel.columnIndex); break;
                case 'image':   bodyHtml = BE.EditorMedia.renderImage(col, sel.columnIndex); break;
                case 'include': bodyHtml = BE.EditorMedia.renderInclude(col, sel.columnIndex); break;
                case 'movie':   bodyHtml = BE.EditorMedia.renderMovie(col, sel.columnIndex); break;
                default:        bodyHtml = BE.EditorItems.render(col, sel.columnIndex); break;
            }

            this.el.innerHTML = headerHtml + bodyHtml;
            this._bindHeaderEvents(sel.columnIndex);

            // 타입별 후처리 — 서브모듈에 위임
            if (type === 'html') BE.EditorHtml.initEditor(sel.columnIndex, this.el);
            if (type === 'image') BE.EditorMedia.bindImageEvents(sel.columnIndex, this.el);
            if (!['html', 'image', 'include', 'movie', ''].includes(type)) {
                BE.EditorItems.initPicker(sel.columnIndex, this.config);
            }
        },

        // ── 이벤트 바인딩 ──

        _bindHeaderEvents(index) {
            const Store = BE.Store;

            // 출력 설정 (content_config)
            this.el.querySelectorAll('[data-config-field]').forEach(input => {
                input.addEventListener('change', () => {
                    const cc = { ...(Store.getColumn(index).content_config || {}) };
                    const field = input.dataset.configField;
                    if (input.type === 'checkbox') cc[field] = input.checked ? 1 : 0;
                    else if (input.type === 'number') cc[field] = parseInt(input.value) || 0;
                    else cc[field] = input.value;
                    Store.updateColumn(index, { content_config: cc });
                });
            });

            // Include 경로
            document.getElementById('be-include-path')?.addEventListener('change', (e) => {
                const cc = { ...(Store.getColumn(index).content_config || {}), path: e.target.value };
                Store.updateColumn(index, { content_config: cc });
            });

            // Movie URL
            document.getElementById('be-movie-url')?.addEventListener('change', (e) => {
                const cc = { ...(Store.getColumn(index).content_config || {}), url: e.target.value };
                Store.updateColumn(index, { content_config: cc });
            });
        },

        // ── 헬퍼 ──

        _getTypeLabel(type) {
            if (!type) return '미설정';
            if (!this._typeMap) {
                this._typeMap = {};
                (this.config.contentTypes || []).forEach(ct => { this._typeMap[ct.value] = ct.label; });
            }
            return this._typeMap[type] || type;
        },

        _typeMap: null,
    };

    BE.EditorPanel = Panel;
})();
