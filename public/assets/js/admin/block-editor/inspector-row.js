/**
 * BE.InspectorRow — 행(Row) 설정 렌더링 + 이벤트 바인딩
 *
 * inspector.js에서 분리. BE.Inspector가 호출.
 */
window.BE = window.BE || {};

(function () {
    'use strict';

    function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    const InspectorRow = {

        _rowSubTab: 'basic', // 'basic' | 'layout' | 'style'
        _rowBgTab: 'color',

        renderBody(inspector) {
            const Store = BE.Store;
            const r = Store.getRow();
            const bg = r.background_config || {};
            const tab = this._rowSubTab || 'basic';

            let content = '';
            switch (tab) {
                case 'basic':
                    content = this._rowBasic(r, inspector);
                    break;
                case 'layout':
                    content = this._rowLayout(r);
                    break;
                case 'style':
                    content = this._rowStyle(r, bg);
                    break;
            }

            return `<div class="be-settings__section-body">${content}</div>`;
        },

        renderSubTabs() {
            const st = this._rowSubTab || 'basic';
            return `
                <button class="be-settings__subtab ${st==='basic'?'be-settings__subtab--active':''}" data-row-subtab="basic">기본</button>
                <button class="be-settings__subtab ${st==='layout'?'be-settings__subtab--active':''}" data-row-subtab="layout">레이아웃</button>
                <button class="be-settings__subtab ${st==='style'?'be-settings__subtab--active':''}" data-row-subtab="style">스타일</button>`;
        },

        bindEvents(inspector) {
            const Store = BE.Store;
            const el = inspector.el;

            // 행 서브 탭 전환
            el.querySelectorAll('[data-row-subtab]').forEach(btn => {
                btn.addEventListener('click', () => {
                    this._rowSubTab = btn.dataset.rowSubtab;
                    inspector._skipRender = false;
                    inspector.render();
                });
            });

            el.querySelectorAll('[data-field]').forEach(input => {
                const field = input.dataset.field;
                input.addEventListener('change', () => {
                    if (field !== 'column_count') inspector._skipRender = true;
                    let v = input.type === 'checkbox' ? (input.checked?1:0) : input.value;
                    if (['width_type','column_count','column_margin','column_width_unit','is_active'].includes(field)) v = parseInt(v)||0;
                    if (field === 'column_count') Store.setColumnCount(v);
                    else Store.updateRow({ [field]: v });
                    if (field === 'position') {
                        const w = document.getElementById('ri-menu-wrap');
                        if (w) w.style.display = v && v !== 'index' ? '' : 'none';
                    }
                });
            });

            // 행 배경 탭 전환
            el.querySelectorAll('[data-row-bg-tab]').forEach(btn => {
                btn.addEventListener('click', () => {
                    this._rowBgTab = btn.dataset.rowBgTab;
                    inspector._skipRender = false;
                    inspector.render();
                });
            });

            // 행 배경: 색상
            const cp = el.querySelector('[data-bg-color]'), ct = el.querySelector('[data-bg-color-text]');
            if (cp && ct) {
                const sync = (c) => { inspector._skipRender = true; const bg = { ...(Store.getRow().background_config||{}) }; bg.color = c; delete bg.gradient; Store.updateRow({ background_config: bg }); };
                cp.addEventListener('input', () => { ct.value = cp.value; sync(cp.value); });
                ct.addEventListener('change', () => { cp.value = ct.value; sync(ct.value); });
            }
            el.querySelector('[data-bg-color-clear]')?.addEventListener('click', () => {
                const bg = { ...(Store.getRow().background_config||{}) }; delete bg.color;
                Store.updateRow({ background_config: bg }); inspector.render();
            });

            // 행 배경: 그라데이션 피커
            inspector._bindGradientPicker(el, 'ri-grad', (g) => {
                inspector._skipRender = true;
                const bg = { ...(Store.getRow().background_config||{}) }; bg.gradient = g; delete bg.color;
                Store.updateRow({ background_config: bg });
            });

            // 행 배경: 이미지
            document.getElementById('ri-bg-file')?.addEventListener('change', function () {
                if (!this.files[0]) return;
                inspector._upload(this.files[0], 'bg', (url) => {
                    Store.updateRow({ background_config: { ...(Store.getRow().background_config||{}), image: url } });
                    inspector.render();
                });
            });
            document.getElementById('ri-bg-delete')?.addEventListener('click', () => {
                const bg = { ...(BE.Store.getRow().background_config||{}) }; delete bg.image;
                BE.Store.updateRow({ background_config: bg }); inspector.render();
            });
        },

        // ── Row 렌더링 헬퍼 ──

        _rowBasic(r, inspector) {
            const positions = inspector.config.positions || {};
            let positionField = '';
            let menuField = '';
            if (parseInt(r.page_id) > 0) {
                positionField = `<div class="text-muted small">페이지: ${esc(inspector.config.currentPageLabel || '#' + r.page_id)}</div>`;
            } else {
                const posOpts = Object.entries(positions).map(([v,l])=>
                    `<option value="${v}" ${r.position===v?'selected':''}>${esc(l)}</option>`).join('');
                const menuOpts = inspector._menuOptions(r.position_menu);
                const show = r.position && r.position !== 'index';
                positionField = `<label class="form-label">출력 위치</label>
                    <select class="form-select" data-field="position">${posOpts}</select>`;
                menuField = `<div id="ri-menu-wrap" style="${show?'':'display:none'}">
                    <label class="form-label">메뉴 필터</label>
                    <select class="form-select" data-field="position_menu">${menuOpts}</select></div>`;
            }

            return `
                <div class="row g-2">
                    <div class="col-6">
                        <div class="be-card">
                            <div class="be-card__title">기본 정보</div>
                            <div class="mb-2"><label class="form-label">관리용 제목</label>
                                <input type="text" class="form-control" data-field="admin_title" value="${esc(r.admin_title || '')}" placeholder="내부 관리용"></div>
                            <div class="mb-0"><label class="form-label">섹션 ID</label>
                                <input type="text" class="form-control" data-field="section_id" value="${esc(r.section_id || '')}" placeholder="자동 생성"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="be-card">
                            <div class="be-card__title">출력 설정</div>
                            <div class="mb-2">${positionField}</div>
                            ${menuField}
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" data-field="is_active" id="ri_active" ${r.is_active ? 'checked' : ''}>
                                <label class="form-check-label" for="ri_active">이 블록을 사이트에 표시</label>
                            </div>
                        </div>
                    </div>
                </div>`;
        },

        _rowLayout(r) {
            const cc = parseInt(r.column_count) || 1;
            return `
                <div class="row g-2">
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">넓이</div>
                            <select class="form-select" data-field="width_type">
                                <option value="0" ${parseInt(r.width_type)===0?'selected':''}>와이드 (100%)</option>
                                <option value="1" ${parseInt(r.width_type)===1?'selected':''}>컨테이너</option>
                            </select>
                            <div class="form-text">화면 전체 또는 사이트 설정 폭</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">칸 수</div>
                            <select class="form-select" data-field="column_count">
                                ${[1,2,3,4].map(n=>`<option value="${n}" ${cc===n?'selected':''}>${n}칸</option>`).join('')}
                            </select>
                            <div class="be-hint">변경 시 칸 내용이 초기화됩니다.</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">칸 간격</div>
                            <div class="input-group">
                                <input type="number" class="form-control" data-field="column_margin" value="${parseInt(r.column_margin)||0}" min="0" max="100">
                                <span class="input-group-text">px</span>
                            </div>
                            <div class="form-text">0이면 붙어서 표시</div>
                        </div>
                    </div>
                </div>`;
        },

        _rowStyle(r, bg) {
            return `
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">높이</div>
                            <div class="mb-2"><label class="form-label">PC</label>
                                <input type="text" class="form-control" data-field="pc_height" value="${esc(r.pc_height||'')}" placeholder="auto"></div>
                            <div><label class="form-label">MO</label>
                                <input type="text" class="form-control" data-field="mobile_height" value="${esc(r.mobile_height||'')}" placeholder="auto"></div>
                            <div class="form-text mt-1">예: 400px, 50vh</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">여백</div>
                            <div class="mb-2"><label class="form-label">PC</label>
                                <input type="text" class="form-control" data-field="pc_padding" value="${esc(r.pc_padding||'')}" placeholder="0"></div>
                            <div><label class="form-label">MO</label>
                                <input type="text" class="form-control" data-field="mobile_padding" value="${esc(r.mobile_padding||'')}" placeholder="0"></div>
                            <div class="form-text mt-1">예: 40px 20px</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">배경</div>
                            ${this._rowBg(bg)}
                        </div>
                    </div>
                </div>`;
        },

        _rowBg(bg) {
            const color = bg.color || '';
            const gradient = bg.gradient || '';
            const image = bg.image || '';
            const tab = this._rowBgTab || (gradient ? 'gradient' : (image ? 'image' : 'color'));
            this._rowBgTab = tab;

            const tabs = `
                <label class="form-label">배경</label>
                <div class="be-bg-tabs mb-2">
                    <button type="button" class="be-bg-tab${tab==='color'?' active':''}" data-row-bg-tab="color">색상</button>
                    <button type="button" class="be-bg-tab${tab==='gradient'?' active':''}" data-row-bg-tab="gradient">그라데이션</button>
                    <button type="button" class="be-bg-tab${tab==='image'?' active':''}" data-row-bg-tab="image">이미지</button>
                </div>`;

            let body = '';
            if (tab === 'color') {
                body = `
                    <div class="input-group input-group">
                        <input type="color" class="form-control form-control-color form-control be-color-picker" data-bg-color value="${color||'#ffffff'}">
                        <input type="text" class="form-control form-control" data-bg-color-text value="${color}" maxlength="7" placeholder="#ffffff">
                        ${color ? `<button type="button" class="btn btn-outline-secondary btn-sm" data-bg-color-clear title="초기화"><i class="bi bi-x"></i></button>` : ''}
                    </div>`;
            } else if (tab === 'gradient') {
                body = BE.Inspector._gradientPickerHtml('ri-grad', gradient || 'linear-gradient(135deg, #667eea, #764ba2)');
            } else {
                body = `
                    ${image ? `<div class="mb-1"><img src="${image}" style="max-width:100%;max-height:40px;border-radius:4px"></div>` : ''}
                    <div class="input-group input-group">
                        <input type="file" class="form-control form-control" id="ri-bg-file" accept="image/*">
                        ${image ? `<button type="button" class="btn btn-outline-danger btn-sm" id="ri-bg-delete"><i class="bi bi-trash"></i></button>` : ''}
                    </div>`;
            }

            return tabs + body;
        },
    };

    BE.InspectorRow = InspectorRow;
})();
