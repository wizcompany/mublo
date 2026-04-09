/**
 * BE.InspectorColumn — 칸(Column) 설정 렌더링 + 이벤트 바인딩
 *
 * inspector.js에서 분리. BE.Inspector가 호출.
 */
window.BE = window.BE || {};

(function () {
    'use strict';

    function esc(s) { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

    const InspectorColumn = {

        _colSubTab: 'style', // 'style' | 'title' | 'content'
        _colBgTab: 'color',

        renderBody(index, inspector) {
            const Store = BE.Store;
            const col = Store.getColumn(index);
            if (!col) return '<div class="be-settings__empty">칸을 선택하세요</div>';

            const tab = this._colSubTab || 'style';
            const border = col.border_config || {};
            const tc = col.title_config || {};
            const bgColor = col.background_config?.color || '';
            const bgImage = col.background_config?.image || '';

            let content = '';
            switch (tab) {
                case 'style':
                    content = `<div class="be-settings__section-body">${this._colStyle(col, bgColor, bgImage, border)}</div>`;
                    break;
                case 'title':
                    content = `<div class="be-settings__section-body">${this._colTitle(tc)}</div>`;
                    break;
                case 'content':
                    content = `<div class="be-settings__section-body">${this._colContentType(col, inspector)}${this._colOutput(col.content_config || {})}</div>`;
                    break;
            }

            return content;
        },

        renderSubTabs() {
            const st = this._colSubTab || 'style';
            return `
                <button class="be-settings__subtab ${st==='style'?'be-settings__subtab--active':''}" data-col-subtab="style">스타일</button>
                <button class="be-settings__subtab ${st==='title'?'be-settings__subtab--active':''}" data-col-subtab="title">제목</button>
                <button class="be-settings__subtab ${st==='content'?'be-settings__subtab--active':''}" data-col-subtab="content">콘텐츠</button>`;
        },

        bindEvents(index, inspector) {
            const Store = BE.Store;
            const el = inspector.el;

            // 서브 탭 전환
            el.querySelectorAll('[data-col-subtab]').forEach(btn => {
                btn.addEventListener('click', () => {
                    this._colSubTab = btn.dataset.colSubtab;
                    inspector._skipRender = false;
                    inspector.render();
                });
            });

            el.querySelectorAll('[data-col-field]').forEach(input => {
                input.addEventListener('change', () => {
                    inspector._skipRender = true;
                    const field = input.dataset.colField;
                    let value = input.type === 'checkbox' ? (input.checked?1:0) : input.value;

                    if (field === 'content_type') {
                        Store.updateColumn(index, { content_type: value, content_skin: '', content_config: {}, content_items: [] });
                        inspector.render();
                        BE.EditorPanel.render();
                        return;
                    }
                    Store.updateColumn(index, { [field]: value });
                    if (field === 'content_skin') BE.EditorPanel.render();
                });
            });

            // 칸 배경 탭 전환
            el.querySelectorAll('[data-col-bg-tab]').forEach(btn => {
                btn.addEventListener('click', () => {
                    this._colBgTab = btn.dataset.colBgTab;
                    inspector._skipRender = false;
                    inspector.render();
                });
            });

            // 칸 배경: 색상
            const colCp = el.querySelector('[data-col-bg-color]'), colCt = el.querySelector('[data-col-bg-text]');
            if (colCp && colCt) {
                const sync = (c) => { inspector._skipRender = true; const bg = { ...(Store.getColumn(index).background_config||{}) }; bg.color = c; delete bg.gradient; Store.updateColumn(index, { background_config: bg }); };
                colCp.addEventListener('input', () => { colCt.value = colCp.value; sync(colCp.value); });
                colCt.addEventListener('change', () => { colCp.value = colCt.value; sync(colCt.value); });
            }
            el.querySelector('[data-col-bg-color-clear]')?.addEventListener('click', () => {
                const bg = { ...(Store.getColumn(index).background_config||{}) }; delete bg.color;
                Store.updateColumn(index, { background_config: bg }); inspector.render();
            });

            // 칸 배경: 그라데이션 피커
            inspector._bindGradientPicker(el, 'ci-grad', (g) => {
                inspector._skipRender = true;
                const bg = { ...(Store.getColumn(index).background_config||{}) }; bg.gradient = g; delete bg.color;
                Store.updateColumn(index, { background_config: bg });
            });

            // 칸 배경: 이미지
            document.getElementById('ci-bg-file')?.addEventListener('change', function () {
                if (!this.files[0]) return;
                inspector._upload(this.files[0], 'bg', (url) => {
                    Store.updateColumn(index, { background_config: { ...(Store.getColumn(index).background_config||{}), image: url } });
                    inspector.render();
                });
            });
            document.getElementById('ci-bg-delete')?.addEventListener('click', () => {
                const bg = { ...(Store.getColumn(index).background_config||{}) }; delete bg.image;
                Store.updateColumn(index, { background_config: bg }); inspector.render();
            });

            // 테두리
            el.querySelectorAll('[data-col-border]').forEach(input => {
                input.addEventListener('change', () => {
                    inspector._skipRender = true;
                    const border = { ...(Store.getColumn(index).border_config||{}) };
                    border[input.dataset.colBorder] = input.type==='number' ? parseInt(input.value)||0 : input.value;
                    Store.updateColumn(index, { border_config: border });
                });
            });

            // 제목
            el.querySelectorAll('[data-title-field]').forEach(input => {
                input.addEventListener('change', () => {
                    inspector._skipRender = true;
                    const tc = { ...(Store.getColumn(index).title_config||{}) };
                    tc[input.dataset.titleField] = input.type==='checkbox' ? (input.checked?1:0) : input.value;
                    Store.updateColumn(index, { title_config: tc });
                    if (input.dataset.titleField === 'show') { const d = document.getElementById('ci-title-detail'); if (d) d.style.display = input.checked ? '' : 'none'; }
                    if (input.dataset.titleField === 'more_link') { const d = document.getElementById('ci-more-url'); if (d) d.style.display = input.checked ? '' : 'none'; }
                });
            });

            // 제목 이미지
            ['pc','mo'].forEach(type => {
                document.getElementById(`ci-title-${type}-file`)?.addEventListener('change', function () {
                    if (!this.files[0]) return;
                    inspector._upload(this.files[0], `title_${type}`, (url) => {
                        const tc = { ...(Store.getColumn(index).title_config||{}), [`${type}_image`]: url };
                        Store.updateColumn(index, { title_config: tc }); inspector.render();
                    });
                });
                el.querySelector(`[data-title-img-del="${type}"]`)?.addEventListener('click', () => {
                    const tc = { ...(Store.getColumn(index).title_config||{}) }; delete tc[`${type}_image`];
                    Store.updateColumn(index, { title_config: tc }); inspector.render();
                });
            });

            // 출력 설정
            el.querySelectorAll('[data-config-field]').forEach(input => {
                input.addEventListener('change', () => {
                    inspector._skipRender = true;
                    const cc = { ...(Store.getColumn(index).content_config||{}) };
                    const f = input.dataset.configField;
                    cc[f] = input.type==='checkbox' ? (input.checked?1:0) : input.type==='number' ? parseInt(input.value)||0 : input.value;
                    Store.updateColumn(index, { content_config: cc });
                });
            });
        },

        // ── Column 렌더링 헬퍼 ──

        _colStyle(col, bgColor, bgImage, border) {
            const bg = col.background_config || {};
            const gradient = bg.gradient || '';
            const tab = this._colBgTab || (gradient ? 'gradient' : (bgImage ? 'image' : 'color'));
            this._colBgTab = tab;

            const bgTabs = `
                <div class="be-bg-tabs mb-2">
                    <button type="button" class="be-bg-tab${tab==='color'?' active':''}" data-col-bg-tab="color">색상</button>
                    <button type="button" class="be-bg-tab${tab==='gradient'?' active':''}" data-col-bg-tab="gradient">그라데이션</button>
                    <button type="button" class="be-bg-tab${tab==='image'?' active':''}" data-col-bg-tab="image">이미지</button>
                </div>`;

            let bgBody = '';
            if (tab === 'color') {
                bgBody = `
                    <div class="input-group mb-2">
                        <input type="color" class="form-control form-control-color be-color-picker" data-col-bg-color value="${bgColor||'#ffffff'}">
                        <input type="text" class="form-control" data-col-bg-text value="${bgColor}" maxlength="7" placeholder="#ffffff">
                        ${bgColor ? `<button type="button" class="btn btn-outline-secondary btn-sm" data-col-bg-color-clear title="초기화"><i class="bi bi-x"></i></button>` : ''}
                    </div>`;
            } else if (tab === 'gradient') {
                bgBody = BE.Inspector._gradientPickerHtml('ci-grad', gradient || 'linear-gradient(135deg, #667eea, #764ba2)');
            } else {
                bgBody = `
                    ${bgImage ? `<div class="mb-1"><img src="${bgImage}" style="max-width:100%;max-height:30px;border-radius:4px"></div>` : ''}
                    <div class="input-group mb-2">
                        <input type="file" class="form-control" id="ci-bg-file" accept="image/*">
                        ${bgImage ? `<button type="button" class="btn btn-outline-danger btn-sm" id="ci-bg-delete"><i class="bi bi-trash"></i></button>` : ''}
                    </div>`;
            }

            return `
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">크기 / 여백</div>
                            <div class="mb-2"><label class="form-label">너비</label>
                                <input type="text" class="form-control" data-col-field="width" value="${esc(col.width||'')}" placeholder="자동">
                                <div class="form-text">예: 50%, 300px</div></div>
                            <div class="mb-2"><label class="form-label">PC 여백</label>
                                <input type="text" class="form-control" data-col-field="pc_padding" value="${esc(col.pc_padding||'')}" placeholder="0">
                            </div>
                            <div><label class="form-label">MO 여백</label>
                                <input type="text" class="form-control" data-col-field="mobile_padding" value="${esc(col.mobile_padding||'')}" placeholder="0">
                            </div>
                            <div class="form-text mt-1">예: 20px 10px</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">테두리</div>
                            <div class="mb-2"><label class="form-label">두께 (px)</label>
                                <input type="number" class="form-control" data-col-border="width" value="${parseInt(border.width)||0}" min="0"></div>
                            <div class="mb-2"><label class="form-label">색상</label>
                                <input type="color" class="form-control form-control-color be-color-picker" data-col-border="color" value="${border.color||'#dee2e6'}"></div>
                            <div><label class="form-label">라운드 (px)</label>
                                <input type="number" class="form-control" data-col-border="radius" value="${parseInt(border.radius)||0}" min="0"></div>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" data-col-field="is_active" id="ci_active" ${col.is_active?'checked':''}>
                                <label class="form-check-label" for="ci_active">칸 표시</label>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">배경</div>
                            ${bgTabs}${bgBody}
                        </div>
                    </div>
                </div>`;
        },

        _colTitle(tc) {
            const show = !!tc.show;
            return `
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" data-title-field="show" id="ci_title_show" ${show?'checked':''}>
                    <label class="form-check-label" for="ci_title_show">제목 영역 표시</label>
                </div>
                <div id="ci-title-detail" style="${show?'':'display:none'}">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="be-card">
                                <div class="be-card__title">제목</div>
                                <div class="mb-2"><label class="form-label">텍스트</label>
                                    <input type="text" class="form-control" data-title-field="text" value="${esc(tc.text||'')}" maxlength="25"></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">색상</label>
                                        <input type="color" class="form-control form-control-color be-color-picker" data-title-field="color" value="${tc.color||'#333333'}"></div>
                                    <div class="col-6"><label class="form-label">위치</label>
                                        <select class="form-select" data-title-field="position">
                                            <option value="left" ${tc.position==='left'?'selected':''}>왼쪽</option>
                                            <option value="center" ${tc.position==='center'?'selected':''}>가운데</option>
                                            <option value="right" ${tc.position==='right'?'selected':''}>오른쪽</option>
                                        </select></div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label">PC 크기</label>
                                        <div class="input-group"><input type="number" class="form-control" data-title-field="size_pc" value="${parseInt(tc.size_pc)||16}" min="10" max="100"><span class="input-group-text">px</span></div></div>
                                    <div class="col-6"><label class="form-label">MO 크기</label>
                                        <div class="input-group"><input type="number" class="form-control" data-title-field="size_mo" value="${parseInt(tc.size_mo)||14}" min="10" max="100"><span class="input-group-text">px</span></div></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="be-card">
                                <div class="be-card__title">문구</div>
                                <div class="mb-2"><label class="form-label">텍스트</label>
                                    <input type="text" class="form-control" data-title-field="copytext" value="${esc(tc.copytext||'')}" maxlength="50"></div>
                                <div class="row g-2 mb-2">
                                    <div class="col-6"><label class="form-label">색상</label>
                                        <input type="color" class="form-control form-control-color be-color-picker" data-title-field="copytext_color" value="${tc.copytext_color||'#666666'}"></div>
                                    <div class="col-6"><label class="form-label">위치</label>
                                        <select class="form-select" data-title-field="copytext_position">
                                            <option value="" ${!tc.copytext_position?'selected':''}>제목과 동일</option>
                                            <option value="left" ${tc.copytext_position==='left'?'selected':''}>왼쪽</option>
                                            <option value="center" ${tc.copytext_position==='center'?'selected':''}>가운데</option>
                                            <option value="right" ${tc.copytext_position==='right'?'selected':''}>오른쪽</option>
                                        </select></div>
                                </div>
                                <div class="row g-2">
                                    <div class="col-6"><label class="form-label">PC 크기</label>
                                        <div class="input-group"><input type="number" class="form-control" data-title-field="copytext_size_pc" value="${parseInt(tc.copytext_size_pc)||14}" min="10" max="100"><span class="input-group-text">px</span></div></div>
                                    <div class="col-6"><label class="form-label">MO 크기</label>
                                        <div class="input-group"><input type="number" class="form-control" data-title-field="copytext_size_mo" value="${parseInt(tc.copytext_size_mo)||12}" min="10" max="100"><span class="input-group-text">px</span></div></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="be-card">
                                <div class="be-card__title">더보기 링크</div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" data-title-field="more_link" id="ci_more" ${tc.more_link?'checked':''}>
                                    <label class="form-check-label" for="ci_more">사용</label>
                                </div>
                                <div id="ci-more-url" style="${tc.more_link?'':'display:none'}">
                                    <input type="text" class="form-control" data-title-field="more_url" value="${esc(tc.more_url||'')}" placeholder="/board/notice">
                                </div>
                                <div class="be-hint">제목 우측에 더보기 링크가 표시됩니다.</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="be-card">
                                <div class="be-card__title">제목 이미지</div>
                                ${['pc','mo'].map(t => `
                                    <div class="mb-2"><label class="form-label">${t.toUpperCase()}</label>
                                        ${tc[t+'_image'] ? `<div class="mb-1"><img src="${tc[t+'_image']}" style="max-width:100%;max-height:24px;border-radius:3px"></div>` : ''}
                                        <div class="input-group">
                                            <input type="file" class="form-control" id="ci-title-${t}-file" accept="image/*">
                                            ${tc[t+'_image'] ? `<button type="button" class="btn btn-outline-danger btn-sm" data-title-img-del="${t}"><i class="bi bi-trash"></i></button>` : ''}
                                        </div></div>`).join('')}
                                <div class="be-hint">이미지를 등록하면 텍스트 대신 이미지가 출력됩니다.</div>
                            </div>
                        </div>
                    </div>
                </div>`;
        },

        _colContentType(col, inspector) {
            return `
                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="be-card">
                            <div class="be-card__title">콘텐츠 타입</div>
                            <select class="form-select" data-col-field="content_type">${inspector._typeOptions(col.content_type)}</select>
                            <div class="form-text">출력할 콘텐츠 종류</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="be-card">
                            <div class="be-card__title">스킨</div>
                            <select class="form-select" data-col-field="content_skin">${inspector._skinOptions(col.content_type, col.content_skin)}</select>
                            <div class="form-text">디자인 템플릿</div>
                        </div>
                    </div>
                </div>`;
        },

        _colOutput(cc) {
            return `
                <div class="row g-2">
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">출력 수</div>
                            <div class="mb-2"><label class="form-label">PC</label>
                                <input type="number" class="form-control" data-config-field="pc_count" value="${parseInt(cc.pc_count)||5}" min="1" max="50"></div>
                            <div><label class="form-label">MO</label>
                                <input type="number" class="form-control" data-config-field="mo_count" value="${parseInt(cc.mo_count)||4}" min="1" max="50"></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">레이아웃</div>
                            <div class="mb-2"><label class="form-label">PC 스타일</label>
                                <select class="form-select" data-config-field="pc_style">
                                    <option value="list" ${cc.pc_style==='list'?'selected':''}>리스트</option>
                                    <option value="slide" ${cc.pc_style==='slide'?'selected':''}>슬라이드</option>
                                </select></div>
                            <div><label class="form-label">MO 스타일</label>
                                <select class="form-select" data-config-field="mo_style">
                                    <option value="list" ${cc.mo_style==='list'?'selected':''}>리스트</option>
                                    <option value="slide" ${cc.mo_style==='slide'?'selected':''}>슬라이드</option>
                                </select></div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="be-card">
                            <div class="be-card__title">컬럼 / 옵션</div>
                            <div class="row g-2 mb-2">
                                <div class="col-6"><label class="form-label">PC</label>
                                    <input type="number" class="form-control" data-config-field="pc_cols" value="${parseInt(cc.pc_cols)||1}" min="1" max="6"></div>
                                <div class="col-6"><label class="form-label">MO</label>
                                    <input type="number" class="form-control" data-config-field="mo_cols" value="${parseInt(cc.mo_cols)||1}" min="1" max="4"></div>
                            </div>
                            <div class="d-flex gap-3 flex-wrap">
                                <div class="form-check"><input class="form-check-input" type="checkbox" data-config-field="aos" id="be-aos" ${cc.aos?'checked':''}><label class="form-check-label" for="be-aos">AOS</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" data-config-field="autoplay" id="be-auto" ${cc.autoplay?'checked':''}><label class="form-check-label" for="be-auto">자동재생</label></div>
                                <div class="form-check"><input class="form-check-input" type="checkbox" data-config-field="loop" id="be-loop" ${cc.loop?'checked':''}><label class="form-check-label" for="be-loop">루프</label></div>
                            </div>
                        </div>
                    </div>
                </div>`;
        },
    };

    BE.InspectorColumn = InspectorColumn;
})();
