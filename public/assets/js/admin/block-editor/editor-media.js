/**
 * BE.EditorMedia — 이미지 / 동영상 / Include 렌더링 + 이벤트
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

    const EditorMedia = {

        renderImage(col, index) {
            const items = col.content_items || [];
            const cards = items.map((img, idx) => {
                const pcImg = typeof img === 'object' ? (img.pc_image || '') : '';
                const moImg = typeof img === 'object' ? (img.mo_image || '') : '';
                const link = typeof img === 'object' ? (img.link || '') : '';

                return `
                    <div class="be-editor__image-card" data-img-idx="${idx}">
                        <div class="be-editor__image-card-thumb">
                            ${pcImg ? `<img src="${pcImg}">` : '<i class="bi bi-image text-muted" style="font-size:2rem"></i>'}
                        </div>
                        <div class="be-editor__image-card-body">
                            <div class="d-flex gap-1 mb-1">
                                <input type="file" class="form-control form-control" data-img-upload="pc" data-img-idx="${idx}" accept="image/*" style="font-size:0.85rem" title="PC 이미지">
                            </div>
                            <div class="d-flex gap-1 mb-1">
                                <input type="file" class="form-control form-control" data-img-upload="mo" data-img-idx="${idx}" accept="image/*" style="font-size:0.85rem" title="MO 이미지">
                            </div>
                            <input type="text" class="form-control form-control" data-img-link="${idx}" value="${esc(link)}" placeholder="링크 URL" style="font-size:0.85rem">
                            <button type="button" class="btn btn-sm btn-outline-danger mt-1 w-100" data-img-delete="${idx}" style="font-size:0.85rem">삭제</button>
                        </div>
                    </div>`;
            }).join('');

            return `
                <div class="be-editor__split">
                    <div class="be-editor__image-grid">
                        ${cards}
                        <div class="be-editor__image-add" id="be-img-add">
                            <i class="bi bi-plus-lg" style="font-size:1.5rem"></i>
                            <div class="small mt-1">이미지 추가</div>
                        </div>
                    </div>
                </div>`;
        },

        renderInclude(col, index) {
            const cc = col.content_config || {};
            return `
                <div style="padding:20px">
                    <label class="form-label small fw-bold">Include 파일 경로</label>
                    <input type="text" class="form-control" id="be-include-path"
                           value="${esc(cc.path || '')}" placeholder="views/Block/include/Basic.php">
                    <div class="small text-muted mt-2">PHP 파일을 직접 포함합니다. 최고관리자만 사용 가능합니다.</div>
                </div>`;
        },

        renderMovie(col, index) {
            const cc = col.content_config || {};
            return `
                <div style="padding:20px">
                    <label class="form-label small fw-bold">동영상 URL</label>
                    <input type="text" class="form-control" id="be-movie-url"
                           value="${esc(cc.url || '')}" placeholder="https://youtube.com/...">
                </div>`;
        },

        bindImageEvents(index, panelEl) {
            const Store = BE.Store;

            // 이미지 추가
            document.getElementById('be-img-add')?.addEventListener('click', () => {
                const items = [...(Store.getColumn(index).content_items || [])];
                items.push({ pc_image: '', mo_image: '', link: '' });
                Store.updateColumn(index, { content_items: items });
                BE.EditorPanel.render();
            });

            // 이미지 삭제
            panelEl.querySelectorAll('[data-img-delete]').forEach(btn => {
                btn.addEventListener('click', () => {
                    const idx = parseInt(btn.dataset.imgDelete);
                    const items = [...(Store.getColumn(index).content_items || [])];
                    items.splice(idx, 1);
                    Store.updateColumn(index, { content_items: items });
                    BE.EditorPanel.render();
                });
            });

            // 이미지 업로드
            panelEl.querySelectorAll('[data-img-upload]').forEach(input => {
                input.addEventListener('change', () => {
                    if (!input.files[0]) return;
                    const imgIdx = parseInt(input.dataset.imgIdx);
                    const type = input.dataset.imgUpload;
                    this._uploadImage(input.files[0], 'content', (url) => {
                        const items = [...(Store.getColumn(index).content_items || [])];
                        if (items[imgIdx]) {
                            items[imgIdx] = { ...items[imgIdx], [`${type}_image`]: url };
                            Store.updateColumn(index, { content_items: items });
                            BE.EditorPanel.render();
                        }
                    });
                });
            });

            // 링크 변경
            panelEl.querySelectorAll('[data-img-link]').forEach(input => {
                input.addEventListener('change', () => {
                    const imgIdx = parseInt(input.dataset.imgLink);
                    const items = [...(Store.getColumn(index).content_items || [])];
                    if (items[imgIdx]) {
                        items[imgIdx] = { ...items[imgIdx], link: input.value };
                        Store.updateColumn(index, { content_items: items });
                    }
                });
            });
        },

        async _uploadImage(file, target, callback) {
            const formData = new FormData();
            formData.append('file', file);
            formData.append('target', target);

            try {
                const csrfToken = await MubloRequest.getCsrfToken();
                const res = await fetch('/admin/block-row/editor-upload', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': csrfToken }
                }).then(r => r.json());
                if (res.result === 'success' && res.data?.url) callback(res.data.url);
                else alert(res.message || '업로드에 실패했습니다.');
            } catch { alert('업로드 중 오류가 발생했습니다.'); }
        },
    };

    BE.EditorMedia = EditorMedia;
})();
