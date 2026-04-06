/**
 * BE.Inspector — 좌하단 설정 패널 (라우터 + 공통 유틸)
 *
 * 행/칸 설정 렌더링은 inspector-row.js, inspector-column.js에 위임.
 * 공통: 아코디언, typeOptions, skinOptions, menuOptions, upload, gradient picker
 */
window.BE = window.BE || {};

(function () {
    'use strict';

    function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    const Inspector = {
        el: null,
        config: {},
        _skipRender: false,
        _openSections: { row: true, style: true, title: false, content: true, output: false },

        init(container, config) {
            this.el = container;
            this.config = config;
        },

        _activeTab: null, // 'row' | 'col' — null이면 selection 따라감
        _activeColIndex: undefined,

        render() {
            if (this._skipRender) { this._skipRender = false; return; }
            const Store = BE.Store;
            const columns = Store.getColumns();
            const sel = Store.getSelection();

            // 탭 자동 전환
            if (!this._activeTab) {
                this._activeTab = sel.type === 'column' ? 'col' : 'row';
            }
            // 선택된 칸 인덱스
            if (this._activeTab === 'col' && this._activeColIndex === undefined) {
                this._activeColIndex = sel.columnIndex ?? 0;
            }

            const tab = this._activeTab;
            const activeColIdx = this._activeColIndex ?? 0;

            // 탭 렌더링: [행] [1칸] [2칸] ...
            let colTabs = '';
            for (let i = 0; i < columns.length; i++) {
                const isActive = tab === 'col' && activeColIdx === i;
                colTabs += `<button class="be-settings__tab ${isActive ? 'be-settings__tab--active' : ''}" data-settings-tab="col" data-col-idx="${i}">
                    <i class="bi bi-square"></i> ${i + 1}칸
                </button>`;
            }

            // 서브탭 (행/칸에 따라 다름)
            let subTabs = '';
            if (tab === 'row') {
                subTabs = BE.InspectorRow.renderSubTabs();
            } else if (columns[activeColIdx]) {
                subTabs = BE.InspectorColumn.renderSubTabs();
            }

            let tabsHtml = `
                <div class="be-settings__tabs">
                    <div class="be-settings__tabs-main">
                        <button class="be-settings__tab ${tab === 'row' ? 'be-settings__tab--active' : ''}" data-settings-tab="row">
                            <i class="bi bi-layout-text-window"></i> 행
                        </button>
                        ${colTabs}
                    </div>
                    <div class="be-settings__tabs-sub">${subTabs}</div>
                </div>`;

            let bodyHtml = '';
            if (tab === 'row') {
                bodyHtml = BE.InspectorRow.renderBody(this);
            } else if (columns[activeColIdx]) {
                Store.select('column', activeColIdx);
                bodyHtml = BE.InspectorColumn.renderBody(activeColIdx, this);
            } else {
                bodyHtml = '<div class="be-settings__empty">칸이 없습니다</div>';
            }

            this.el.innerHTML = tabsHtml + `<div class="be-settings__body">${bodyHtml}</div>`;

            // 탭 이벤트
            this.el.querySelectorAll('[data-settings-tab]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const t = btn.dataset.settingsTab;
                    this._activeTab = t;
                    if (t === 'col') {
                        this._activeColIndex = parseInt(btn.dataset.colIdx) || 0;
                    }
                    this._skipRender = false;
                    this.render();
                    BE.EditorPanel.render();
                });
            });

            // 내부 이벤트 바인딩 — 서브모듈에 위임
            if (tab === 'row') BE.InspectorRow.bindEvents(this);
            else if (columns[activeColIdx]) BE.InspectorColumn.bindEvents(activeColIdx, this);
        },

        // selection 변경 시 탭 자동 전환
        onSelectionChanged() {
            const sel = BE.Store.getSelection();
            if (sel.type === 'column' && sel.columnIndex !== null) {
                this._activeTab = 'col';
                this._activeColIndex = sel.columnIndex;
            } else {
                this._activeTab = 'row';
            }
            this._skipRender = false;
            this.render();
        },

        // ── 공통 유틸 (서브모듈에서 inspector.method()로 접근) ──

        _accordion(key, title, body, defaultOpen) {
            const open = defaultOpen !== undefined ? defaultOpen : (this._openSections[key] !== false);
            return `
                <div class="be-settings__section">
                    <div class="be-settings__section-toggle" data-toggle-section="${key}" aria-expanded="${open}">
                        <span>${esc(title)}</span>
                        <i class="bi bi-chevron-right"></i>
                    </div>
                    <div class="be-settings__section-body${open?'':' be-settings__section-body--collapsed'}" data-section-body="${key}">
                        ${body}
                    </div>
                </div>`;
        },

        _bindAccordionToggles() {
            this.el.querySelectorAll('[data-toggle-section]').forEach(toggle => {
                toggle.addEventListener('click', () => {
                    const key = toggle.dataset.toggleSection;
                    const body = this.el.querySelector(`[data-section-body="${key}"]`);
                    const expanded = toggle.getAttribute('aria-expanded') === 'true';
                    toggle.setAttribute('aria-expanded', !expanded);
                    body?.classList.toggle('be-settings__section-body--collapsed', expanded);
                    this._openSections[key] = !expanded;
                });
            });
        },

        _typeOptions(current) {
            const groups = this.config.contentTypeGroups || {};
            let html = '<option value="">선택</option>';
            Object.entries(groups).forEach(([kind, types]) => {
                if (!types || !Object.keys(types).length) return;
                html += `<optgroup label="${esc(kind==='CORE'?'Core':kind)}">`;
                Object.entries(types).forEach(([val, info]) => {
                    const label = typeof info==='object' ? (info.title||val) : info;
                    html += `<option value="${val}" ${current===val?'selected':''}>${esc(label)}</option>`;
                });
                html += '</optgroup>';
            });
            return html;
        },

        _skinOptions(type, current) {
            const skins = type ? (this.config.skinLists?.[type] || []) : [];
            let html = '<option value="">기본</option>';
            skins.forEach(s => {
                const v = typeof s==='string' ? s : (s.value||'');
                const l = typeof s==='string' ? s : (s.label||v);
                html += `<option value="${v}" ${current===v?'selected':''}>${esc(l)}</option>`;
            });
            return html;
        },

        _menuOptions(current) {
            const groups = this.config.menuOptions || [];
            let html = '<option value="">전체</option>';
            groups.forEach(g => {
                html += `<optgroup label="${esc(g.group||'')}">`;
                (g.items||[]).forEach(i => { html += `<option value="${esc(i.value||'')}" ${current===i.value?'selected':''}>${esc(i.label||'')}</option>`; });
                html += '</optgroup>';
            });
            return html;
        },

        async _upload(file, target, cb) {
            const fd = new FormData();
            fd.append('file', file); fd.append('target', target);
            try {
                const csrfToken = await MubloRequest.getCsrfToken();
                const res = await fetch('/admin/block-row/editor-upload', {
                    method: 'POST', body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken }
                }).then(r => r.json());
                if (res.result === 'success' && res.data?.url) cb(res.data.url);
                else alert(res.message || '업로드 실패');
            } catch { alert('업로드 오류'); }
        },
    };

    // ── 그라데이션 피커 유틸리티 (LandWizard InspectorPanel 이식) ──

    const GRADIENT_PRESETS = [
        'linear-gradient(135deg, #0b1e3d, #1e3a5f)',
        'linear-gradient(135deg, #1a1a2e, #16213e)',
        'linear-gradient(135deg, #1e3c72, #2a5298)',
        'linear-gradient(135deg, #0b486b, #3b8d99)',
        'linear-gradient(135deg, #4f46e5, #7c3aed)',
        'linear-gradient(135deg, #6366f1, #ec4899)',
        'linear-gradient(135deg, #f59e0b, #ef4444)',
        'linear-gradient(135deg, #059669, #0ea5e9)',
        'linear-gradient(135deg, #667eea, #764ba2)',
        'linear-gradient(135deg, #f093fb, #f5576c)',
        'linear-gradient(135deg, #43e97b, #38f9d7)',
        'linear-gradient(135deg, #ffecd2, #fcb69f)',
    ];

    function parseGradient(gradient) {
        const defaults = { deg: '135', color1: '#667eea', color2: '#764ba2' };
        if (!gradient) return defaults;
        const m = gradient.match(/(\d+)deg\s*,\s*(#[0-9a-fA-F]{3,8})\s*(?:,\s*(#[0-9a-fA-F]{3,8}))?/);
        if (!m) return defaults;
        return { deg: m[1] || '135', color1: m[2] || defaults.color1, color2: m[3] || m[2] || defaults.color2 };
    }

    function gradientPickerHtml(id, currentGradient) {
        const parsed = parseGradient(currentGradient);
        const presets = GRADIENT_PRESETS.map((g, i) =>
            `<button type="button" class="${id}-preset be-gradient-chip" data-gradient="${esc(g)}" style="background:${g};border-color:${g === currentGradient ? '#fff' : 'transparent'}" title="프리셋 ${i+1}"></button>`
        ).join('');

        return `<div class="mb-2">
            <div class="be-gradient-presets mb-2">${presets}</div>
            <div class="be-gradient-builder">
                <input type="color" id="${id}-c1" value="${parsed.color1}">
                <span class="be-grad-arrow">→</span>
                <input type="color" id="${id}-c2" value="${parsed.color2}">
                <select id="${id}-dir">
                    <option value="135" ${parsed.deg==='135'?'selected':''}>↘ 대각선</option>
                    <option value="180" ${parsed.deg==='180'?'selected':''}>↓ 위→아래</option>
                    <option value="90" ${parsed.deg==='90'?'selected':''}>→ 좌→우</option>
                    <option value="0" ${parsed.deg==='0'?'selected':''}>↑ 아래→위</option>
                    <option value="45" ${parsed.deg==='45'?'selected':''}>↗ 대각선</option>
                    <option value="270" ${parsed.deg==='270'?'selected':''}>← 우→좌</option>
                </select>
            </div>
            <div id="${id}-preview" class="be-gradient-preview" style="background:${currentGradient || 'linear-gradient(135deg, #667eea, #764ba2)'}"></div>
            <details class="be-gradient-raw">
                <summary>CSS 직접 입력</summary>
                <input type="text" id="${id}-raw" class="form-control form-control" value="${esc(currentGradient)}" placeholder="linear-gradient(...)">
            </details>
        </div>`;
    }

    function bindGradientPicker(el, id, onGradient) {
        const c1 = el.querySelector(`#${id}-c1`);
        const c2 = el.querySelector(`#${id}-c2`);
        const dir = el.querySelector(`#${id}-dir`);
        const preview = el.querySelector(`#${id}-preview`);
        const raw = el.querySelector(`#${id}-raw`);

        const emit = () => {
            if (!c1 || !c2 || !dir) return;
            const g = `linear-gradient(${dir.value}deg, ${c1.value}, ${c2.value})`;
            if (preview) preview.style.background = g;
            if (raw) raw.value = g;
            onGradient(g);
        };
        c1?.addEventListener('input', emit);
        c2?.addEventListener('input', emit);
        dir?.addEventListener('change', emit);

        el.querySelectorAll(`.${id}-preset`).forEach(btn => {
            btn.addEventListener('click', () => {
                const g = btn.dataset.gradient;
                const p = parseGradient(g);
                if (c1) c1.value = p.color1;
                if (c2) c2.value = p.color2;
                if (dir) dir.value = p.deg;
                if (preview) preview.style.background = g;
                if (raw) raw.value = g;
                el.querySelectorAll(`.${id}-preset`).forEach(b => b.style.borderColor = 'transparent');
                btn.style.borderColor = '#fff';
                onGradient(g);
            });
        });

        raw?.addEventListener('change', (e) => {
            if (preview) preview.style.background = e.target.value;
            onGradient(e.target.value);
        });
    }

    // Inspector에 유틸 메서드 노출
    Inspector._gradientPickerHtml = gradientPickerHtml;
    Inspector._bindGradientPicker = bindGradientPicker;

    BE.Inspector = Inspector;
})();
