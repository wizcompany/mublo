/**
 * BE.Main — 오케스트레이터 (init, 이벤트 연결, 저장)
 *
 * 로드 순서: store → adapter → preview → editor-panel → inspector → main
 */
window.BE = window.BE || {};

BE.Main = {
    config: {},

    init(config) {
        this.config = config;

        const Store = BE.Store;
        const Preview = BE.Preview;
        const EditorPanel = BE.EditorPanel;
        const Inspector = BE.Inspector;

        Preview.init();
        EditorPanel.init(document.getElementById('be-editor'), config);
        Inspector.init(document.getElementById('be-settings'), config);

        // ── Store 이벤트 → UI 갱신 ──

        Store.on('loaded', () => {
            Preview.refresh();
            Inspector.render();
            EditorPanel.render();
        });

        let _previewTimer = null;
        const debouncedRefresh = () => {
            clearTimeout(_previewTimer);
            _previewTimer = setTimeout(() => Preview.refresh(), 300);
        };

        Store.on('row-updated', () => {
            debouncedRefresh();
        });

        Store.on('column-updated', (index) => {
            debouncedRefresh();
        });

        Store.on('columns-changed', () => {
            Preview.refresh();
            Inspector.render();
            EditorPanel.render();
        });

        Store.on('selection-changed', () => {
            Preview.updateOverlay();
            Inspector.onSelectionChanged();
            EditorPanel.render();
        });

        Store.on('dirty', () => {
            const el = document.getElementById('be-dirty');
            if (el) el.classList.toggle('be-toolbar__dirty--show', Store.isDirty());
        });

        // ── Toolbar 버튼 ──

        document.getElementById('be-btn-save')?.addEventListener('click', () => this.save());

        // ── 레이아웃 높이 계산 ──

        this._calcLayout();
        window.addEventListener('resize', () => this._calcLayout());

        // ── 미리보기 리사이즈 ──

        this._initResize();

        // ── 데이터 로드 ──

        this.loadData(config.rowId);

        // ── 페이지 이탈 경고 ──

        window.addEventListener('beforeunload', (e) => {
            if (Store.isDirty()) {
                e.preventDefault();
                e.returnValue = '';
            }
        });
    },

    loadData(rowId) {
        MubloRequest.requestQuery(`/admin/block-row/editor-load?id=${rowId}`)
            .then(response => {
                if (response.data) {
                    const state = BE.Adapter.fromServer(response.data);
                    BE.Preview.setSiblingRows(response.data.siblingRows || []);
                    BE.Preview.setServerRowHtml(response.data.currentRowHtml || '');

                    // 스킨 CSS 로드
                    (response.data.currentRowCss || []).forEach(url => {
                        if (!document.querySelector(`link[href="${url}"]`)) {
                            const link = document.createElement('link');
                            link.rel = 'stylesheet';
                            link.href = url;
                            document.head.appendChild(link);
                        }
                    });

                    BE.Store.load(state);
                }
            })
            .catch(() => {
                const loading = document.getElementById('be-preview-loading');
                if (loading) {
                    loading.innerHTML = `
                        <span class="text-danger">데이터를 불러올 수 없습니다.</span>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="be-retry-load">
                            <i class="bi bi-arrow-clockwise me-1"></i>다시 시도
                        </button>`;
                    document.getElementById('be-retry-load')?.addEventListener('click', () => {
                        loading.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 로딩 중...';
                        this.loadData(rowId);
                    });
                }
            });
    },

    save() {
        const Store = BE.Store;
        const btn = document.getElementById('be-btn-save');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> 저장 중...';

        const payload = BE.Adapter.toFormData();
        const rowId = Store.getRow().row_id;

        MubloRequest.requestJson('/admin/block-row/editor-save', payload)
            .then(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> 저장';
                // 저장 후 서버 데이터 재로드
                return MubloRequest.requestQuery(`/admin/block-row/editor-load?id=${rowId}`);
            })
            .then(response => {
                if (response.data) {
                    const state = BE.Adapter.fromServer(response.data);
                    Store.load(state);
                }
            })
            .catch(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg"></i> 저장';
            });
    },

    /**
     * 전체 높이 계산 — admin top-header 높이를 측정하여
     * 에디터 컨테이너가 정확히 남은 영역을 차지하도록 설정
     */
    _calcLayout() {
        const editor = document.getElementById('block-editor');
        if (!editor) return;

        // admin top-header 높이 측정
        const topHeader = document.querySelector('.top-header');
        const headerH = topHeader ? topHeader.offsetHeight : 56;

        // content-area 패딩 (CSS로 0으로 설정했지만 안전하게)
        const contentArea = editor.closest('.content-area');
        const caPadding = contentArea
            ? parseInt(getComputedStyle(contentArea).paddingTop) + parseInt(getComputedStyle(contentArea).paddingBottom)
            : 0;

        const availableH = window.innerHeight - headerH - caPadding;
        editor.style.height = availableH + 'px';
    },

    _initResize() {
        const handle = document.getElementById('be-resize-handle');
        const preview = document.querySelector('.be-preview');
        if (!handle || !preview) return;

        let startY = 0;
        let startHeight = 0;

        handle.addEventListener('mousedown', (e) => {
            e.preventDefault();
            startY = e.clientY;
            startHeight = preview.offsetHeight;

            const onMove = (e) => {
                const delta = e.clientY - startY;
                const newHeight = Math.max(100, Math.min(window.innerHeight * 0.7, startHeight + delta));
                preview.style.flex = 'none';
                preview.style.height = newHeight + 'px';
            };

            const onUp = () => {
                document.removeEventListener('mousemove', onMove);
                document.removeEventListener('mouseup', onUp);
            };

            document.addEventListener('mousemove', onMove);
            document.addEventListener('mouseup', onUp);
        });
    }
};

// 전역 진입점
window.BlockRowEditor = { init: (config) => BE.Main.init(config) };
