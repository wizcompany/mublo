/**
 * BE.EditorItems — 아이템 렌더링 + DualListbox + AdminScript 위임
 *
 * editor-panel.js에서 분리. BE.EditorPanel이 호출.
 */
window.BE = window.BE || {};

(function () {
    'use strict';

    function esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    const EditorItems = {

        _adminScriptRetry: 0,

        render(col, index) {
            const type = col.content_type;
            if (!type) {
                return `<div class="be-editor__empty">
                    <i class="bi bi-grid-3x3-gap"></i>
                    <div class="small mt-1">콘텐츠 타입을 선택하세요</div>
                </div>`;
            }

            return `
                <div class="be-editor__item-area" id="be-item-picker">
                    <div class="small text-muted">
                        <span class="spinner-border spinner-border-sm me-1"></span> 아이템 로딩 중...
                    </div>
                </div>`;
        },

        initPicker(index, panelConfig) {
            const Store = BE.Store;
            const col = Store.getColumn(index);
            if (!col?.content_type) return;

            const container = document.getElementById('be-item-picker');
            if (!container) return;

            // Plugin/Package 자체 adminScript가 있으면 해당 스크립트로 위임
            const typeInfo = (panelConfig.contentTypes || []).find(t => t.value === col.content_type);
            if (typeInfo?.adminScript) {
                this._loadAdminScript(typeInfo.adminScript, typeInfo.adminScriptInit, container, col, index, panelConfig);
                return;
            }

            // Core 아이템 (menu 등)
            MubloRequest.requestQuery(`/admin/block-row/get-content-items?content_type=${col.content_type}`)
                .then(response => {
                    const allItems = response.data?.items || [];
                    const selected = col.content_items || [];
                    const selectedIds = selected.map(s => typeof s === 'object' ? String(s.id || s) : String(s));
                    this._renderDualListbox(container, allItems, selectedIds, index);
                })
                .catch(() => {
                    container.innerHTML = '<div class="small text-danger">아이템을 불러올 수 없습니다.</div>';
                });
        },

        _showPreviewNotice(container) {
            const existing = container.querySelector('.be-preview-notice');
            if (existing) return;
            const notice = document.createElement('div');
            notice.className = 'be-preview-notice';
            notice.innerHTML = '<i class="bi bi-info-circle me-1"></i>미리보기는 저장 후 확인할 수 있습니다.';
            container.insertBefore(notice, container.firstChild);
        },

        _loadAdminScript(scriptUrl, initName, container, col, index, panelConfig) {
            // 이미 로드된 스크립트인지 확인
            if (document.querySelector(`script[src="${scriptUrl}"]`)) {
                setTimeout(() => this._callAdminScriptInit(initName, container, col, index, panelConfig), 50);
                return;
            }

            container.innerHTML = '<div class="small text-muted py-3 text-center"><div class="spinner-border spinner-border-sm me-1"></div> 로딩 중...</div>';

            const script = document.createElement('script');
            script.src = scriptUrl;
            script.onload = () => {
                setTimeout(() => this._callAdminScriptInit(initName, container, col, index, panelConfig), 50);
            };
            script.onerror = () => {
                container.innerHTML = '<div class="small text-danger">스크립트 로드 실패: ' + scriptUrl + '</div>';
            };
            document.head.appendChild(script);
        },

        _callAdminScriptInit(initName, container, col, index, panelConfig) {
            if (!initName) return;

            if (!window[initName]) {
                // 최대 10회 재시도 (500ms)
                if (this._adminScriptRetry < 10) {
                    this._adminScriptRetry++;
                    setTimeout(() => this._callAdminScriptInit(initName, container, col, index, panelConfig), 50);
                    return;
                }
                container.innerHTML = '<div class="small text-danger">플러그인 스크립트를 불러올 수 없습니다.</div>';
                this._adminScriptRetry = 0;
                return;
            }
            this._adminScriptRetry = 0;

            const domainId = panelConfig.domainId || 1;

            try {
                const Store = BE.Store;
                const livePreview = window[initName].livePreview ?? false;
                const self = this;

                window[initName].init(container, {
                    domainId: domainId,
                    selectedItems: col.content_items || [],
                    config: col.content_config || {},
                    onChanged: (selectedIds) => {
                        const items = selectedIds.map(id => {
                            if (window[initName].getSelectedItems) {
                                const all = window[initName].getSelectedItems();
                                const found = all.find(a => String(a.id) === String(id));
                                if (found) return found;
                            }
                            return { id };
                        });
                        Store.updateColumn(index, { content_items: items });

                        const livePreview = window[initName].livePreview ?? false;

                        if (livePreview) {
                            setTimeout(() => {
                                const payload = BE.Adapter.toFormData();
                                MubloRequest.requestJson('/admin/block-row/preview', payload)
                                    .then(res => {
                                        if (res.data?.html) {
                                            (res.data.skinCss || []).forEach(url => {
                                                if (!document.querySelector(`link[href="${url}"]`)) {
                                                    const link = document.createElement('link');
                                                    link.rel = 'stylesheet';
                                                    link.href = url;
                                                    document.head.appendChild(link);
                                                }
                                            });
                                            BE.Preview.setServerRowHtml(res.data.html);
                                            const cur = document.getElementById('be-current-row');
                                            if (cur) {
                                                cur.querySelectorAll('.swiper-initialized').forEach(el => {
                                                    if (el.swiper) el.swiper.destroy(true, true);
                                                });
                                                cur.dataset.mode = 'server';
                                                cur.innerHTML = `<span class="be-editing-badge">편집 중</span>${res.data.html}`;
                                                setTimeout(() => BE.Preview._initScripts(cur), 100);
                                            }
                                        }
                                    })
                                    .catch(err => console.error('[BE] preview error:', err));
                            }, 100);
                        } else {
                            self._showPreviewNotice(container);
                        }
                    },
                });

                if (!livePreview) {
                    setTimeout(() => self._showPreviewNotice(container), 500);
                }
            } catch (e) {
                console.error('[BE] adminScript init error:', e);
                container.innerHTML = '<div class="small text-danger">플러그인 초기화 오류</div>';
            }
        },

        _renderDualListbox(container, allItems, selectedIds, colIndex) {
            const Store = BE.Store;
            const available = allItems.filter(item => !selectedIds.includes(String(item.id)));
            const selected = selectedIds.map(id => allItems.find(i => String(i.id) === id)).filter(Boolean);

            container.innerHTML = `
                <div class="be-duallist">
                    <div class="be-duallist__panel">
                        <div class="be-duallist__panel-label">전체 (${available.length})</div>
                        <select id="be-dl-available" class="be-duallist__list" size="12" multiple>
                            ${available.map(i => `<option value="${esc(String(i.id))}">${esc(i.label)}</option>`).join('')}
                        </select>
                    </div>
                    <div class="be-duallist__buttons">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="be-dl-add"><i class="bi bi-chevron-right"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="be-dl-remove"><i class="bi bi-chevron-left"></i></button>
                        <hr class="my-1">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="be-dl-up"><i class="bi bi-arrow-up"></i></button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="be-dl-down"><i class="bi bi-arrow-down"></i></button>
                    </div>
                    <div class="be-duallist__panel">
                        <div class="be-duallist__panel-label">선택됨 (${selected.length})</div>
                        <select id="be-dl-selected" class="be-duallist__list" size="12" multiple>
                            ${selected.map(i => `<option value="${esc(String(i.id))}">${esc(i.label)}</option>`).join('')}
                        </select>
                    </div>
                </div>`;

            const availEl = document.getElementById('be-dl-available');
            const selEl = document.getElementById('be-dl-selected');

            const syncToStore = () => {
                const ids = Array.from(selEl.options).map(o => o.value);
                Store.updateColumn(colIndex, { content_items: ids });
            };

            document.getElementById('be-dl-add').addEventListener('click', () => {
                Array.from(availEl.selectedOptions).forEach(opt => selEl.appendChild(opt));
                syncToStore();
            });
            document.getElementById('be-dl-remove').addEventListener('click', () => {
                Array.from(selEl.selectedOptions).forEach(opt => availEl.appendChild(opt));
                syncToStore();
            });
            document.getElementById('be-dl-up').addEventListener('click', () => {
                Array.from(selEl.selectedOptions).forEach(opt => {
                    if (opt.previousElementSibling) selEl.insertBefore(opt, opt.previousElementSibling);
                });
                syncToStore();
            });
            document.getElementById('be-dl-down').addEventListener('click', () => {
                Array.from(selEl.selectedOptions).reverse().forEach(opt => {
                    if (opt.nextElementSibling) opt.nextElementSibling.after(opt);
                });
                syncToStore();
            });
        },
    };

    BE.EditorItems = EditorItems;
})();
