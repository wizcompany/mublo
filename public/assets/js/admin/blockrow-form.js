/**
 * BlockRow Form - 블록 행 설정 폼 관리
 *
 * 블록 시스템의 핵심 컴포넌트로, 행(Row)과 칸(Column) 설정을 담당합니다.
 *
 * @requires MubloRequest
 * @requires Bootstrap 5
 */
(function() {
    'use strict';

    /**
     * BlockRowForm 클래스
     * 블록 행 설정 폼의 전체 로직을 관리
     */
    class BlockRowForm {
        constructor(config) {
            this.config = config || {};
            this.contentTypes = config.contentTypes || [];
            this.contentTypeGroups = config.contentTypeGroups || {};
            this.skinLists = config.skinLists || {};
            this.domainId = config.domainId || 1;

            this.elements = {
                columnCount: document.getElementById('column_count'),
                columnsPreview: document.getElementById('columns-preview'),
                columnsData: document.getElementById('columns-data'),
                columnModal: document.getElementById('columnModal'),
                previewModal: document.getElementById('previewModal'),
                contentTypeSelect: document.getElementById('modal_content_type'),
                skinSelect: document.getElementById('modal_content_skin'),
                contentItemsContainer: document.getElementById('content_items_container')
            };

            // DualListbox 인스턴스
            this.dualListbox = null;
            // Plugin Custom UI 모드 인스턴스
            this.pluginSelector = null;

            this.init();
        }

        /**
         * 초기화
         */
        init() {
            this.bindEvents();
        }

        /**
         * 이벤트 바인딩
         */
        bindEvents() {
            // 칸 수 변경
            if (this.elements.columnCount) {
                this.elements.columnCount.addEventListener('change', (e) => this.onColumnCountChange(e));
            }

            // 칸 간격 변경 시 프리뷰 gap 업데이트
            const columnMarginInput = document.querySelector('input[name="formData[column_margin]"]');
            if (columnMarginInput) {
                columnMarginInput.addEventListener('input', () => this.updatePreviewGap());
            }

            // 콘텐츠 타입 변경
            if (this.elements.contentTypeSelect) {
                this.elements.contentTypeSelect.addEventListener('change', (e) => {
                    const contentType = e.target.value;
                    this.toggleHtmlEditor(contentType);
                    this.updateSkinList(contentType);
                    this.loadContentItems(contentType);
                });
            }

            // 제목 표시 체크박스 변경
            const titleShowCheckbox = document.getElementById('modal_title_show');
            if (titleShowCheckbox) {
                titleShowCheckbox.addEventListener('change', (e) => {
                    this.toggleTitleDetailWrapper(e.target.checked);
                });
            }

            // 출력갯수 변경 → DualListbox/Plugin maxItems 동기화
            const countPcInput = document.getElementById('modal_content_count_pc');
            const countMoInput = document.getElementById('modal_content_count_mo');
            const syncMaxItems = () => {
                const max = this.getMaxItemCount();
                if (this.dualListbox && typeof this.dualListbox.setMaxItems === 'function') {
                    this.dualListbox.setMaxItems(max);
                }
                if (this.pluginSelector && this.pluginSelector._dualListbox
                    && typeof this.pluginSelector._dualListbox.setMaxItems === 'function') {
                    this.pluginSelector._dualListbox.setMaxItems(max);
                }
            };
            if (countPcInput) countPcInput.addEventListener('change', syncMaxItems);
            if (countMoInput) countMoInput.addEventListener('change', syncMaxItems);

            // 이미지 추가 버튼
            const addImageBtn = document.getElementById('btn_add_image');
            if (addImageBtn) {
                addImageBtn.addEventListener('click', () => this.addImageItem());
            }

            // 칸 배경: 컬러 픽커 ↔ 텍스트 동기화
            const modalBgPicker = document.getElementById('modal_bg_color_picker');
            const modalBgText = document.getElementById('modal_bg_color');
            if (modalBgPicker && modalBgText) {
                modalBgPicker.addEventListener('input', () => {
                    modalBgText.value = modalBgPicker.value;
                });
                modalBgText.addEventListener('input', () => {
                    if (/^#[0-9A-Fa-f]{6}$/.test(modalBgText.value)) {
                        modalBgPicker.value = modalBgText.value;
                    }
                });
            }

            // 제목 색상: 컬러 픽커 ↔ 텍스트 동기화
            this.initColorSync('modal_title_color_picker', 'modal_title_color');
            // 문구 색상: 컬러 픽커 ↔ 텍스트 동기화
            this.initColorSync('modal_copytext_color_picker', 'modal_copytext_color');

            // 칸 배경: 이미지 파일 선택 시 미리보기 + pendingFiles 저장
            const modalBgImageFile = document.getElementById('modal_bg_image_file');
            if (modalBgImageFile) {
                modalBgImageFile.addEventListener('change', (e) => this.handleModalBgImageChange(e));
            }

            // 칸 배경: 이미지 삭제 체크박스
            const modalBgImageDel = document.getElementById('modal_bg_image_del');
            if (modalBgImageDel) {
                modalBgImageDel.addEventListener('change', (e) => {
                    if (e.target.checked) {
                        this._modalBgImageDeleted = true;
                        document.getElementById('modal_bg_image').value = '';
                        this.toggleModalBgImageOptions();
                    } else {
                        this._modalBgImageDeleted = false;
                        // 기존 URL 복원
                        document.getElementById('modal_bg_image').value = this._modalBgImageOriginal || '';
                        this.toggleModalBgImageOptions();
                    }
                });
            }

            // 제목 이미지: 파일 선택 시 미리보기
            ['pc', 'mo'].forEach(type => {
                const fileInput = document.getElementById(`modal_title_${type}_image_file`);
                if (fileInput) {
                    fileInput.addEventListener('change', (e) => {
                        const file = e.target.files[0];
                        if (!file) return;
                        if (type === 'pc') this._titlePcPendingFile = file;
                        else this._titleMoPendingFile = file;
                        const previewDiv = document.getElementById(`modal_title_${type}_image_preview`);
                        const previewImg = previewDiv?.querySelector('img');
                        if (previewImg) previewImg.src = URL.createObjectURL(file);
                        if (previewDiv) previewDiv.style.display = '';
                        const delCheck = document.getElementById(`modal_title_${type}_image_del`);
                        if (delCheck) delCheck.checked = false;
                    });
                }
                const delCheck = document.getElementById(`modal_title_${type}_image_del`);
                if (delCheck) {
                    delCheck.addEventListener('change', (e) => {
                        if (e.target.checked) {
                            document.getElementById(`modal_title_${type}_image`).value = '';
                            if (type === 'pc') this._titlePcPendingFile = null;
                            else this._titleMoPendingFile = null;
                        }
                    });
                }
            });

            // 슬라이드 옵션 토글: 스타일 변경 시
            const pcStyleSelect = document.getElementById('modal_pc_style');
            const moStyleSelect = document.getElementById('modal_mo_style');
            if (pcStyleSelect) pcStyleSelect.addEventListener('change', () => this.toggleSlideOptions());
            if (moStyleSelect) moStyleSelect.addEventListener('change', () => this.toggleSlideOptions());

            // autoplay 체크박스 → delay input 활성/비활성
            const pcAutoCheck = document.getElementById('modal_pc_autoplay_check');
            const moAutoCheck = document.getElementById('modal_mo_autoplay_check');
            if (pcAutoCheck) pcAutoCheck.addEventListener('change', (e) => {
                document.getElementById('modal_pc_autoplay_delay').disabled = !e.target.checked;
            });
            if (moAutoCheck) moAutoCheck.addEventListener('change', (e) => {
                document.getElementById('modal_mo_autoplay_delay').disabled = !e.target.checked;
            });
        }

        /**
         * 슬라이드 옵션 표시/숨김 토글
         */
        toggleSlideOptions() {
            const pcStyle = document.getElementById('modal_pc_style')?.value || 'list';
            const moStyle = document.getElementById('modal_mo_style')?.value || 'list';

            const pcOpts = document.getElementById('pc_slide_options');
            if (pcOpts) pcOpts.style.display = pcStyle === 'slide' ? '' : 'none';

            const moOpts = document.getElementById('mo_slide_options');
            if (moOpts) moOpts.style.display = moStyle === 'slide' ? '' : 'none';
        }

        /**
         * 컬러 픽커 ↔ 텍스트 입력 동기화 헬퍼
         */
        initColorSync(pickerId, textId) {
            const picker = document.getElementById(pickerId);
            const text = document.getElementById(textId);
            if (!picker || !text) return;
            picker.addEventListener('input', () => { text.value = picker.value; });
            text.addEventListener('input', () => {
                if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) picker.value = text.value;
            });
        }

        /**
         * 칸 배경 이미지 파일 선택 핸들러
         */
        handleModalBgImageChange(e) {
            const input = e.target;
            if (!input.files || !input.files[0]) return;

            const file = input.files[0];
            const previewDiv = document.getElementById('modal_bg_image_preview');
            const previewImg = previewDiv?.querySelector('img');

            // FileReader로 미리보기
            const reader = new FileReader();
            reader.onload = (event) => {
                if (previewImg) {
                    previewImg.src = event.target.result;
                }
                if (previewDiv) {
                    previewDiv.style.display = '';
                }
            };
            reader.readAsDataURL(file);

            // pendingFiles에 저장
            if (!this.pendingFiles) this.pendingFiles = {};
            this._modalBgPendingFile = file;

            // 삭제 체크박스 해제
            const delCheckbox = document.getElementById('modal_bg_image_del');
            if (delCheckbox) delCheckbox.checked = false;
            this._modalBgImageDeleted = false;

            // 이미지 옵션 표시
            this.toggleModalBgImageOptions(true);
        }

        /**
         * 칸 배경 이미지 옵션 표시/숨김
         */
        toggleModalBgImageOptions(forceShow) {
            const hasImage = forceShow || document.getElementById('modal_bg_image')?.value || this._modalBgPendingFile;
            const isDeleted = this._modalBgImageDeleted;
            const optionsEl = document.getElementById('modal_bg_image_options');
            if (optionsEl) {
                optionsEl.style.display = (hasImage && !isDeleted) ? '' : 'none';
            }
        }

        /**
         * 칸 배경 이미지 파일을 메인 폼에 동적 file input으로 첨부
         */
        attachBgFileToForm(columnIndex) {
            const btn = document.querySelector('.mublo-submit');
            const form = btn ? btn.closest('form') : document.querySelector('form');
            if (!form) return;

            // 기존 동적 file input 제거
            const existingInput = form.querySelector(`input[name="column_bg_image[${columnIndex}]"]`);
            if (existingInput) existingInput.remove();

            if (this._modalBgPendingFile) {
                const fileInput = document.createElement('input');
                fileInput.type = 'file';
                fileInput.name = `column_bg_image[${columnIndex}]`;
                fileInput.style.display = 'none';

                const dt = new DataTransfer();
                dt.items.add(this._modalBgPendingFile);
                fileInput.files = dt.files;

                form.appendChild(fileInput);
            }
        }

        /**
         * 제목 이미지 미리보기 로드
         */
        loadTitleImagePreview(type, imageUrl) {
            const fileInput = document.getElementById(`modal_title_${type}_image_file`);
            const hiddenInput = document.getElementById(`modal_title_${type}_image`);
            const previewDiv = document.getElementById(`modal_title_${type}_image_preview`);
            const previewImg = previewDiv?.querySelector('img');
            const delCheck = document.getElementById(`modal_title_${type}_image_del`);

            if (fileInput) fileInput.value = '';
            if (hiddenInput) hiddenInput.value = imageUrl;
            if (delCheck) delCheck.checked = false;

            if (imageUrl) {
                if (previewImg) previewImg.src = imageUrl;
                if (previewDiv) previewDiv.style.display = '';
            } else {
                if (previewDiv) previewDiv.style.display = 'none';
            }
        }

        /**
         * 제목 이미지 파일을 메인 폼에 동적 file input으로 첨부
         */
        attachTitleImageFilesToForm(columnIndex) {
            const btn = document.querySelector('.mublo-submit');
            const form = btn ? btn.closest('form') : document.querySelector('form');
            if (!form) return;

            ['pc', 'mo'].forEach(type => {
                const inputName = `column_title_image[${columnIndex}][${type}]`;
                const existing = form.querySelector(`input[name="${inputName}"]`);
                if (existing) existing.remove();

                const file = type === 'pc' ? this._titlePcPendingFile : this._titleMoPendingFile;
                if (file) {
                    const fileInput = document.createElement('input');
                    fileInput.type = 'file';
                    fileInput.name = inputName;
                    fileInput.style.display = 'none';
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    fileInput.files = dt.files;
                    form.appendChild(fileInput);
                }
            });
        }

        /**
         * 제목 상세 설정 영역 표시/숨김
         */
        toggleTitleDetailWrapper(show) {
            const wrapper = document.getElementById('title_detail_wrapper');
            if (wrapper) {
                wrapper.style.display = show ? 'block' : 'none';
            }
        }

        /**
         * 콘텐츠 타입별 아이템 목록 로드
         *
         * 2-모드 시스템:
         * 1. DualListbox 모드 (기본) — AJAX로 아이템 목록 조회 → Core DualListbox UI
         * 2. Custom UI 모드 (고급) — Plugin JS 로드 → Plugin이 UI 전체 소유
         */
        async loadContentItems(contentType, selectedItems = []) {
            const container = this.elements.contentItemsContainer;
            if (!container) return;

            const typeInfo = this.contentTypes.find(ct => ct.value === contentType);

            // 아이템 선택 불필요
            if (!typeInfo?.hasItems) {
                container.style.display = 'none';
                this.destroyCurrentSelector();
                return;
            }

            container.style.display = 'block';

            // Custom UI 모드: Plugin JS 로드
            if (typeInfo.adminScript) {
                await this.loadPluginItemSelector(typeInfo, selectedItems);
                return;
            }

            // DualListbox 모드 (기존 흐름)
            const listContainer = container.querySelector('.dual-listbox-wrapper');
            if (listContainer) {
                listContainer.innerHTML = '<div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div> 목록 로딩 중...</div>';
            }

            try {
                const response = await MubloRequest.requestJson(
                    `/admin/block-row/get-content-items?content_type=${contentType}`
                );

                if (response.success && response.data && response.data.items) {
                    this.destroyCurrentSelector();
                    this.initDualListbox(response.data.items, selectedItems);
                } else {
                    if (listContainer) {
                        listContainer.innerHTML = '<p class="text-muted">선택 가능한 아이템이 없습니다.</p>';
                    }
                }
            } catch (error) {
                console.error('아이템 로드 실패:', error);
                if (listContainer) {
                    listContainer.innerHTML = '<p class="text-danger">아이템을 불러오는데 실패했습니다.</p>';
                }
            }
        }

        /**
         * DualListbox 초기화
         */
        initDualListbox(items, selectedIds = []) {
            const container = this.elements.contentItemsContainer?.querySelector('.dual-listbox-wrapper');
            if (!container) return;

            this.dualListbox = new DualListbox(container, {
                available: items,
                selected: selectedIds,
                maxItems: this.getMaxItemCount(),
                leftTitle: '사용 가능',
                rightTitle: '선택됨',
                onChanged: (selected) => {
                    // 선택 변경 시 hidden input 업데이트 (선택사항)
                    console.log('선택된 아이템:', selected);
                }
            });
        }

        /**
         * Plugin Custom UI 모드 — Plugin JS 로드 후 init() 호출
         */
        async loadPluginItemSelector(typeInfo, selectedItems) {
            const container = this.elements.contentItemsContainer;
            const listContainer = container?.querySelector('.dual-listbox-wrapper');
            if (!listContainer) return;

            listContainer.innerHTML = '<div class="text-center text-muted py-3"><div class="spinner-border spinner-border-sm"></div> 플러그인 UI 로딩 중...</div>';

            try {
                await this.ensureScript(typeInfo.adminScript);

                const initName = typeInfo.adminScriptInit;
                const pluginModule = initName ? window[initName] : null;

                if (!pluginModule || typeof pluginModule.init !== 'function') {
                    listContainer.innerHTML = '<p class="text-danger">플러그인 아이템 선택기를 초기화할 수 없습니다.</p>';
                    return;
                }

                this.destroyCurrentSelector();
                listContainer.innerHTML = '';

                pluginModule.init(listContainer, {
                    selectedItems: selectedItems,
                    domainId: this.domainId,
                    maxItems: this.getMaxItemCount(),
                    config: this._currentContentConfig || {}
                });

                this.pluginSelector = pluginModule;
            } catch (error) {
                console.error('플러그인 아이템 선택기 로드 실패:', error);
                if (listContainer) {
                    listContainer.innerHTML = '<p class="text-danger">플러그인 UI를 불러오는데 실패했습니다.</p>';
                }
            }
        }

        /**
         * 현재 선택기 정리 (DualListbox / Plugin 모두)
         */
        destroyCurrentSelector() {
            if (this.pluginSelector && typeof this.pluginSelector.destroy === 'function') {
                this.pluginSelector.destroy();
            }
            this.pluginSelector = null;
            this.dualListbox = null;
        }

        /**
         * 출력갯수 필드에서 최대 선택 가능 수 산출
         */
        getMaxItemCount() {
            const pc = parseInt(document.getElementById('modal_content_count_pc')?.value) || 0;
            const mo = parseInt(document.getElementById('modal_content_count_mo')?.value) || 0;
            return Math.max(pc, mo);
        }

        /**
         * 스크립트 동적 로드 (Promise)
         */
        ensureScript(src) {
            return new Promise((resolve, reject) => {
                if (document.querySelector(`script[src="${src}"]`)) {
                    resolve();
                    return;
                }

                const script = document.createElement('script');
                script.src = src;
                script.onload = resolve;
                script.onerror = () => reject(new Error(`스크립트 로드 실패: ${src}`));
                document.head.appendChild(script);
            });
        }

        /**
         * 스킨 목록 업데이트
         */
        updateSkinList(contentType) {
            const skinSelect = this.elements.skinSelect;
            if (!skinSelect) return;

            // 스킨이 필요 없는 타입
            const noSkinTypes = ['html', 'include'];
            const skinWrapper = document.getElementById('content_skin_wrapper');

            if (!contentType || noSkinTypes.includes(contentType)) {
                // 스킨 선택 숨김
                if (skinWrapper) skinWrapper.style.display = 'none';
                skinSelect.innerHTML = '<option value="">스킨 없음</option>';
                return;
            }

            // 스킨 선택 표시
            if (skinWrapper) skinWrapper.style.display = '';

            // 해당 콘텐츠 타입의 스킨 목록 가져오기
            const skins = this.skinLists[contentType] || [];

            // 옵션 생성
            skinSelect.innerHTML = '';

            if (skins.length === 0) {
                skinSelect.innerHTML = '<option value="">스킨 없음</option>';
            } else {
                skins.forEach(skin => {
                    const option = document.createElement('option');
                    option.value = skin.value;
                    option.textContent = skin.label;
                    skinSelect.appendChild(option);
                });
            }
        }

        /**
         * 칸 간격 변경 시 프리뷰 gap 및 칸 너비 재계산
         */
        updatePreviewGap() {
            const preview = this.elements.columnsPreview;
            const margin = parseInt(document.querySelector('input[name="formData[column_margin]"]')?.value) || 0;
            const count = parseInt(this.elements.columnCount?.value) || 1;
            const gapTotal = margin * (count - 1);

            preview.style.gap = margin + 'px';

            preview.querySelectorAll('.column-preview-item').forEach((card, i) => {
                const widthInput = this.elements.columnsData.querySelector(`input[name="columns[${i}][width]"]`);
                const colWidth = widthInput?.value || '';
                card.style.cssText = colWidth
                    ? `flex: 0 0 calc(${colWidth} - ${gapTotal}px / ${count}); min-width: 150px;`
                    : 'flex: 1; min-width: 200px;';
            });
        }

        /**
         * 칸 수 변경 핸들러
         */
        onColumnCountChange(e) {
            const count = parseInt(e.target.value);
            const preview = this.elements.columnsPreview;
            const dataContainer = this.elements.columnsData;

            // 기존 칸 데이터 저장
            const existingData = [];
            for (let i = 0; i < 4; i++) {
                const input = dataContainer.querySelector(`input[name="columns[${i}][content_type]"]`);
                if (input) {
                    existingData[i] = this.getColumnData(i);
                }
            }

            // 프리뷰 재생성 - gap 반영
            const columnMargin = parseInt(document.querySelector('input[name="formData[column_margin]"]')?.value) || 0;
            preview.style.gap = columnMargin + 'px';
            preview.innerHTML = '';
            dataContainer.innerHTML = '';

            for (let i = 0; i < count; i++) {
                const data = existingData[i] || {};

                // 프리뷰 카드
                const card = document.createElement('div');
                card.className = 'column-preview-item card';
                const colWidth = data.width || '';
                const gapTotal = columnMargin * (count - 1);
                card.style.cssText = colWidth
                    ? `flex: 0 0 calc(${colWidth} - ${gapTotal}px / ${count}); min-width: 150px;`
                    : 'flex: 1; min-width: 200px;';
                card.dataset.index = i;

                const contentLabel = this.getContentTypeLabel(data.content_type);
                const widthBadge = colWidth
                    ? `<span class="column-width-badge badge bg-info ms-1">${colWidth}</span>`
                    : '';

                card.innerHTML = `
                    <div class="card-body text-center">
                        <h6 class="card-title">${i + 1}번째 칸${widthBadge}</h6>
                        <p class="card-text">
                            <span class="column-type-badge badge ${data.content_type ? 'bg-primary' : 'bg-secondary'}">${contentLabel || '미설정'}</span>
                        </p>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-column-index="${i}">
                            ${data.content_type ? '수정' : '설정'}
                        </button>
                    </div>
                `;

                // 버튼 이벤트 바인딩
                card.querySelector('button').addEventListener('click', () => this.openColumnModal(i));

                preview.appendChild(card);

                // Hidden inputs
                this.setColumnHiddenInputs(dataContainer, i, data);
            }
        }

        /**
         * 칸 데이터 가져오기
         */
        getColumnData(index) {
            const container = this.elements.columnsData;
            const get = (field) => {
                const input = container.querySelector(`input[name="columns[${index}][${field}]"]`);
                return input ? input.value : '';
            };

            return {
                width: get('width'),
                pc_padding: get('pc_padding'),
                mobile_padding: get('mobile_padding'),
                content_type: get('content_type'),
                content_kind: get('content_kind'),
                content_skin: get('content_skin'),
                background_config: get('background_config'),
                border_config: get('border_config'),
                title_config: get('title_config'),
                content_config: get('content_config'),
                content_items: get('content_items'),
                is_active: get('is_active')
            };
        }

        /**
         * Hidden input 설정
         *
         * DB 필드: width, pc_padding, mobile_padding, content_type, content_kind,
         *          content_skin, background_config, border_config, title_config,
         *          content_config, content_items, is_active
         */
        setColumnHiddenInputs(container, index, data) {
            const fields = [
                'width', 'pc_padding', 'mobile_padding', 'content_type', 'content_kind',
                'content_skin', 'background_config', 'border_config', 'title_config',
                'content_config', 'content_items', 'is_active'
            ];

            fields.forEach(field => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = `columns[${index}][${field}]`;
                input.value = data[field] || (field === 'is_active' ? '1' : '');
                container.appendChild(input);
            });
        }

        /**
         * 콘텐츠 타입 레이블 가져오기
         */
        getContentTypeLabel(type) {
            if (!type) return '';
            const found = this.contentTypes.find(ct => ct.value === type);
            return found ? found.label : type;
        }

        /**
         * 콘텐츠 타입별 UI 표시/숨김 토글
         */
        toggleHtmlEditor(contentType) {
            const htmlWrapper = document.getElementById('html_editor_wrapper');
            const includeWrapper = document.getElementById('include_path_wrapper');
            const imageWrapper = document.getElementById('image_config_wrapper');
            const movieWrapper = document.getElementById('movie_config_wrapper');
            const outloginWrapper = document.getElementById('outlogin_config_wrapper');
            const styleWrapper = document.getElementById('content_style_wrapper');
            const countWrapper = document.getElementById('content_count_wrapper');
            const countMoWrapper = document.getElementById('content_count_mo_wrapper');
            const skinWrapper = document.getElementById('content_skin_wrapper');
            const aosWrapper = document.getElementById('content_aos_wrapper');

            // 기본적으로 모두 숨김
            if (htmlWrapper) htmlWrapper.style.display = 'none';
            if (includeWrapper) includeWrapper.style.display = 'none';
            if (outloginWrapper) outloginWrapper.style.display = 'none';
            if (imageWrapper) imageWrapper.style.display = 'none';
            if (movieWrapper) movieWrapper.style.display = 'none';
            if (styleWrapper) styleWrapper.style.display = 'none';
            if (aosWrapper) aosWrapper.style.display = 'none';

            // 출력 스타일이 필요한 타입 (Registry의 hasStyle 옵션으로 동적 판단)
            const typeInfo = this.contentTypes.find(ct => ct.value === contentType);
            // 갯수 숨김 타입
            const noCountTypes = ['html', 'include', 'image', 'movie', 'outlogin'];
            // AOS 숨김 타입
            const noAosTypes = ['include', 'movie', 'outlogin'];

            if (contentType === 'html') {
                // HTML 타입: 에디터 표시, 갯수/스킨 숨김
                if (htmlWrapper) htmlWrapper.style.display = 'block';
                if (countWrapper) countWrapper.style.display = 'none';
                if (countMoWrapper) countMoWrapper.style.display = 'none';
                if (skinWrapper) skinWrapper.style.display = 'none';

                // MubloEditor 초기화 (아직 초기화되지 않은 경우)
                if (typeof MubloEditor !== 'undefined' && !MubloEditor.get('modal_html_content')) {
                    setTimeout(() => {
                        MubloEditor.create('#modal_html_content');
                    }, 50);
                }
            } else if (contentType === 'include') {
                // include 타입: 파일 경로 입력 표시, 갯수/스킨 숨김
                if (includeWrapper) includeWrapper.style.display = 'block';
                if (countWrapper) countWrapper.style.display = 'none';
                if (countMoWrapper) countMoWrapper.style.display = 'none';
                if (skinWrapper) skinWrapper.style.display = 'none';
            } else if (contentType === 'image') {
                // image 타입: 이미지 설정 + 출력 스타일(슬라이드/자동재생/반복) 표시, 갯수 숨김
                if (imageWrapper) imageWrapper.style.display = 'block';
                if (styleWrapper) styleWrapper.style.display = 'block';
                if (countWrapper) countWrapper.style.display = 'none';
                if (countMoWrapper) countMoWrapper.style.display = 'none';
                this.initImageItems();
                this.toggleSlideOptions();
            } else if (contentType === 'movie') {
                // movie 타입: 동영상 설정 표시, 갯수 숨김
                if (movieWrapper) movieWrapper.style.display = 'block';
                if (countWrapper) countWrapper.style.display = 'none';
                if (countMoWrapper) countMoWrapper.style.display = 'none';
                this.initVideoTypeChange();
            } else if (contentType === 'outlogin') {
                // outlogin 타입: 갯수 숨김, 스킨 + 전용 설정 표시
                if (countWrapper) countWrapper.style.display = 'none';
                if (countMoWrapper) countMoWrapper.style.display = 'none';
                if (skinWrapper) skinWrapper.style.display = '';
                if (outloginWrapper) outloginWrapper.style.display = 'block';
            } else {
                // 다른 타입: 갯수/스킨 표시
                if (countWrapper) countWrapper.style.display = '';
                if (countMoWrapper) countMoWrapper.style.display = '';
                if (skinWrapper) skinWrapper.style.display = '';
            }

            // 출력 스타일 영역 표시 (hasStyle 옵션이 있는 타입)
            if (typeInfo?.hasStyle) {
                if (styleWrapper) styleWrapper.style.display = 'block';
            }

            // AOS 이벤트: 복수 아이템 출력 타입에서 표시
            if (contentType && !noAosTypes.includes(contentType)) {
                if (aosWrapper) aosWrapper.style.display = '';
            }
        }

        /**
         * 이미지 아이템 관리 초기화
         */
        initImageItems(items = null) {
            const container = document.getElementById('image_items_container');
            if (!container) return;

            // 기존 아이템이 없으면 하나 추가
            if (!items || items.length === 0) {
                items = [{ pc_image: '', mo_image: '', link_url: '', link_win: '0' }];
            }

            this.imageItems = items;
            this.renderImageItems();
        }

        /**
         * 이미지 아이템 렌더링
         */
        renderImageItems() {
            const container = document.getElementById('image_items_container');
            if (!container || !this.imageItems) return;

            const noImage = '/assets/images/no-image.svg';

            container.innerHTML = this.imageItems.map((item, index) => `
                <div class="col-12 col-md-6 image-item-card" data-index="${index}">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center py-2">
                            <span class="small fw-bold">${index + 1}번 이미지</span>
                            ${this.imageItems.length > 1 ? `
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-image" data-index="${index}">
                                <i class="bi bi-x"></i>
                            </button>
                            ` : ''}
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <!-- PC 이미지 -->
                                <div class="col-6">
                                    <label class="form-label small">PC 이미지</label>
                                    <div class="image-preview-box mb-2" data-target="pc" data-index="${index}">
                                        <div class="ratio ratio-16x9 border rounded overflow-hidden bg-light">
                                            <div class="img-preview-inner" style="background-image: url('${item.pc_image || noImage}'); background-size: cover; background-position: center;"></div>
                                        </div>
                                    </div>
                                    <input type="hidden" class="img-pc-url" value="${item.pc_image || ''}">
                                    <input type="file" class="form-control form-control-sm img-pc-file" accept="image/*" data-index="${index}">
                                    ${item.pc_image ? `
                                    <div class="form-check mt-1">
                                        <input type="checkbox" class="form-check-input img-pc-del" data-index="${index}">
                                        <label class="form-check-label small text-muted">삭제</label>
                                    </div>
                                    ` : ''}
                                </div>
                                <!-- MO 이미지 -->
                                <div class="col-6">
                                    <label class="form-label small">MO 이미지</label>
                                    <div class="image-preview-box mb-2" data-target="mo" data-index="${index}">
                                        <div class="ratio ratio-16x9 border rounded overflow-hidden bg-light">
                                            <div class="img-preview-inner" style="background-image: url('${item.mo_image || noImage}'); background-size: cover; background-position: center;"></div>
                                        </div>
                                    </div>
                                    <input type="hidden" class="img-mo-url" value="${item.mo_image || ''}">
                                    <input type="file" class="form-control form-control-sm img-mo-file" accept="image/*" data-index="${index}">
                                    ${item.mo_image ? `
                                    <div class="form-check mt-1">
                                        <input type="checkbox" class="form-check-input img-mo-del" data-index="${index}">
                                        <label class="form-check-label small text-muted">삭제</label>
                                    </div>
                                    ` : ''}
                                    <small class="text-muted d-block mt-1">비워두면 PC 사용</small>
                                </div>
                                <!-- 링크 설정 -->
                                <div class="col-8 mt-2">
                                    <label class="form-label small">연결 URL</label>
                                    <input type="text" class="form-control form-control-sm img-link-url"
                                           value="${item.link_url || ''}" placeholder="https://">
                                </div>
                                <div class="col-4 mt-2">
                                    <label class="form-label small">새창</label>
                                    <select class="form-select form-select-sm img-link-win">
                                        <option value="0" ${item.link_win != '1' ? 'selected' : ''}>아니오</option>
                                        <option value="1" ${item.link_win == '1' ? 'selected' : ''}>예</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');

            // 삭제 버튼 이벤트 바인딩
            container.querySelectorAll('.btn-remove-image').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const idx = parseInt(e.currentTarget.dataset.index);
                    this.removeImageItem(idx);
                });
            });

            // 파일 업로드 이벤트 바인딩
            container.querySelectorAll('.img-pc-file, .img-mo-file').forEach(input => {
                input.addEventListener('change', (e) => this.handleImageFileChange(e));
            });

            // 이미지 삭제 체크박스 이벤트 바인딩
            container.querySelectorAll('.img-pc-del, .img-mo-del').forEach(checkbox => {
                checkbox.addEventListener('change', (e) => this.handleImageDelete(e));
            });
        }

        /**
         * 이미지 파일 변경 핸들러 (미리보기 + pendingFiles에 저장)
         */
        handleImageFileChange(e) {
            const input = e.target;
            const index = parseInt(input.dataset.index);
            const isPc = input.classList.contains('img-pc-file');
            const card = input.closest('.image-item-card');
            const previewBox = card.querySelector(`.image-preview-box[data-target="${isPc ? 'pc' : 'mo'}"] .img-preview-inner`);

            if (!input.files || !input.files[0]) return;

            const file = input.files[0];

            // FileReader로 미리보기 표시
            const reader = new FileReader();
            reader.onload = (event) => {
                if (previewBox) {
                    previewBox.style.backgroundImage = `url('${event.target.result}')`;
                }
            };
            reader.readAsDataURL(file);

            // pendingFiles에 파일 저장 (Form 전송 시 사용)
            if (!this.pendingFiles) this.pendingFiles = {};
            const fileKey = `col_img_${isPc ? 'pc' : 'mo'}_${index}`;
            this.pendingFiles[fileKey] = file;

            // imageItems 배열에 새 파일 표시 (실제 URL은 서버 업로드 후 설정됨)
            if (this.imageItems[index]) {
                if (isPc) {
                    this.imageItems[index].pc_image = '__pending__';
                    this.imageItems[index].pc_file_key = fileKey;
                } else {
                    this.imageItems[index].mo_image = '__pending__';
                    this.imageItems[index].mo_file_key = fileKey;
                }
            }
        }

        /**
         * 이미지 삭제 체크박스 핸들러
         */
        handleImageDelete(e) {
            const checkbox = e.target;
            const index = parseInt(checkbox.dataset.index);
            const isPc = checkbox.classList.contains('img-pc-del');
            const card = checkbox.closest('.image-item-card');
            const previewBox = card.querySelector(`.image-preview-box[data-target="${isPc ? 'pc' : 'mo'}"] .img-preview-inner`);
            const hiddenInput = card.querySelector(isPc ? '.img-pc-url' : '.img-mo-url');
            const noImage = '/assets/images/no-image.svg';

            if (checkbox.checked) {
                // 이미지 삭제
                if (previewBox) {
                    previewBox.style.backgroundImage = `url('${noImage}')`;
                }
                if (hiddenInput) {
                    hiddenInput.value = '';
                }
                if (this.imageItems[index]) {
                    if (isPc) {
                        this.imageItems[index].pc_image = '';
                        this.imageItems[index].pc_del = true;
                        // pendingFiles에서도 제거
                        if (this.pendingFiles) {
                            delete this.pendingFiles[`col_img_pc_${index}`];
                        }
                    } else {
                        this.imageItems[index].mo_image = '';
                        this.imageItems[index].mo_del = true;
                        if (this.pendingFiles) {
                            delete this.pendingFiles[`col_img_mo_${index}`];
                        }
                    }
                }
            }
        }

        /**
         * 칸 이미지 파일을 메인 폼에 동적 file input으로 추가
         * (saveColumnSettings 호출 시 실행)
         */
        attachPendingFilesToForm(columnIndex) {
            if (!this.pendingFiles || !this.imageItems) return;

            const form = document.querySelector('.mublo-submit');
            if (!form) return;

            // 기존 동적 file input 제거 (해당 칸의 것만)
            form.querySelectorAll(`input[name^="column_images[${columnIndex}]"]`).forEach(el => el.remove());

            // 현재 칸의 이미지 아이템들에 대해 file input 생성
            this.imageItems.forEach((item, imgIndex) => {
                // PC 이미지
                if (item.pc_file_key && this.pendingFiles[item.pc_file_key]) {
                    const pcInput = document.createElement('input');
                    pcInput.type = 'file';
                    pcInput.name = `column_images[${columnIndex}][${imgIndex}][pc]`;
                    pcInput.style.display = 'none';

                    // DataTransfer API로 파일 설정
                    const dt = new DataTransfer();
                    dt.items.add(this.pendingFiles[item.pc_file_key]);
                    pcInput.files = dt.files;

                    form.appendChild(pcInput);
                }

                // MO 이미지
                if (item.mo_file_key && this.pendingFiles[item.mo_file_key]) {
                    const moInput = document.createElement('input');
                    moInput.type = 'file';
                    moInput.name = `column_images[${columnIndex}][${imgIndex}][mo]`;
                    moInput.style.display = 'none';

                    const dt = new DataTransfer();
                    dt.items.add(this.pendingFiles[item.mo_file_key]);
                    moInput.files = dt.files;

                    form.appendChild(moInput);
                }
            });
        }

        /**
         * 이미지 아이템 추가
         */
        addImageItem() {
            if (!this.imageItems) this.imageItems = [];
            this.imageItems.push({ pc_image: '', mo_image: '', link_url: '', link_win: '0' });
            this.renderImageItems();
        }

        /**
         * 이미지 아이템 제거
         */
        removeImageItem(index) {
            if (this.imageItems && this.imageItems.length > 1) {
                this.imageItems.splice(index, 1);
                this.renderImageItems();
            }
        }

        /**
         * 이미지 아이템 데이터 수집 (서버 전송용)
         * - file_key 등 내부 정보 제외
         * - __pending__ 값은 서버에서 파일 업로드 후 URL로 대체됨
         */
        getImageItems() {
            const container = document.getElementById('image_items_container');
            if (!container) return [];

            const items = [];
            container.querySelectorAll('.image-item-card').forEach((card, idx) => {
                const pcUrl = card.querySelector('.img-pc-url')?.value || '';
                const moUrl = card.querySelector('.img-mo-url')?.value || '';

                // imageItems 배열에서 추가 정보 가져오기
                const itemData = this.imageItems?.[idx] || {};

                items.push({
                    // 기존 URL (pending이 아닌 경우)
                    pc_image: pcUrl !== '__pending__' ? pcUrl : '',
                    mo_image: moUrl !== '__pending__' ? moUrl : '',
                    // 새 파일이 있는 경우 표시 (서버에서 파일 처리 시 참조)
                    pc_has_file: !!itemData.pc_file_key,
                    mo_has_file: !!itemData.mo_file_key,
                    // 삭제 여부
                    pc_del: !!itemData.pc_del,
                    mo_del: !!itemData.mo_del,
                    // 링크 설정
                    link_url: card.querySelector('.img-link-url')?.value || '',
                    link_win: card.querySelector('.img-link-win')?.value || '0'
                });
            });
            return items;
        }

        /**
         * 동영상 타입 변경 핸들러 초기화
         */
        initVideoTypeChange() {
            const typeSelect = document.getElementById('modal_video_type');
            const urlInput = document.getElementById('modal_video_url');
            const inputLabel = document.getElementById('video_input_label');
            const inputHint = document.getElementById('video_input_hint');
            const previewArea = document.getElementById('video_preview_area');
            const previewFrame = document.getElementById('modal_video_preview');

            if (!typeSelect || !urlInput) return;

            // 이미 바인딩된 경우 스킵
            if (typeSelect._typeBound) return;
            typeSelect._typeBound = true;

            const updateVideoUI = () => {
                const type = typeSelect.value;
                switch (type) {
                    case 'youtube':
                        inputLabel.textContent = 'YouTube URL 또는 영상 ID';
                        urlInput.placeholder = 'https://www.youtube.com/watch?v=... 또는 영상 ID';
                        inputHint.textContent = 'YouTube 링크를 붙여넣거나 영상 ID만 입력하세요.';
                        break;
                    case 'vimeo':
                        inputLabel.textContent = 'Vimeo URL 또는 영상 ID';
                        urlInput.placeholder = 'https://vimeo.com/123456789 또는 영상 ID';
                        inputHint.textContent = 'Vimeo 링크를 붙여넣거나 영상 ID만 입력하세요.';
                        break;
                    case 'url':
                        inputLabel.textContent = '동영상 URL';
                        urlInput.placeholder = 'https://example.com/video.mp4';
                        inputHint.textContent = 'MP4, WebM 등 동영상 파일 URL을 입력하세요.';
                        break;
                }
                this.updateVideoPreview();
            };

            typeSelect.addEventListener('change', updateVideoUI);
            urlInput.addEventListener('change', () => this.updateVideoPreview());
            urlInput.addEventListener('blur', () => this.updateVideoPreview());

            // 초기 UI 설정
            updateVideoUI();
        }

        /**
         * 동영상 미리보기 업데이트
         */
        updateVideoPreview() {
            const typeSelect = document.getElementById('modal_video_type');
            const urlInput = document.getElementById('modal_video_url');
            const previewArea = document.getElementById('video_preview_area');
            const previewFrame = document.getElementById('modal_video_preview');

            if (!typeSelect || !urlInput || !previewFrame) return;

            const type = typeSelect.value;
            const url = urlInput.value.trim();

            if (!url) {
                previewArea.style.display = 'none';
                return;
            }

            let embedUrl = '';

            if (type === 'youtube') {
                const videoId = this.extractYouTubeId(url);
                if (videoId) {
                    embedUrl = `https://www.youtube.com/embed/${videoId}`;
                }
            } else if (type === 'vimeo') {
                const videoId = this.extractVimeoId(url);
                if (videoId) {
                    embedUrl = `https://player.vimeo.com/video/${videoId}`;
                }
            } else if (type === 'url') {
                // 직접 URL은 iframe으로 미리보기 불가, 표시하지 않음
                previewArea.style.display = 'none';
                return;
            }

            if (embedUrl) {
                previewFrame.src = embedUrl;
                previewArea.style.display = 'block';
            } else {
                previewArea.style.display = 'none';
            }
        }

        /**
         * YouTube 영상 ID 추출
         */
        extractYouTubeId(url) {
            if (!url) return null;

            // 이미 ID만 있는 경우 (11자리 영숫자)
            if (/^[a-zA-Z0-9_-]{11}$/.test(url)) {
                return url;
            }

            // URL에서 추출
            const patterns = [
                /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/,
                /youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/
            ];

            for (const pattern of patterns) {
                const match = url.match(pattern);
                if (match) return match[1];
            }

            return null;
        }

        /**
         * Vimeo 영상 ID 추출
         */
        extractVimeoId(url) {
            if (!url) return null;

            // 이미 ID만 있는 경우 (숫자)
            if (/^\d+$/.test(url)) {
                return url;
            }

            // URL에서 추출
            const match = url.match(/vimeo\.com\/(\d+)/);
            return match ? match[1] : null;
        }

        /**
         * 칸 설정 모달 열기
         */
        openColumnModal(index) {
            document.getElementById('modalColumnIndex').value = index;
            document.getElementById('modalColumnNumber').textContent = index + 1;

            const data = this.getColumnData(index);

            // JSON 설정 파싱
            let bgConfig = {}, borderConfig = {}, titleConfig = {}, contentConfig = {};
            try {
                bgConfig = data.background_config ? JSON.parse(data.background_config) : {};
                borderConfig = data.border_config ? JSON.parse(data.border_config) : {};
                titleConfig = data.title_config ? JSON.parse(data.title_config) : {};
                contentConfig = data.content_config ? JSON.parse(data.content_config) : {};
            } catch (e) {
                console.warn('Config parsing error:', e);
            }

            // Plugin/Package getConfig() 훅용 — 편집 시 기존값 복원
            this._currentContentConfig = contentConfig;

            let contentItems = [];
            try {
                contentItems = data.content_items ? JSON.parse(data.content_items) : [];
            } catch (e) {
                console.warn('Content items parsing error:', e);
            }

            // 스타일 탭 - 칸 너비
            const widthValue = data.width || '';
            if (widthValue) {
                const widthMatch = widthValue.match(/^([\d.]+)(px|%)$/);
                if (widthMatch) {
                    document.getElementById('modal_column_width').value = widthMatch[1];
                    document.getElementById('modal_column_width_unit').value = widthMatch[2];
                } else {
                    document.getElementById('modal_column_width').value = widthValue;
                    document.getElementById('modal_column_width_unit').value = '%';
                }
            } else {
                document.getElementById('modal_column_width').value = '';
                document.getElementById('modal_column_width_unit').value = '%';
            }

            // 스타일 탭 - 내부여백
            document.getElementById('modal_pc_padding').value = data.pc_padding || '';
            document.getElementById('modal_mobile_padding').value = data.mobile_padding || '';

            // 스타일 탭 - 배경
            const bgColor = bgConfig.color || '';
            document.getElementById('modal_bg_color').value = bgColor;
            document.getElementById('modal_bg_color_picker').value = bgColor || '#ffffff';

            // 배경 이미지
            const bgImage = bgConfig.image || '';
            document.getElementById('modal_bg_image').value = bgImage;
            this._modalBgImageOriginal = bgImage;
            this._modalBgPendingFile = null;
            this._modalBgImageDeleted = false;

            // 파일 input 초기화
            const bgFileInput = document.getElementById('modal_bg_image_file');
            if (bgFileInput) bgFileInput.value = '';

            // 기존 이미지 미리보기
            const bgPreviewDiv = document.getElementById('modal_bg_image_preview');
            const bgPreviewImg = bgPreviewDiv?.querySelector('img');
            const bgDelCheckbox = document.getElementById('modal_bg_image_del');
            if (bgImage) {
                if (bgPreviewImg) bgPreviewImg.src = bgImage;
                if (bgPreviewDiv) bgPreviewDiv.style.display = '';
                if (bgDelCheckbox) bgDelCheckbox.checked = false;
            } else {
                if (bgPreviewDiv) bgPreviewDiv.style.display = 'none';
                if (bgDelCheckbox) bgDelCheckbox.checked = false;
            }

            document.getElementById('modal_bg_size').value = bgConfig.size || 'cover';
            document.getElementById('modal_bg_position').value = bgConfig.position || 'center center';
            document.getElementById('modal_bg_repeat').value = bgConfig.repeat || 'no-repeat';
            document.getElementById('modal_bg_attachment').value = bgConfig.attachment || 'scroll';
            this.toggleModalBgImageOptions();

            // 스타일 탭 - 테두리
            document.getElementById('modal_border_width').value = borderConfig.width || '';
            document.getElementById('modal_border_color').value = borderConfig.color || '';
            document.getElementById('modal_border_radius').value = borderConfig.radius || '';

            // 제목 탭
            const titleShow = titleConfig.show || false;
            document.getElementById('modal_title_show').checked = titleShow;
            this.toggleTitleDetailWrapper(titleShow);
            document.getElementById('modal_title_text').value = titleConfig.text || '';
            document.getElementById('modal_title_color').value = titleConfig.color || '';
            document.getElementById('modal_title_color_picker').value = titleConfig.color || '#000000';
            document.getElementById('modal_title_position').value = titleConfig.position || 'left';
            document.getElementById('modal_title_size_pc').value = titleConfig.size_pc || 16;
            document.getElementById('modal_title_size_mo').value = titleConfig.size_mo || 14;

            // 제목 이미지
            this._titlePcPendingFile = null;
            this._titleMoPendingFile = null;
            this.loadTitleImagePreview('pc', titleConfig.pc_image || '');
            this.loadTitleImagePreview('mo', titleConfig.mo_image || '');

            document.getElementById('modal_copytext').value = titleConfig.copytext || '';
            document.getElementById('modal_copytext_color').value = titleConfig.copytext_color || '';
            document.getElementById('modal_copytext_color_picker').value = titleConfig.copytext_color || '#666666';
            document.getElementById('modal_copytext_position').value = titleConfig.copytext_position || '';
            document.getElementById('modal_copytext_size_pc').value = titleConfig.copytext_size_pc || 14;
            document.getElementById('modal_copytext_size_mo').value = titleConfig.copytext_size_mo || 12;
            document.getElementById('modal_more_link').checked = titleConfig.more_link || false;
            document.getElementById('modal_more_url').value = titleConfig.more_url || '';

            // 콘텐츠 탭 - content_config에서 값 읽기
            const contentType = data.content_type || '';
            document.getElementById('modal_content_type').value = contentType;
            document.getElementById('modal_content_count_pc').value = contentConfig.pc_count || 5;
            document.getElementById('modal_content_count_mo').value = contentConfig.mo_count || 4;
            document.getElementById('modal_aos_effect').value = contentConfig.aos || '';
            document.getElementById('modal_aos_duration').value = contentConfig.aos_duration || 600;

            // 스킨 목록 업데이트 후 저장된 값 선택
            this.updateSkinList(contentType);
            const savedSkin = data.content_skin || '';
            if (savedSkin) {
                document.getElementById('modal_content_skin').value = savedSkin;
            }

            // 출력 스타일 설정 (content_config에서 읽기)
            document.getElementById('modal_pc_style').value = contentConfig.pc_style || 'list';
            document.getElementById('modal_mo_style').value = contentConfig.mo_style || 'list';
            document.getElementById('modal_pc_cols').value = contentConfig.pc_cols || '4';
            document.getElementById('modal_mo_cols').value = contentConfig.mo_cols || '2';

            // 슬라이드 옵션 (autoplay / loop)
            const pcAutoplay = contentConfig.pc_autoplay || 0;
            const moAutoplay = contentConfig.mo_autoplay || 0;
            document.getElementById('modal_pc_autoplay_check').checked = pcAutoplay > 0;
            document.getElementById('modal_pc_autoplay_delay').value = pcAutoplay || 5000;
            document.getElementById('modal_pc_autoplay_delay').disabled = pcAutoplay <= 0;
            document.getElementById('modal_mo_autoplay_check').checked = moAutoplay > 0;
            document.getElementById('modal_mo_autoplay_delay').value = moAutoplay || 3000;
            document.getElementById('modal_mo_autoplay_delay').disabled = moAutoplay <= 0;
            document.getElementById('modal_pc_loop').checked = contentConfig.pc_loop || false;
            document.getElementById('modal_mo_loop').checked = contentConfig.mo_loop || false;
            document.getElementById('modal_pc_slide_cover').checked = contentConfig.pc_slide_cover || false;
            document.getElementById('modal_mo_slide_cover').checked = contentConfig.mo_slide_cover || false;
            this.toggleSlideOptions();

            // HTML/include 에디터 표시/숨김 토글
            this.toggleHtmlEditor(contentType);

            // HTML 콘텐츠 로드 (에디터가 초기화된 후)
            setTimeout(() => {
                const editorInstance = MubloEditor.get('modal_html_content');
                if (editorInstance) {
                    editorInstance.setHTML(contentConfig.html || '');
                } else {
                    const htmlEditor = document.getElementById('modal_html_content');
                    if (htmlEditor) htmlEditor.value = contentConfig.html || '';
                }
            }, 100);

            // CSS / JS 로드
            const cssField = document.getElementById('modal_html_css');
            const jsField = document.getElementById('modal_html_js');
            if (cssField) cssField.value = contentConfig.css || '';
            if (jsField) jsField.value = contentConfig.js || '';
            // 값이 있으면 접이식 패널 자동 펼침
            if (contentConfig.css) {
                const cssCollapse = document.getElementById('html_css_collapse');
                if (cssCollapse && !cssCollapse.classList.contains('show')) {
                    new bootstrap.Collapse(cssCollapse, { toggle: true });
                }
            }
            if (contentConfig.js) {
                const jsCollapse = document.getElementById('html_js_collapse');
                if (jsCollapse && !jsCollapse.classList.contains('show')) {
                    new bootstrap.Collapse(jsCollapse, { toggle: true });
                }
            }

            // include 경로 로드 (파일명만 추출)
            const includePathInput = document.getElementById('modal_include_path');
            if (includePathInput) {
                let incPath = contentConfig.include_path || contentConfig.file || '';
                if (incPath.indexOf('/') !== -1) incPath = incPath.split('/').pop();
                if (incPath.indexOf('\\') !== -1) incPath = incPath.split('\\').pop();
                includePathInput.value = incPath;
            }

            // outlogin 설정 로드
            document.getElementById('modal_outlogin_show_pc').checked = contentConfig.show_pc !== false;
            document.getElementById('modal_outlogin_show_mobile').checked = contentConfig.show_mobile !== false;

            // image 타입: content_items에서 이미지 배열 읽기
            if (contentType === 'image') {
                // content_items가 이미지 배열 (리팩토링 후)
                // 하위 호환: content_config.images도 체크
                const images = Array.isArray(contentItems) && contentItems.length > 0 && contentItems[0]?.pc_image !== undefined
                    ? contentItems
                    : (contentConfig.images || []);
                this.initImageItems(images);
            }

            // movie 설정 로드
            const videoTypeSelect = document.getElementById('modal_video_type');
            const videoUrlInput = document.getElementById('modal_video_url');
            const videoAutoplay = document.getElementById('modal_video_autoplay');
            const videoMuted = document.getElementById('modal_video_muted');
            if (videoTypeSelect) videoTypeSelect.value = contentConfig.video_type || 'youtube';
            if (videoUrlInput) videoUrlInput.value = contentConfig.video_url || contentConfig.video_id || '';
            if (videoAutoplay) videoAutoplay.checked = contentConfig.autoplay || false;
            if (videoMuted) videoMuted.checked = contentConfig.muted !== false; // 기본 true

            // 아이템 목록 로드 (board/boardgroup/menu 등 - ID 배열)
            // image 타입은 DualListbox 사용 안함
            if (contentType !== 'image') {
                this.loadContentItems(contentType, contentItems);
            }

            // 모달 열기
            const modal = new bootstrap.Modal(this.elements.columnModal);
            modal.show();
        }

        /**
         * 칸 설정 저장
         */
        saveColumnSettings() {
            const index = parseInt(document.getElementById('modalColumnIndex').value);
            const container = this.elements.columnsData;

            const set = (field, value) => {
                let input = container.querySelector(`input[name="columns[${index}][${field}]"]`);
                // hidden input이 없으면 생성
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = `columns[${index}][${field}]`;
                    container.appendChild(input);
                }
                input.value = value;
            };

            // 칸 너비
            const widthNum = document.getElementById('modal_column_width').value.trim();
            const widthUnit = document.getElementById('modal_column_width_unit').value;
            set('width', widthNum ? widthNum + widthUnit : '');

            // 내부여백
            set('pc_padding', document.getElementById('modal_pc_padding').value);
            set('mobile_padding', document.getElementById('modal_mobile_padding').value);

            // 배경
            const bgConfig = {
                color: document.getElementById('modal_bg_color').value
            };

            // 배경 이미지 처리
            const existingBgImage = document.getElementById('modal_bg_image').value;
            const hasPendingFile = !!this._modalBgPendingFile;
            const isDeleted = this._modalBgImageDeleted;

            if (isDeleted) {
                bgConfig.image = '';
                bgConfig.image_del = true;
            } else if (hasPendingFile) {
                bgConfig.image = existingBgImage || '__pending__';
                bgConfig.image_has_file = true;
            } else if (existingBgImage) {
                bgConfig.image = existingBgImage;
            }

            // 이미지가 있으면 (기존 유지 또는 새 파일) 옵션 저장
            if ((bgConfig.image && bgConfig.image !== '') || hasPendingFile) {
                bgConfig.size = document.getElementById('modal_bg_size').value;
                bgConfig.position = document.getElementById('modal_bg_position').value;
                bgConfig.repeat = document.getElementById('modal_bg_repeat').value;
                bgConfig.attachment = document.getElementById('modal_bg_attachment').value;
            }
            set('background_config', JSON.stringify(bgConfig));

            // 배경 이미지 파일을 메인 폼에 첨부
            this.attachBgFileToForm(index);

            // 테두리
            const borderConfig = {
                width: document.getElementById('modal_border_width').value,
                color: document.getElementById('modal_border_color').value,
                radius: document.getElementById('modal_border_radius').value
            };
            set('border_config', JSON.stringify(borderConfig));

            // 제목
            const titleConfig = {
                show: document.getElementById('modal_title_show').checked,
                text: document.getElementById('modal_title_text').value,
                color: document.getElementById('modal_title_color').value,
                position: document.getElementById('modal_title_position').value,
                size_pc: parseInt(document.getElementById('modal_title_size_pc').value) || 16,
                size_mo: parseInt(document.getElementById('modal_title_size_mo').value) || 14,
                copytext: document.getElementById('modal_copytext').value,
                copytext_color: document.getElementById('modal_copytext_color').value,
                copytext_position: document.getElementById('modal_copytext_position').value,
                copytext_size_pc: parseInt(document.getElementById('modal_copytext_size_pc').value) || 14,
                copytext_size_mo: parseInt(document.getElementById('modal_copytext_size_mo').value) || 12,
                more_link: document.getElementById('modal_more_link').checked,
                more_url: document.getElementById('modal_more_url').value
            };

            // 제목 이미지
            const pcImgDel = document.getElementById('modal_title_pc_image_del')?.checked;
            const moImgDel = document.getElementById('modal_title_mo_image_del')?.checked;
            const existingPcImage = document.getElementById('modal_title_pc_image').value;
            const existingMoImage = document.getElementById('modal_title_mo_image').value;

            if (pcImgDel) {
                titleConfig.pc_image = '';
                titleConfig.pc_image_del = true;
            } else if (this._titlePcPendingFile) {
                titleConfig.pc_image = existingPcImage || '__pending__';
                titleConfig.pc_image_has_file = true;
            } else if (existingPcImage) {
                titleConfig.pc_image = existingPcImage;
            }

            if (moImgDel) {
                titleConfig.mo_image = '';
                titleConfig.mo_image_del = true;
            } else if (this._titleMoPendingFile) {
                titleConfig.mo_image = existingMoImage || '__pending__';
                titleConfig.mo_image_has_file = true;
            } else if (existingMoImage) {
                titleConfig.mo_image = existingMoImage;
            }

            set('title_config', JSON.stringify(titleConfig));

            // 제목 이미지 파일을 메인 폼에 첨부
            this.attachTitleImageFilesToForm(index);

            // 콘텐츠
            const contentType = document.getElementById('modal_content_type').value;
            const selectedOption = document.getElementById('modal_content_type').selectedOptions[0];
            const contentKind = selectedOption ? (selectedOption.dataset.kind || 'CORE') : 'CORE';

            set('content_type', contentType);
            set('content_kind', contentKind);
            set('content_skin', document.getElementById('modal_content_skin').value);

            // content_items 결정 (타입에 따라 다름)
            let items = [];
            if (contentType === 'image') {
                // image 타입: 이미지 배열이 content_items
                items = this.getImageItems();
                this.attachPendingFilesToForm(index);
            } else if (this.pluginSelector && typeof this.pluginSelector.getSelectedItems === 'function') {
                // Plugin Custom UI 모드
                items = this.pluginSelector.getSelectedItems();
            } else if (this.dualListbox) {
                // DualListbox 모드: board/boardgroup/menu 등
                items = this.dualListbox.getSelected();
            }
            set('content_items', JSON.stringify(items));

            // content_config 통합 (공통 설정 + 타입별 설정)
            let contentConfig = {
                // 공통 설정
                pc_count: parseInt(document.getElementById('modal_content_count_pc').value) || 5,
                mo_count: parseInt(document.getElementById('modal_content_count_mo').value) || 4,
                aos: document.getElementById('modal_aos_effect').value || null,
                aos_duration: parseInt(document.getElementById('modal_aos_duration').value) || 600,
                // 출력 스타일
                pc_style: document.getElementById('modal_pc_style').value,
                mo_style: document.getElementById('modal_mo_style').value,
                pc_cols: document.getElementById('modal_pc_cols').value,
                mo_cols: document.getElementById('modal_mo_cols').value,
                // 슬라이드 옵션
                pc_autoplay: document.getElementById('modal_pc_autoplay_check').checked
                    ? (parseInt(document.getElementById('modal_pc_autoplay_delay').value) || 5000) : 0,
                mo_autoplay: document.getElementById('modal_mo_autoplay_check').checked
                    ? (parseInt(document.getElementById('modal_mo_autoplay_delay').value) || 3000) : 0,
                pc_loop: document.getElementById('modal_pc_loop').checked,
                mo_loop: document.getElementById('modal_mo_loop').checked,
                pc_slide_cover: document.getElementById('modal_pc_slide_cover').checked,
                mo_slide_cover: document.getElementById('modal_mo_slide_cover').checked
            };

            // 타입별 설정 추가
            if (contentType === 'html') {
                const editorInstance = MubloEditor.get('modal_html_content');
                if (editorInstance) {
                    contentConfig.html = editorInstance.getHTML();
                } else {
                    const htmlEditor = document.getElementById('modal_html_content');
                    if (htmlEditor) contentConfig.html = htmlEditor.value;
                }
                // CSS / JS 수집
                const cssField = document.getElementById('modal_html_css');
                const jsField = document.getElementById('modal_html_js');
                if (cssField && cssField.value.trim()) contentConfig.css = cssField.value.trim();
                if (jsField && jsField.value.trim()) contentConfig.js = jsField.value.trim();
            } else if (contentType === 'include') {
                contentConfig.include_path = document.getElementById('modal_include_path')?.value || '';
            } else if (contentType === 'outlogin') {
                contentConfig.show_pc = document.getElementById('modal_outlogin_show_pc')?.checked !== false;
                contentConfig.show_mobile = document.getElementById('modal_outlogin_show_mobile')?.checked !== false;
            } else if (contentType === 'movie') {
                const videoType = document.getElementById('modal_video_type')?.value || 'youtube';
                const videoUrl = document.getElementById('modal_video_url')?.value || '';

                // 영상 ID 추출
                let videoId = '';
                if (videoType === 'youtube') {
                    videoId = this.extractYouTubeId(videoUrl) || videoUrl;
                } else if (videoType === 'vimeo') {
                    videoId = this.extractVimeoId(videoUrl) || videoUrl;
                } else {
                    videoId = videoUrl;
                }

                contentConfig.video_type = videoType;
                contentConfig.video_url = videoUrl;
                contentConfig.video_id = videoId;
                contentConfig.autoplay = document.getElementById('modal_video_autoplay')?.checked || false;
                contentConfig.muted = document.getElementById('modal_video_muted')?.checked !== false;
            }

            // Plugin/Package 추가 설정 병합
            if (this.pluginSelector && typeof this.pluginSelector.getConfig === 'function') {
                Object.assign(contentConfig, this.pluginSelector.getConfig());
            }

            set('content_config', JSON.stringify(contentConfig));

            // 프리뷰 업데이트
            this.updateColumnPreview(index);

            // 모달 닫기
            bootstrap.Modal.getInstance(this.elements.columnModal).hide();
        }

        /**
         * 칸 프리뷰 업데이트
         */
        updateColumnPreview(index) {
            const preview = document.querySelector(`.column-preview-item[data-index="${index}"]`);
            if (!preview) return;

            const data = this.getColumnData(index);
            const contentLabel = this.getContentTypeLabel(data.content_type);

            // 콘텐츠 타입 배지
            const typeBadge = preview.querySelector('.column-type-badge');
            if (typeBadge) {
                typeBadge.className = 'column-type-badge badge ' + (data.content_type ? 'bg-primary' : 'bg-secondary');
                typeBadge.textContent = contentLabel || '미설정';
            }

            // 너비 배지
            const colWidth = data.width || '';
            let widthBadge = preview.querySelector('.column-width-badge');
            const title = preview.querySelector('.card-title');
            if (colWidth) {
                if (!widthBadge && title) {
                    widthBadge = document.createElement('span');
                    widthBadge.className = 'column-width-badge badge bg-info ms-1';
                    title.appendChild(widthBadge);
                }
                if (widthBadge) widthBadge.textContent = colWidth;
                const count = parseInt(this.elements.columnCount?.value) || 1;
                const margin = parseInt(document.querySelector('input[name="formData[column_margin]"]')?.value) || 0;
                const gapTotal = margin * (count - 1);
                preview.style.cssText = `flex: 0 0 calc(${colWidth} - ${gapTotal}px / ${count}); min-width: 150px;`;
            } else {
                if (widthBadge) widthBadge.remove();
                preview.style.cssText = 'flex: 1; min-width: 200px;';
            }

            // 버튼 텍스트
            const btn = preview.querySelector('button');
            if (btn) {
                btn.textContent = data.content_type ? '수정' : '설정';
            }
        }

        /**
         * 미리보기 표시
         */
        showPreview() {
            const previewModal = new bootstrap.Modal(this.elements.previewModal);
            const previewLoading = document.getElementById('previewLoading');
            const previewError = document.getElementById('previewError');
            const previewFrame = document.getElementById('previewFrame');

            // 초기화
            previewLoading.style.display = '';
            previewError.style.display = 'none';
            previewFrame.style.display = 'none';

            previewModal.show();

            // 폼 데이터 수집
            const formData = this.collectFormData();
            const columnsData = this.collectColumnsData();

            // AJAX 요청
            MubloRequest.requestJson('/admin/block-row/preview', {
                formData: formData,
                columns: columnsData
            })
            .then(response => {
                if (response.data && response.data.html) {
                    this.renderPreviewIframe(response.data.html, response.data.skinCss || []);
                } else {
                    previewLoading.style.display = 'none';
                    previewError.style.display = '';
                    previewError.innerHTML = `
                        <div class="alert alert-warning mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            ${response.message || '미리보기를 생성할 수 없습니다.'}
                        </div>
                    `;
                }
            })
            .catch(error => {
                previewLoading.style.display = 'none';
                previewError.style.display = '';
                previewError.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        <i class="bi bi-x-circle me-2"></i>
                        미리보기 생성 중 오류가 발생했습니다.
                    </div>
                `;
            });
        }

        /**
         * iframe srcdoc으로 미리보기 렌더링
         */
        renderPreviewIframe(html, skinCss) {
            const previewLoading = document.getElementById('previewLoading');
            const previewFrame = document.getElementById('previewFrame');

            // 스킨별 CSS 태그 생성
            let skinCssTags = '';
            if (skinCss && skinCss.length > 0) {
                skinCssTags = skinCss.map(path =>
                    '<link rel="stylesheet" href="' + path + '">'
                ).join('\n');
            }

            const srcdoc = `<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2/dist/aos.css">
<link rel="stylesheet" href="/assets/css/front-common.css">
<link rel="stylesheet" href="/assets/css/block.css">
${skinCssTags}
<style>
body { margin: 0; padding: 16px 0; background: #f8f9fa; font-family: 'Noto Sans KR', sans-serif; overflow-x: hidden; }
*, *::before, *::after { box-sizing: border-box; }
ul, ol { list-style: none; margin: 0; padding: 0; }
.block-container--wide, .block-container--contained { max-width: 100%; }
</style>
</head>
<body>
${html}
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"><\/script>
<script src="/assets/js/MubloItemLayout.js"><\/script>
<script src="https://cdn.jsdelivr.net/npm/aos@2/dist/aos.js"><\/script>
<script>
AOS.init();
document.addEventListener('DOMContentLoaded', function() {
    if (typeof MubloItemLayout !== 'undefined') {
        MubloItemLayout.initAll();
    }
});
<\/script>
</body>
</html>`;

            previewFrame.srcdoc = srcdoc;

            previewFrame.onload = function() {
                previewLoading.style.display = 'none';
                previewFrame.style.display = '';
            };
        }

        /**
         * 폼 데이터 수집
         */
        collectFormData() {
            const btn = document.querySelector('.mublo-submit');
            const form = btn ? btn.closest('form') : document.querySelector('form');
            if (!form) return {};
            const data = {};

            form.querySelectorAll('input[name^="formData["], select[name^="formData["], textarea[name^="formData["]').forEach(el => {
                const match = el.name.match(/formData\[([^\]]+)\]/);
                if (match) {
                    if (el.type === 'checkbox') {
                        data[match[1]] = el.checked ? 1 : 0;
                    } else {
                        data[match[1]] = el.value;
                    }
                }
            });

            return data;
        }

        /**
         * 칸 데이터 수집
         */
        collectColumnsData() {
            const columns = [];
            const columnCount = parseInt(this.elements.columnCount.value) || 1;

            for (let i = 0; i < columnCount; i++) {
                columns.push(this.getColumnData(i));
            }

            return columns;
        }
    }

    // =========================================================================
    // DualListbox 컴포넌트
    // =========================================================================
    // TODO: 다른 곳에서도 필요해지면 별도 파일(dual-listbox.js)로 분리 가능
    // 현재는 BlockRow 폼 전용으로 사용
    // =========================================================================

    /**
     * DualListbox 클래스
     * 왼쪽(사용 가능)에서 오른쪽(선택됨)으로 드래그하여 아이템을 선택하는 UI 컴포넌트
     *
     * @example
     * const listbox = new DualListbox('#container', {
     *     available: [{ id: 'free', label: '자유게시판' }],
     *     selected: ['free'],
     *     onChanged: (selectedIds) => console.log(selectedIds)
     * });
     */
    class DualListbox {
        constructor(container, options = {}) {
            this.container = typeof container === 'string' ? document.querySelector(container) : container;
            this.options = {
                available: [],
                selected: [],
                leftTitle: '사용 가능',
                rightTitle: '선택됨',
                maxItems: 0,
                onChanged: null,
                ...options
            };

            // 아이템 데이터 축적 저장소 (페이지/필터 전환 시에도 선택 아이템 유지)
            this.itemMap = {};
            this.options.available.forEach(item => {
                this.itemMap[item.id] = item;
            });

            this.selectedIds = new Set(this.options.selected);
            this.init();
        }

        init() {
            this.render();
            this.bindEvents();
            this.bindDragEvents();
        }

        render() {
            this.container.innerHTML = `
                <div class="dual-listbox">
                    <div class="dual-listbox-panel dual-listbox-available">
                        <div class="dual-listbox-header">${this.options.leftTitle}</div>
                        <div class="dual-listbox-list" data-side="available"></div>
                    </div>
                    <div class="dual-listbox-actions">
                        <button type="button" class="btn btn-sm btn-outline-primary" data-action="add-all" title="모두 추가">
                            <i class="bi bi-chevron-double-right"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-action="add" title="선택 추가">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-action="remove" title="선택 제거">
                            <i class="bi bi-chevron-left"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-action="remove-all" title="모두 제거">
                            <i class="bi bi-chevron-double-left"></i>
                        </button>
                    </div>
                    <div class="dual-listbox-panel dual-listbox-selected">
                        <div class="dual-listbox-header dual-listbox-selected-header">${this.options.rightTitle}</div>
                        <div class="dual-listbox-list" data-side="selected"></div>
                    </div>
                </div>
            `;

            this.availableList = this.container.querySelector('[data-side="available"]');
            this.selectedList = this.container.querySelector('[data-side="selected"]');
            this.selectedHeader = this.container.querySelector('.dual-listbox-selected-header');

            this.updateLists();
        }

        updateLists() {
            // 사용 가능 목록 (선택되지 않은 것들)
            const availableItems = this.options.available.filter(item => !this.selectedIds.has(item.id));
            this.availableList.innerHTML = availableItems.map(item => `
                <div class="dual-listbox-item" data-id="${item.id}" draggable="true">
                    ${item.label}
                </div>
            `).join('');

            // 선택된 목록: itemMap에서 조회 (페이지/필터 변경 후에도 유지)
            const selectedItems = Array.from(this.selectedIds)
                .map(id => this.itemMap[id])
                .filter(Boolean);

            this.selectedList.innerHTML = selectedItems.map(item => `
                <div class="dual-listbox-item" data-id="${item.id}" draggable="true">
                    ${item.label}
                </div>
            `).join('');

            this.updateSelectedHeader();
        }

        updateSelectedHeader() {
            if (!this.selectedHeader) return;
            const count = this.selectedIds.size;
            const max = this.options.maxItems;
            if (max > 0) {
                this.selectedHeader.textContent = this.options.rightTitle + ' (' + count + '/' + max + ')';
            } else {
                this.selectedHeader.textContent = this.options.rightTitle + (count > 0 ? ' (' + count + ')' : '');
            }
        }

        /**
         * 추가 가능 여부 확인
         */
        canAdd(count) {
            const max = this.options.maxItems;
            if (!max || max <= 0) return true;
            return (this.selectedIds.size + count) <= max;
        }

        /**
         * 추가 가능 잔여 수
         */
        remainingSlots() {
            const max = this.options.maxItems;
            if (!max || max <= 0) return Infinity;
            return Math.max(0, max - this.selectedIds.size);
        }

        bindEvents() {
            // 아이템 클릭 (선택)
            this.container.addEventListener('click', (e) => {
                const item = e.target.closest('.dual-listbox-item');
                if (item) {
                    item.classList.toggle('selected');
                }
            });

            // 아이템 더블클릭 (이동)
            this.container.addEventListener('dblclick', (e) => {
                const item = e.target.closest('.dual-listbox-item');
                if (item) {
                    const id = item.dataset.id;
                    const side = item.closest('.dual-listbox-list').dataset.side;

                    if (side === 'available') {
                        if (!this.canAdd(1)) {
                            alert('최대 ' + this.options.maxItems + '개까지 선택할 수 있습니다.');
                            return;
                        }
                        this.selectedIds.add(id);
                    } else {
                        this.selectedIds.delete(id);
                    }

                    this.updateLists();
                    this.triggerChange();
                }
            });

            // 버튼 클릭
            this.container.querySelectorAll('[data-action]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const action = e.currentTarget.dataset.action;
                    this.handleAction(action);
                });
            });
        }

        handleAction(action) {
            switch (action) {
                case 'add': {
                    const items = Array.from(this.availableList.querySelectorAll('.dual-listbox-item.selected'));
                    const remaining = this.remainingSlots();
                    if (remaining === 0) {
                        alert('최대 ' + this.options.maxItems + '개까지 선택할 수 있습니다.');
                        return;
                    }
                    const toAdd = items.slice(0, remaining);
                    toAdd.forEach(item => {
                        this.selectedIds.add(item.dataset.id);
                    });
                    if (items.length > toAdd.length) {
                        alert('최대 ' + this.options.maxItems + '개까지 선택할 수 있습니다. ' + toAdd.length + '개만 추가되었습니다.');
                    }
                    break;
                }
                case 'add-all': {
                    const remaining = this.remainingSlots();
                    if (remaining === 0) {
                        alert('최대 ' + this.options.maxItems + '개까지 선택할 수 있습니다.');
                        return;
                    }
                    const availableItems = this.options.available.filter(item => !this.selectedIds.has(item.id));
                    const toAdd = availableItems.slice(0, remaining);
                    toAdd.forEach(item => this.selectedIds.add(item.id));
                    if (availableItems.length > toAdd.length) {
                        alert('최대 ' + this.options.maxItems + '개까지 선택할 수 있습니다. ' + toAdd.length + '개만 추가되었습니다.');
                    }
                    break;
                }
                case 'remove':
                    this.selectedList.querySelectorAll('.dual-listbox-item.selected').forEach(item => {
                        this.selectedIds.delete(item.dataset.id);
                    });
                    break;
                case 'remove-all':
                    this.selectedIds.clear();
                    break;
            }

            this.updateLists();
            this.triggerChange();
        }

        triggerChange() {
            if (typeof this.options.onChanged === 'function') {
                this.options.onChanged(Array.from(this.selectedIds));
            }
        }

        /**
         * 드래그 앤 드롭 이벤트 바인딩
         */
        bindDragEvents() {
            const self = this;

            // 아이템 dragstart (이벤트 위임)
            this.container.addEventListener('dragstart', (e) => {
                const item = e.target.closest('.dual-listbox-item');
                if (!item) return;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', item.dataset.id);
                item.classList.add('dragging');
            });

            this.container.addEventListener('dragend', (e) => {
                const item = e.target.closest('.dual-listbox-item');
                if (item) item.classList.remove('dragging');
                // drop-target 클래스 정리
                [self.availableList, self.selectedList].forEach(list => {
                    list.classList.remove('drop-target');
                });
            });

            // 양쪽 리스트에 drop zone 설정
            [this.availableList, this.selectedList].forEach(list => {
                list.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    list.classList.add('drop-target');
                });

                list.addEventListener('dragleave', (e) => {
                    // 자식 요소로 이동 시 무시
                    if (list.contains(e.relatedTarget)) return;
                    list.classList.remove('drop-target');
                });

                list.addEventListener('drop', (e) => {
                    e.preventDefault();
                    list.classList.remove('drop-target');
                    list.querySelectorAll('.dual-listbox-item').forEach(el => el.classList.remove('drag-over-above', 'drag-over-below'));

                    const id = e.dataTransfer.getData('text/plain');
                    if (!id) return;

                    const targetSide = list.dataset.side;

                    if (targetSide === 'selected' && self.selectedIds.has(id)) {
                        // 선택 목록 내 순서 변경
                        const dropTarget = e.target.closest('.dual-listbox-item');
                        if (dropTarget && dropTarget.dataset.id !== id) {
                            const ids = Array.from(self.selectedIds);
                            const fromIdx = ids.indexOf(id);
                            const toIdx = ids.indexOf(dropTarget.dataset.id);
                            if (fromIdx !== -1 && toIdx !== -1) {
                                ids.splice(fromIdx, 1);
                                ids.splice(toIdx, 0, id);
                                self.selectedIds = new Set(ids);
                                self.updateLists();
                                self.triggerChange();
                            }
                        }
                    } else if (targetSide === 'selected' && !self.selectedIds.has(id)) {
                        if (!self.canAdd(1)) {
                            alert('최대 ' + self.options.maxItems + '개까지 선택할 수 있습니다.');
                            return;
                        }
                        self.selectedIds.add(id);
                        self.updateLists();
                        self.triggerChange();
                    } else if (targetSide === 'available' && self.selectedIds.has(id)) {
                        self.selectedIds.delete(id);
                        self.updateLists();
                        self.triggerChange();
                    }
                });

                // 선택 목록 내 드래그 오버 시 위치 표시
                if (list.dataset.side === 'selected') {
                    list.addEventListener('dragover', (e) => {
                        const item = e.target.closest('.dual-listbox-item');
                        list.querySelectorAll('.dual-listbox-item').forEach(el => el.classList.remove('drag-over-above', 'drag-over-below'));
                        if (item) {
                            const rect = item.getBoundingClientRect();
                            const mid = rect.top + rect.height / 2;
                            item.classList.add(e.clientY < mid ? 'drag-over-above' : 'drag-over-below');
                        }
                    });
                }
            });
        }

        /**
         * 선택된 ID 목록 반환
         */
        getSelected() {
            return Array.from(this.selectedIds);
        }

        /**
         * 선택 목록 설정
         */
        setSelected(ids) {
            this.selectedIds = new Set(ids);
            this.updateLists();
        }

        /**
         * 사용 가능한 아이템 목록 업데이트
         */
        setAvailable(items) {
            items.forEach(item => {
                this.itemMap[item.id] = item;
            });
            this.options.available = items;
            this.updateLists();
        }

        /**
         * 최대 선택 수 변경
         */
        setMaxItems(max) {
            this.options.maxItems = max;
            this.updateSelectedHeader();
        }
    }

    // =========================================================================
    // 전역 인스턴스 및 함수
    // =========================================================================

    let blockRowFormInstance = null;

    /**
     * BlockRowForm 초기화
     */
    function initBlockRowForm(config) {
        blockRowFormInstance = new BlockRowForm(config);
        return blockRowFormInstance;
    }

    // 전역 함수 (기존 onclick 핸들러 호환용)
    window.openColumnModal = function(index) {
        if (blockRowFormInstance) {
            blockRowFormInstance.openColumnModal(index);
        }
    };

    window.saveColumnSettings = function() {
        if (blockRowFormInstance) {
            blockRowFormInstance.saveColumnSettings();
        }
    };

    window.showPreview = function() {
        if (blockRowFormInstance) {
            blockRowFormInstance.showPreview();
        }
    };

    // 모듈 내보내기
    window.BlockRowForm = {
        init: initBlockRowForm,
        DualListbox: DualListbox,
        getInstance: () => blockRowFormInstance
    };

    // Core UI 컴포넌트: Plugin JS에서 사용 가능
    window.MubloDualListbox = DualListbox;

})();
