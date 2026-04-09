/**
 * BE.Preview — 상단 미리보기 (클라이언트 직접 렌더링 + 주변 행 컨텍스트)
 *
 * - 현재 편집 중인 행: Store 데이터로 클라이언트 렌더링
 * - 같은 위치의 다른 행: 서버 렌더링된 HTML (로드 시 1회 수신)
 * - 현재 행 위치로 자동 스크롤
 */
window.BE = window.BE || {};

BE.Preview = {
    el: null,
    loading: null,
    _siblingRows: [],
    _serverRowHtml: '',

    init() {
        this.el = document.getElementById('be-preview-content');
        this.loading = document.getElementById('be-preview-loading');
        this._previewEl = document.getElementById('be-preview');
        this._isFullscreen = false;

        document.getElementById('be-btn-refresh')?.addEventListener('click', () => this.refresh());
        document.getElementById('be-btn-fullscreen')?.addEventListener('click', () => this.toggleFullscreen());

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this._isFullscreen) {
                this.toggleFullscreen();
            }
        });
    },

    toggleFullscreen() {
        this._isFullscreen = !this._isFullscreen;
        this._previewEl?.classList.toggle('be-preview--fullscreen', this._isFullscreen);

        const icon = document.querySelector('#be-btn-fullscreen i');
        if (icon) {
            icon.className = this._isFullscreen ? 'bi bi-fullscreen-exit' : 'bi bi-arrows-fullscreen';
        }

        // 스케일 재계산
        this.refresh();
    },

    /**
     * 주변 행 데이터 설정 (로드 시 1회)
     */
    setSiblingRows(rows) {
        this._siblingRows = rows || [];
    },

    setServerRowHtml(html) {
        this._serverRowHtml = html || '';
    },

    refresh() {
        if (!this.el) return;
        this.loading?.classList.add('be-preview__loading--hidden');

        const Store = BE.Store;
        const columns = Store.getColumns();
        const hasClientType = columns.some(c => {
            const t = c.content_type || '';
            return !t || t === 'html' || t === 'image' || t === 'include' || t === 'movie';
        });
        const currentHtml = this._buildCurrentRow(Store.getRow(), columns);

        // 이미 초기 렌더링 완료 → 현재 행만 교체
        const existing = document.getElementById('be-current-row');
        if (existing) {
            if (existing.dataset.mode === 'server') return;
            existing.innerHTML = `<span class="be-editing-badge">편집 중</span>${currentHtml}`;
            this._initScripts(existing);
            return;
        }

        // 초기 렌더링 (1회만)
        const containerWidth = this.el.offsetWidth - 24; // padding 12px * 2
        const scale = containerWidth / 1920;

        let parts = [];

        if (this._siblingRows.length > 0) {
            this._siblingRows.forEach((sib, idx) => {
                if (sib.error || !sib.html) return;
                if (sib.html === '__CURRENT__') {
                    const useServer = !hasClientType && this._serverRowHtml;
                    const rowContent = useServer ? this._serverRowHtml : currentHtml;
                    const modeAttr = useServer ? ' data-mode="server"' : '';
                    parts.push(`<div id="be-current-row"${modeAttr} style="position:relative;">
                        <span class="be-editing-badge">편집 중</span>
                        ${rowContent}
                    </div>`);
                } else if (sib.html && sib.html.trim()) {
                    parts.push(`<div class="be-sib-row" data-row-id="${sib.row_id}" style="opacity:0.45;cursor:pointer;position:relative;"><div style="position:absolute;inset:0;z-index:1;"></div><iframe class="be-sib-frame" data-sib="${idx}" style="width:100%;border:none;display:block;" scrolling="no"></iframe></div>`);
                }
            });
        }

        if (parts.length === 0) parts.push(`<div id="be-current-row" style="position:relative;"><span class="be-editing-badge">편집 중</span>${currentHtml}</div>`);

        this.el.innerHTML = `<div class="be-preview__scaler" style="width:1920px;transform:scale(${scale});">${parts.join('')}</div>`;

        // 주변 행 iframe 내용 삽입 (1회만)
        this._siblingRows.forEach((sib, idx) => {
            if (sib.error || !sib.html || sib.html === '__CURRENT__') return;
            const iframe = this.el.querySelector(`[data-sib="${idx}"]`);
            if (!iframe) return;

            iframe.srcdoc = `<!DOCTYPE html>
<html><head>
<link rel="stylesheet" href="/assets/css/front-common.css">
<link rel="stylesheet" href="/serve/front/basic/css/front.css">
<link rel="stylesheet" href="/assets/css/block.css">
<link rel="stylesheet" href="/assets/lib/swiper/12/swiper-bundle.min.css">
<style>body{margin:0;padding:0;overflow:hidden;}</style>
</head><body>${sib.html}
<script src="/assets/lib/swiper/12/swiper-bundle.min.js"><\/script>
<script src="/assets/js/MubloItemLayout.js"><\/script>
<script>if(typeof MubloItemLayout!=='undefined')MubloItemLayout.initAll();<\/script>
</body></html>`;

            iframe.onload = () => {
                try { iframe.style.height = iframe.contentDocument.body.scrollHeight + 'px'; } catch(e) {}
            };
        });

        // 스크립트 초기화 + 현재 행으로 스크롤 + 다른 행 클릭 이벤트
        requestAnimationFrame(() => {
            const cur = document.getElementById('be-current-row');
            if (cur) {
                this._initScripts(cur);
                // 약간 지연 후 스크롤 (iframe 로드 대기)
                setTimeout(() => this._scrollToCurrent(), 500);
            }

            this.el.querySelectorAll('.be-sib-row').forEach(row => {
                row.addEventListener('click', () => {
                    const rowId = row.dataset.rowId;
                    if (!rowId) return;
                    const msg = BE.Store.isDirty()
                        ? '변경사항이 저장되지 않았습니다.\n해당 행 편집 페이지로 이동하시겠습니까?'
                        : '해당 행 편집 페이지로 이동하시겠습니까?';
                    MubloRequest.showConfirm(msg, function() {
                        location.href = '/admin/block-row/editor?id=' + rowId;
                    });
                });
            });
        });
    },

    _buildCurrentRow(row, columns) {
        const bg = row.background_config || {};
        let rowStyle = this._buildBgStyle(bg);
        if (row.pc_padding) rowStyle += `padding:${row.pc_padding};`;

        const isWide = parseInt(row.width_type) === 0;
        const containerStyle = isWide ? '' : 'max-width:var(--site-max-width,1200px);margin:0 auto;';

        const margin = parseInt(row.column_margin) || 0;
        const previewGap = Math.max(margin, 8);
        const flexGap = `gap:${previewGap}px;`;

        let colsHtml = '';
        columns.forEach((col, i) => {
            if (!col.is_active) return;

            const colBg = col.background_config || {};
            let colStyle = this._buildBgStyle(colBg);
            if (col.pc_padding) colStyle += `padding:${col.pc_padding};`;
            if (col.width) colStyle += `flex:0 0 ${col.width};`;
            else colStyle += 'flex:1;';

            const border = col.border_config || {};
            if (parseInt(border.width) > 0) {
                colStyle += `border:${border.width}px solid ${border.color || '#dee2e6'};`;
            }
            if (parseInt(border.radius) > 0) {
                colStyle += `border-radius:${border.radius}px;`;
            }

            let titleHtml = '';
            const tc = col.title_config || {};
            if (tc.show && tc.text) {
                let ts = '';
                if (tc.color) ts += `color:${tc.color};`;
                if (tc.size) ts += `font-size:${tc.size};`;
                if (tc.position) ts += `text-align:${tc.position};`;
                titleHtml = `<div style="font-weight:700;margin-bottom:8px;${ts}">${this._esc(tc.text)}</div>`;
                if (tc.copytext) {
                    titleHtml += `<div style="text-align:${tc.position||'left'};color:#888;font-size:0.85em;margin-bottom:8px;">${this._esc(tc.copytext)}</div>`;
                }
            }

            let contentHtml = '';
            const cc = col.content_config || {};
            const type = col.content_type || '';

            if (type === 'html') {
                const mode = cc.mode || 'single';
                if (mode === 'slide' && cc.slides?.length) {
                    contentHtml = cc.slides.map(s => `<div>${s.html || ''}</div>`).join('');
                } else {
                    contentHtml = cc.html || '';
                }
                if (cc.css) contentHtml = `<style>${cc.css}</style>` + contentHtml;
            } else if (type === 'image') {
                const items = col.content_items || [];
                if (items.length) {
                    contentHtml = '<div style="display:flex;gap:8px;flex-wrap:wrap">' +
                        items.map(img => {
                            const src = (typeof img === 'object') ? (img.pc_image || '') : '';
                            return src ? `<img src="${src}" style="max-width:120px;height:auto;border-radius:4px">` : '';
                        }).join('') + '</div>';
                }
            } else if (type) {
                contentHtml = `<div style="padding:20px;text-align:center;color:#aaa;font-size:0.85rem;background:#f8f9fa;border-radius:6px;">${this._esc(type)} 콘텐츠</div>`;
            } else {
                const hasBg = colBg.color || colBg.gradient || colBg.image;
                const emptyBg = hasBg ? '' : 'background:repeating-linear-gradient(45deg,#f8f9fa,#f8f9fa 10px,#fff 10px,#fff 20px);';
                contentHtml = `<div style="min-height:300px;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:2rem;font-weight:300;${emptyBg}">${i+1}칸 — 콘텐츠를 추가하세요</div>`;
            }

            colsHtml += `<div style="min-height:300px;overflow:hidden;box-sizing:border-box;border:2px solid #dee2e6;border-radius:4px;${colStyle}">${titleHtml}${contentHtml}</div>`;
        });

        return `<div style="${rowStyle}">
            <div style="${containerStyle}">
                <div style="display:flex;align-items:stretch;${flexGap}">${colsHtml}</div>
            </div>
        </div>`;
    },

    _buildBgStyle(bg) {
        let s = '';
        if (bg.gradient) {
            s += `background:${bg.gradient};`;
        } else if (bg.color) {
            s += `background-color:${bg.color};`;
        }
        if (bg.image) {
            if (bg.gradient) {
                s += `background:${bg.gradient}, url('${bg.image}');`;
            } else {
                s += `background-image:url('${bg.image}');`;
            }
            s += `background-position:${bg.position || 'center'};`;
            s += `background-size:${bg.size || 'cover'};`;
            s += `background-repeat:${bg.repeat || 'no-repeat'};`;
        }
        return s;
    },

    /**
     * 현재 편집 행으로 스크롤
     */
    _scrollToCurrent() {
        const cur = document.getElementById('be-current-row');
        if (!cur) return;

        const isFirst = !cur.previousElementSibling;
        if (isFirst) {
            this.el.scrollTop = 0;
            return;
        }

        // 스케일된 요소의 실제 위치 계산
        const curRect = cur.getBoundingClientRect();
        const elRect = this.el.getBoundingClientRect();
        const viewH = this.el.clientHeight;

        // 현재 행이 미리보기 중앙에 오도록
        const curCenter = curRect.top - elRect.top + this.el.scrollTop + (curRect.height / 2);
        this.el.scrollTop = Math.max(0, curCenter - viewH / 2);
    },

    /**
     * 서버 HTML 삽입 후 Swiper/AOS 등 JS 초기화
     */
    _initScripts(container) {
        if (!container) return;
        // MubloItemLayout (Swiper 슬라이드)
        if (typeof MubloItemLayout !== 'undefined') {
            container.querySelectorAll('.mublo-item-layout').forEach(el => {
                MubloItemLayout.init(el);
            });
        }
        // AOS
        if (typeof AOS !== 'undefined') {
            AOS.refresh();
        }
    },

    // 호환용
    _buildOverlay() {},
    updateOverlay() {},

    _esc(str) {
        const d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }
};
