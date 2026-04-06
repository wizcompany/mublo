<?php
/**
 * Admin Blockrow - Form
 *
 * 블록 행 생성/수정 폼
 *
 * @var string $pageTitle 페이지 제목
 * @var bool $isEdit 수정 모드 여부
 * @var array $rowData 정규화된 행 데이터
 * @var int $rowId 행 ID (0이면 새 행)
 * @var string $position 출력 위치
 * @var int $pageId 페이지 ID (0이면 위치 기반)
 * @var int $columnCount 칸 수
 * @var bool $isPageBased 페이지 기반 여부
 * @var string $currentPageLabel 현재 페이지 라벨
 * @var array $bgConfig 배경 설정
 * @var array $columns 칸 데이터
 * @var array $positions 위치 목록
 * @var array $pages 페이지 목록
 * @var array $contentTypes 콘텐츠 타입 목록
 * @var array $contentTypeGroups 종류별 콘텐츠 타입
 * @var array $menuOptions 메뉴 옵션 (position_menu 셀렉트용)
 */
?>
<?= editor_css() ?>
<link rel="stylesheet" href="/assets/css/admin/blockrow-form.css">
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '블록 행 설정') ?></h3>
                <p class="text-muted mb-0">
                    블록 행의 레이아웃과 칸 설정을 관리합니다.
                    <?php if ($isEdit): ?>
                        <span class="badge bg-secondary">ID: <?= $rowId ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <?php if ($isPageBased): ?>
                <a href="/admin/block-row?page_id=<?= $pageId ?>" class="btn btn-default">
                    <i class="bi bi-list me-1"></i>행 목록
                </a>
                <a href="/admin/block-page/edit?id=<?= $pageId ?>" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark me-1"></i>페이지 설정
                </a>
                <?php else: ?>
                <a href="/admin/block-row" class="btn btn-default">
                    <i class="bi bi-list me-1"></i>목록
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 메인 폼 -->
    <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="formData[row_id]" value="<?= $rowId ?>">

        <!-- 기본 정보 -->
        <div class="card mt-4">
            <div class="px-3 pt-3 pb-0 fw-semibold d-flex justify-content-between align-items-center" style="font-size:0.9rem">
                <span><i class="bi bi-info-circle me-2 text-pastel-blue"></i>기본 정보</span>
                <?php if ($isPageBased): ?>
                <span class="badge bg-primary">페이지: <?= htmlspecialchars($currentPageLabel) ?></span>
                <?php else: ?>
                <span class="badge bg-secondary">위치 기반</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">관리용 제목</label>
                        <input type="text" name="formData[admin_title]" class="form-control"
                               value="<?= htmlspecialchars($rowData['admin_title'] ?? '') ?>"
                               placeholder="관리자에서 식별할 제목">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">섹션 ID</label>
                        <?php $defaultSectionId = $rowData['section_id'] ?? ('section-' . bin2hex(random_bytes(4))); ?>
                        <input type="text" name="formData[section_id]" class="form-control"
                               value="<?= htmlspecialchars($defaultSectionId) ?>"
                               placeholder="자동 생성됨 (수정 가능)">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">사용 여부</label>
                        <select name="formData[is_active]" class="form-select">
                            <option value="1" <?= ($rowData['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>사용</option>
                            <option value="0" <?= ($rowData['is_active'] ?? 1) == 0 ? 'selected' : '' ?>>미사용</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($isPageBased): ?>
        <!-- 페이지 기반: page_id 고정 -->
        <input type="hidden" name="formData[page_id]" value="<?= $pageId ?>">
        <input type="hidden" name="formData[position]" value="">
        <?php else: ?>
        <!-- 위치 기반: position 선택 -->
        <input type="hidden" name="formData[page_id]" value="0">
        <div class="card mt-4">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-geo-alt me-2 text-pastel-blue"></i>출력 위치
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">출력 위치 <span class="text-danger">*</span></label>
                        <select name="formData[position]" id="position_select" class="form-select">
                            <?php foreach ($positions as $key => $label): ?>
                                <option value="<?= $key ?>" <?= $position === $key ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4" id="position_menu_wrapper"<?php if ($position === 'index'): ?> style="display:none"<?php endif; ?>>
                        <label class="form-label">특정 메뉴에서만 출력</label>
                        <select name="formData[position_menu]" id="position_menu_select" class="form-select">
                            <option value="">전체 (메뉴 제한 없음)</option>
                            <?php foreach ($menuOptions ?? [] as $group): ?>
                            <optgroup label="<?= htmlspecialchars($group['group']) ?>">
                                <?php foreach ($group['items'] as $menuItem): ?>
                                <option value="<?= htmlspecialchars($menuItem['value']) ?>"
                                        <?= ($rowData['position_menu'] ?? '') === $menuItem['value'] ? 'selected' : '' ?>>
                                    <?= str_repeat('&nbsp;&nbsp;', $menuItem['depth']) ?><?= htmlspecialchars($menuItem['label']) ?>
                                    (<?= htmlspecialchars($menuItem['value']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- 레이아웃 설정 -->
        <div class="card mt-4">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-layout-text-window me-2 text-pastel-green"></i>레이아웃 설정
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">넓이 타입</label>
                        <select name="formData[width_type]" id="width_type_select" class="form-select">
                            <option value="0" <?= ($rowData['width_type'] ?? 1) == 0 ? 'selected' : '' ?>>와이드 (전체)</option>
                            <option value="1" <?= ($rowData['width_type'] ?? 1) == 1 ? 'selected' : '' ?>>최대넓이</option>
                        </select>
                        <small class="text-muted" id="width_type_hint" style="display:none">이 출력 위치는 컨테이너 내부이므로 최대넓이만 가능합니다.</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">칸 수</label>
                        <select name="formData[column_count]" id="column_count" class="form-select">
                            <?php for ($i = 1; $i <= 4; $i++): ?>
                                <option value="<?= $i ?>" <?= $columnCount == $i ? 'selected' : '' ?>><?= $i ?>칸</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">칸 간격</label>
                        <div class="input-group">
                            <input type="number" name="formData[column_margin]" class="form-control"
                                   value="<?= $rowData['column_margin'] ?? 0 ?>" min="0">
                            <span class="input-group-text">px</span>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">칸 너비 단위</label>
                        <select name="formData[column_width_unit]" id="column_width_unit" class="form-select">
                            <option value="1" <?= ($rowData['column_width_unit'] ?? 1) == 1 ? 'selected' : '' ?>>%</option>
                            <option value="2" <?= ($rowData['column_width_unit'] ?? 1) == 2 ? 'selected' : '' ?>>px</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 상세 설정 -->
        <div class="card mt-4">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-sliders me-2 text-pastel-purple"></i>상세 설정
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <!-- 높이/여백 -->
                    <div class="col-12 col-lg-6">
                        <h6 class="mb-3">높이 / 여백</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">PC 높이</label>
                                <input type="text" name="formData[pc_height]" class="form-control"
                                       value="<?= htmlspecialchars($rowData['pc_height'] ?? '') ?>"
                                       placeholder="auto, 300px, 50vh">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mobile 높이</label>
                                <input type="text" name="formData[mobile_height]" class="form-control"
                                       value="<?= htmlspecialchars($rowData['mobile_height'] ?? '') ?>"
                                       placeholder="auto, 200px, 30vh">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PC 여백</label>
                                <input type="text" name="formData[pc_padding]" class="form-control"
                                       value="<?= htmlspecialchars($rowData['pc_padding'] ?? '') ?>"
                                       placeholder="25px 10px 20px 25px">
                                <small class="text-muted">위, 오른쪽, 아래, 왼쪽 순서</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mobile 여백</label>
                                <input type="text" name="formData[mobile_padding]" class="form-control"
                                       value="<?= htmlspecialchars($rowData['mobile_padding'] ?? '') ?>"
                                       placeholder="15px 10px 15px 10px">
                                <small class="text-muted">위, 오른쪽, 아래, 왼쪽 순서</small>
                            </div>
                        </div>
                    </div>

                    <!-- 배경 설정 -->
                    <div class="col-12 col-lg-6">
                        <h6 class="mb-3">배경</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">배경 색상</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-sm form-control-color p-0"
                                           id="row_bg_color_picker"
                                           value="<?= htmlspecialchars(($bgConfig['color'] ?? '') ?: '#ffffff') ?>"
                                           style="width: 28px; height: 28px;" title="색상 선택">
                                    <input type="text" name="formData[bg_color]" id="row_bg_color" class="form-control"
                                           value="<?= htmlspecialchars($bgConfig['color'] ?? '') ?>"
                                           placeholder="#ffffff">
                                </div>
                                <small class="text-muted">비워두면 기본 배경색 적용</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">배경 이미지</label>
                                <input type="hidden" name="formData[bg_image_old]" value="<?= htmlspecialchars($bgConfig['image'] ?? '') ?>">
                                <input type="file" name="bg_image" id="row_bg_image" class="form-control" accept="image/*">
                                <?php if (!empty($bgConfig['image'])): ?>
                                <div class="mt-2">
                                    <img src="<?= htmlspecialchars($bgConfig['image']) ?>" alt="배경 이미지"
                                         style="max-height: 60px; border-radius: 4px; border: 1px solid #dee2e6;">
                                </div>
                                <div class="form-check mt-1">
                                    <input type="checkbox" class="form-check-input" name="formData[bg_image_del]" value="1" id="bg_image_del">
                                    <label class="form-check-label" for="bg_image_del">기존 이미지 삭제</label>
                                </div>
                                <?php endif; ?>
                            </div>
                            <!-- 배경 이미지 옵션 -->
                            <div class="col-12" id="bg_image_options"<?php if (empty($bgConfig['image'])): ?> style="display:none"<?php endif; ?>>
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label small">크기 (size)</label>
                                        <select name="formData[bg_size]" class="form-select form-select-sm">
                                            <option value="cover"<?= ($bgConfig['size'] ?? 'cover') === 'cover' ? ' selected' : '' ?>>cover (채우기)</option>
                                            <option value="contain"<?= ($bgConfig['size'] ?? '') === 'contain' ? ' selected' : '' ?>>contain (맞추기)</option>
                                            <option value="auto"<?= ($bgConfig['size'] ?? '') === 'auto' ? ' selected' : '' ?>>auto (원본)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">위치 (position)</label>
                                        <select name="formData[bg_position]" class="form-select form-select-sm">
                                            <option value="center center"<?= ($bgConfig['position'] ?? 'center center') === 'center center' ? ' selected' : '' ?>>가운데</option>
                                            <option value="top center"<?= ($bgConfig['position'] ?? '') === 'top center' ? ' selected' : '' ?>>상단</option>
                                            <option value="bottom center"<?= ($bgConfig['position'] ?? '') === 'bottom center' ? ' selected' : '' ?>>하단</option>
                                            <option value="left center"<?= ($bgConfig['position'] ?? '') === 'left center' ? ' selected' : '' ?>>좌측</option>
                                            <option value="right center"<?= ($bgConfig['position'] ?? '') === 'right center' ? ' selected' : '' ?>>우측</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">반복 (repeat)</label>
                                        <select name="formData[bg_repeat]" class="form-select form-select-sm">
                                            <option value="no-repeat"<?= ($bgConfig['repeat'] ?? 'no-repeat') === 'no-repeat' ? ' selected' : '' ?>>반복 없음</option>
                                            <option value="repeat"<?= ($bgConfig['repeat'] ?? '') === 'repeat' ? ' selected' : '' ?>>전체 반복</option>
                                            <option value="repeat-x"<?= ($bgConfig['repeat'] ?? '') === 'repeat-x' ? ' selected' : '' ?>>가로 반복</option>
                                            <option value="repeat-y"<?= ($bgConfig['repeat'] ?? '') === 'repeat-y' ? ' selected' : '' ?>>세로 반복</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">스크롤</label>
                                        <select name="formData[bg_attachment]" class="form-select form-select-sm">
                                            <option value="scroll"<?= ($bgConfig['attachment'] ?? 'scroll') === 'scroll' ? ' selected' : '' ?>>스크롤</option>
                                            <option value="fixed"<?= ($bgConfig['attachment'] ?? '') === 'fixed' ? ' selected' : '' ?>>고정 (패럴랙스)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 칸 구성 -->
        <div class="card mt-4">
            <div class="px-3 pt-3 pb-0 fw-semibold d-flex justify-content-between align-items-center" style="font-size:0.9rem">
                <span><i class="bi bi-grid-3x2 me-2 text-pastel-sky"></i>칸 구성</span>
            </div>
            <div class="card-body">
                <p class="text-muted mb-3">
                    각 칸의 [설정] 버튼을 클릭하여 콘텐츠와 스타일을 설정하세요.
                </p>

                <!-- 칸 프리뷰 -->
                <?php $columnMargin = (int)($rowData['column_margin'] ?? 0); ?>
                <div id="columns-preview" class="d-flex flex-wrap mb-3" style="gap: <?= $columnMargin ?>px;">
                    <?php for ($i = 0; $i < $columnCount; $i++):
                        $col = $columns[$i] ?? [];
                        $contentType = $col['content_type'] ?? '';
                        $colWidth = $col['width'] ?? '';
                        $contentLabel = '';
                        foreach ($contentTypes as $ct) {
                            if ($ct['value'] === $contentType) {
                                $contentLabel = $ct['label'];
                                break;
                            }
                        }
                        if ($colWidth) {
                            $gapTotal = $columnMargin * ($columnCount - 1);
                            $cardStyle = "flex: 0 0 calc({$colWidth} - {$gapTotal}px / {$columnCount}); min-width: 150px;";
                        } else {
                            $cardStyle = 'flex: 1; min-width: 200px;';
                        }
                    ?>
                    <div class="column-preview-item card" style="<?= $cardStyle ?>" data-index="<?= $i ?>">
                        <div class="card-body text-center">
                            <h6 class="card-title"><?= $i + 1 ?>번째 칸<?php if ($colWidth): ?><span class="column-width-badge badge bg-info ms-1"><?= htmlspecialchars($colWidth) ?></span><?php endif; ?></h6>
                            <p class="card-text">
                                <?php if ($contentType): ?>
                                    <span class="column-type-badge badge bg-primary"><?= htmlspecialchars($contentLabel) ?></span>
                                <?php else: ?>
                                    <span class="column-type-badge badge bg-secondary">미설정</span>
                                <?php endif; ?>
                            </p>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="openColumnModal(<?= $i ?>)">
                                <?= $contentType ? '수정' : '설정' ?>
                            </button>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- 칸 데이터 (hidden) -->
                <!-- 삭제된 필드: content_count, content_style, style_config, aos_effect (content_config로 통합) -->
                <div id="columns-data">
                    <?php foreach ($columns as $i => $col): ?>
                    <input type="hidden" name="columns[<?= $i ?>][width]" value="<?= htmlspecialchars($col['width'] ?? '') ?>">
                    <input type="hidden" name="columns[<?= $i ?>][pc_padding]" value="<?= htmlspecialchars($col['pc_padding'] ?? '') ?>">
                    <input type="hidden" name="columns[<?= $i ?>][mobile_padding]" value="<?= htmlspecialchars($col['mobile_padding'] ?? '') ?>">
                    <input type="hidden" name="columns[<?= $i ?>][content_type]" value="<?= htmlspecialchars($col['content_type'] ?? '') ?>">
                    <input type="hidden" name="columns[<?= $i ?>][content_kind]" value="<?= htmlspecialchars($col['content_kind'] ?? 'CORE') ?>">
                    <input type="hidden" name="columns[<?= $i ?>][content_skin]" value="<?= htmlspecialchars($col['content_skin'] ?? '') ?>">
                    <input type="hidden" name="columns[<?= $i ?>][background_config]" value="<?= htmlspecialchars(json_encode($col['background_config'] ?? [])) ?>">
                    <input type="hidden" name="columns[<?= $i ?>][border_config]" value="<?= htmlspecialchars(json_encode($col['border_config'] ?? [])) ?>">
                    <input type="hidden" name="columns[<?= $i ?>][title_config]" value="<?= htmlspecialchars(json_encode($col['title_config'] ?? [])) ?>">
                    <input type="hidden" name="columns[<?= $i ?>][content_config]" value="<?= htmlspecialchars(json_encode($col['content_config'] ?? [])) ?>">
                    <input type="hidden" name="columns[<?= $i ?>][content_items]" value="<?= htmlspecialchars(json_encode($col['content_items'] ?? [])) ?>">
                    <input type="hidden" name="columns[<?= $i ?>][is_active]" value="<?= (int) ($col['is_active'] ?? 1) ?>">
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- 저장 버튼 -->
        <div class="sticky-act mt-3 sticky-status">
            <a href="/admin/block-row" class="btn btn-default">취소</a>
            <?php if (!empty($isEdit) && !empty($rowId)): ?>
            <a href="/admin/block-row/editor?id=<?= $rowId ?>" class="btn btn-outline-success">
                <i class="bi bi-layout-text-window-reverse me-1"></i>에디터 <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem">Beta</span>
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-outline-info" onclick="showPreview()">
                <i class="bi bi-eye me-1"></i>미리보기
            </button>
            <button type="button" class="btn btn-primary mublo-submit"
                    data-target="/admin/block-row/store"
                    data-callback="blockrowSaved">
                <i class="bi bi-check-lg me-1"></i>저장
            </button>
        </div>
    </form>
</div>

<!-- 미리보기 모달 -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye me-2"></i>블록 미리보기</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="previewLoading" class="text-center text-muted py-5">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-2">미리보기 생성 중...</p>
                </div>
                <div id="previewError" style="display: none;" class="p-3"></div>
                <iframe id="previewFrame" class="preview-iframe" style="display: none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
            </div>
        </div>
    </div>
</div>

<!-- 칸 설정 모달 -->
<div class="modal fade" id="columnModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><span id="modalColumnNumber">1</span>번째 칸 설정</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="modalColumnIndex" value="0">

                <!-- 탭 -->
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-style">스타일</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-title">제목</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-content">콘텐츠</button>
                    </li>
                </ul>

                <div class="tab-content pt-3">
                    <!-- 스타일 탭 -->
                    <div class="tab-pane fade show active" id="tab-style">
                        <div class="row g-3">
                            <!-- 칸 너비 -->
                            <div class="col-md-6">
                                <label class="form-label">칸 너비</label>
                                <div class="input-group">
                                    <input type="text" id="modal_column_width" class="form-control" placeholder="자동">
                                    <select id="modal_column_width_unit" class="form-select" style="max-width: 80px;">
                                        <option value="%">%</option>
                                        <option value="px">px</option>
                                    </select>
                                </div>
                                <small class="text-muted">비워두면 균등 분배</small>
                            </div>

                            <!-- 내부여백 -->
                            <div class="col-12">
                                <h6 class="mb-3">내부여백</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">PC 여백</label>
                                <input type="text" id="modal_pc_padding" class="form-control" placeholder="15px">
                                <small class="text-muted">위, 오른쪽, 아래, 왼쪽 순서</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Mobile 여백</label>
                                <input type="text" id="modal_mobile_padding" class="form-control" placeholder="10px">
                                <small class="text-muted">위, 오른쪽, 아래, 왼쪽 순서</small>
                            </div>

                            <!-- 배경 -->
                            <div class="col-12 mt-4">
                                <h6 class="mb-3">배경</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">배경 색상</label>
                                <div class="d-flex align-items-center gap-2">
                                    <input type="color" class="form-control form-control-sm form-control-color p-0"
                                           id="modal_bg_color_picker"
                                           value="#ffffff"
                                           style="width: 28px; height: 28px;" title="색상 선택">
                                    <input type="text" id="modal_bg_color" class="form-control" placeholder="#ffffff">
                                </div>
                                <small class="text-muted">비워두면 기본 배경색 적용</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">배경 이미지</label>
                                <input type="hidden" id="modal_bg_image" value="">
                                <input type="file" id="modal_bg_image_file" class="form-control" accept="image/*">
                                <div id="modal_bg_image_preview" class="mt-2" style="display:none">
                                    <img src="" alt="배경 이미지" style="max-height: 60px; border-radius: 4px; border: 1px solid #dee2e6;">
                                    <div class="form-check mt-1">
                                        <input type="checkbox" class="form-check-input" id="modal_bg_image_del">
                                        <label class="form-check-label small" for="modal_bg_image_del">기존 이미지 삭제</label>
                                    </div>
                                </div>
                            </div>
                            <!-- 배경 이미지 옵션 -->
                            <div class="col-12" id="modal_bg_image_options" style="display:none">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label small">크기 (size)</label>
                                        <select id="modal_bg_size" class="form-select form-select-sm">
                                            <option value="cover">cover (채우기)</option>
                                            <option value="contain">contain (맞추기)</option>
                                            <option value="auto">auto (원본)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">위치 (position)</label>
                                        <select id="modal_bg_position" class="form-select form-select-sm">
                                            <option value="center center">가운데</option>
                                            <option value="top center">상단</option>
                                            <option value="bottom center">하단</option>
                                            <option value="left center">좌측</option>
                                            <option value="right center">우측</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">반복 (repeat)</label>
                                        <select id="modal_bg_repeat" class="form-select form-select-sm">
                                            <option value="no-repeat">반복 없음</option>
                                            <option value="repeat">전체 반복</option>
                                            <option value="repeat-x">가로 반복</option>
                                            <option value="repeat-y">세로 반복</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small">스크롤</label>
                                        <select id="modal_bg_attachment" class="form-select form-select-sm">
                                            <option value="scroll">스크롤</option>
                                            <option value="fixed">고정 (패럴랙스)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- 테두리 -->
                            <div class="col-12 mt-4">
                                <h6 class="mb-3">테두리</h6>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">테두리 두께</label>
                                <input type="text" id="modal_border_width" class="form-control" placeholder="1px">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">테두리 색상</label>
                                <input type="text" id="modal_border_color" class="form-control" placeholder="#e5e5e5">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">테두리 라운드</label>
                                <input type="text" id="modal_border_radius" class="form-control" placeholder="8px">
                            </div>
                        </div>
                    </div>

                    <!-- 제목 탭 -->
                    <div class="tab-pane fade" id="tab-title">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" id="modal_title_show" class="form-check-input">
                                    <label class="form-check-label" for="modal_title_show">제목 표시</label>
                                </div>
                            </div>

                            <!-- 제목 상세 설정 (title_show 체크 시 표시) -->
                            <div class="col-12" id="title_detail_wrapper">
                                <div class="border rounded p-3">
                                    <h6 class="mb-3">제목 설정</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">제목 텍스트</label>
                                            <input type="text" id="modal_title_text" class="form-control" placeholder="최신 게시글" maxlength="25">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">제목 색상</label>
                                            <div class="d-flex align-items-center gap-2">
                                                <input type="color" class="form-control form-control-sm form-control-color p-0"
                                                       id="modal_title_color_picker"
                                                       value="#000000"
                                                       style="width: 28px; height: 28px;" title="색상 선택">
                                                <input type="text" id="modal_title_color" class="form-control" placeholder="#000000">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">제목 위치</label>
                                            <select id="modal_title_position" class="form-select">
                                                <option value="left">왼쪽</option>
                                                <option value="center">가운데</option>
                                                <option value="right">오른쪽</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">PC 크기</label>
                                            <div class="input-group">
                                                <input type="number" id="modal_title_size_pc" class="form-control" value="16" min="10" max="100">
                                                <span class="input-group-text">px</span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">MO 크기</label>
                                            <div class="input-group">
                                                <input type="number" id="modal_title_size_mo" class="form-control" value="14" min="10" max="100">
                                                <span class="input-group-text">px</span>
                                            </div>
                                        </div>
                                    </div>

                                    <h6 class="mb-3 mt-4">제목 이미지</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">PC 이미지</label>
                                            <input type="file" id="modal_title_pc_image_file" class="form-control form-control-sm" accept="image/*">
                                            <input type="hidden" id="modal_title_pc_image">
                                            <div id="modal_title_pc_image_preview" class="mt-2" style="display: none;">
                                                <img src="" alt="PC 제목 이미지" style="max-height: 60px;">
                                                <div class="form-check form-check-inline ms-2">
                                                    <input type="checkbox" id="modal_title_pc_image_del" class="form-check-input">
                                                    <label class="form-check-label text-danger" for="modal_title_pc_image_del">삭제</label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">MO 이미지</label>
                                            <input type="file" id="modal_title_mo_image_file" class="form-control form-control-sm" accept="image/*">
                                            <input type="hidden" id="modal_title_mo_image">
                                            <div id="modal_title_mo_image_preview" class="mt-2" style="display: none;">
                                                <img src="" alt="MO 제목 이미지" style="max-height: 60px;">
                                                <div class="form-check form-check-inline ms-2">
                                                    <input type="checkbox" id="modal_title_mo_image_del" class="form-check-input">
                                                    <label class="form-check-label text-danger" for="modal_title_mo_image_del">삭제</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <h6 class="mb-3 mt-4">문구 설정</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label">문구</label>
                                            <input type="text" id="modal_copytext" class="form-control" placeholder="새로운 소식을 확인하세요" maxlength="50">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">문구 색상</label>
                                            <div class="d-flex align-items-center gap-2">
                                                <input type="color" class="form-control form-control-sm form-control-color p-0"
                                                       id="modal_copytext_color_picker"
                                                       value="#666666"
                                                       style="width: 28px; height: 28px;" title="색상 선택">
                                                <input type="text" id="modal_copytext_color" class="form-control" placeholder="#666666">
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">문구 위치</label>
                                            <select id="modal_copytext_position" class="form-select">
                                                <option value="">위치 선택</option>
                                                <option value="left">왼쪽</option>
                                                <option value="right">오른쪽</option>
                                                <option value="top">위쪽</option>
                                                <option value="bottom">아래쪽</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">PC 크기</label>
                                            <div class="input-group">
                                                <input type="number" id="modal_copytext_size_pc" class="form-control" value="14" min="10" max="100">
                                                <span class="input-group-text">px</span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">MO 크기</label>
                                            <div class="input-group">
                                                <input type="number" id="modal_copytext_size_mo" class="form-control" value="12" min="10" max="100">
                                                <span class="input-group-text">px</span>
                                            </div>
                                        </div>
                                    </div>

                                    <h6 class="mb-3 mt-4">더보기 링크</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="form-check mb-2">
                                                <input type="checkbox" id="modal_more_link" class="form-check-input">
                                                <label class="form-check-label" for="modal_more_link">더보기 링크 사용</label>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">더보기 URL</label>
                                            <input type="text" id="modal_more_url" class="form-control" placeholder="/board/notice">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 콘텐츠 탭 -->
                    <div class="tab-pane fade" id="tab-content">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <label class="form-label">콘텐츠 타입</label>
                                        <select id="modal_content_type" class="form-select">
                                            <option value="">선택하세요</option>
                                            <?php foreach ($contentTypeGroups as $kind => $types): ?>
                                                <?php if (!empty($types)): ?>
                                                <optgroup label="<?= $kind ?>">
                                                    <?php foreach ($types as $type => $info): ?>
                                                    <option value="<?= $type ?>" data-kind="<?= $kind ?>">
                                                        <?= htmlspecialchars($info['title']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-6" id="content_skin_wrapper">
                                        <label class="form-label">스킨</label>
                                        <select id="modal_content_skin" class="form-select">
                                            <option value="">스킨 선택</option>
                                        </select>
                                    </div>
                                    <div class="col-6" id="content_count_wrapper">
                                        <label class="form-label">PC 출력갯수</label>
                                        <input type="number" id="modal_content_count_pc" class="form-control" value="5" min="1" max="100">
                                    </div>
                                    <div class="col-6" id="content_count_mo_wrapper">
                                        <label class="form-label">MO 출력갯수</label>
                                        <input type="number" id="modal_content_count_mo" class="form-control" value="4" min="1" max="100">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4" id="content_aos_wrapper" style="display: none;">
                                <label class="form-label">출력 이벤트</label>
                                <select id="modal_aos_effect" class="form-select">
                                    <option value="">없음</option>
                                    <optgroup label="Fade">
                                        <option value="fade-up">Fade Up</option>
                                        <option value="fade-down">Fade Down</option>
                                        <option value="fade-left">Fade Left</option>
                                        <option value="fade-right">Fade Right</option>
                                        <option value="fade-up-right">Fade Up Right</option>
                                        <option value="fade-up-left">Fade Up Left</option>
                                        <option value="fade-down-right">Fade Down Right</option>
                                        <option value="fade-down-left">Fade Down Left</option>
                                    </optgroup>
                                    <optgroup label="Flip">
                                        <option value="flip-up">Flip Up</option>
                                        <option value="flip-down">Flip Down</option>
                                        <option value="flip-left">Flip Left</option>
                                        <option value="flip-right">Flip Right</option>
                                    </optgroup>
                                    <optgroup label="Slide">
                                        <option value="slide-up">Slide Up</option>
                                        <option value="slide-down">Slide Down</option>
                                        <option value="slide-left">Slide Left</option>
                                        <option value="slide-right">Slide Right</option>
                                    </optgroup>
                                    <optgroup label="Zoom">
                                        <option value="zoom-in">Zoom In</option>
                                        <option value="zoom-in-up">Zoom In Up</option>
                                        <option value="zoom-in-down">Zoom In Down</option>
                                        <option value="zoom-in-left">Zoom In Left</option>
                                        <option value="zoom-in-right">Zoom In Right</option>
                                        <option value="zoom-out">Zoom Out</option>
                                        <option value="zoom-out-up">Zoom Out Up</option>
                                        <option value="zoom-out-down">Zoom Out Down</option>
                                        <option value="zoom-out-left">Zoom Out Left</option>
                                        <option value="zoom-out-right">Zoom Out Right</option>
                                    </optgroup>
                                </select>
                                <label class="form-label mt-3">시간(ms)</label>
                                <input type="number" id="modal_aos_duration" class="form-control" value="600" min="100" max="3000" step="100">
                            </div>

                            <!-- 출력 스타일 설정 (board, boardgroup 등에서 사용) -->
                            <div class="col-12" id="content_style_wrapper" style="display: none;">
                                <div class="border rounded p-3">
                                    <h6 class="mb-3">출력 스타일</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <h6 class="mb-2 small text-muted">PC 출력</h6>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="form-label small">스타일</label>
                                                    <select id="modal_pc_style" class="form-select form-select-sm">
                                                        <option value="list">리스트형</option>
                                                        <option value="slide">슬라이드형</option>
                                                        <option value="none">숨김</option>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small">1줄 출력갯수</label>
                                                    <select id="modal_pc_cols" class="form-select form-select-sm">
                                                        <?php for ($n = 1; $n <= 7; $n++): ?>
                                                        <option value="<?= $n ?>" <?= $n == 4 ? 'selected' : '' ?>><?= $n ?>개</option>
                                                        <?php endfor; ?>
                                                        <option value="auto">자동</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div id="pc_slide_options" class="mt-2" style="display: none;">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="modal_pc_autoplay_check">
                                                    <label class="form-check-label small" for="modal_pc_autoplay_check">자동재생</label>
                                                </div>
                                                <div class="input-group input-group-sm mt-1">
                                                    <input type="number" id="modal_pc_autoplay_delay" class="form-control form-control-sm"
                                                           value="5000" min="1000" max="30000" step="500" disabled>
                                                    <span class="input-group-text">ms</span>
                                                </div>
                                                <div class="form-check mt-1">
                                                    <input type="checkbox" class="form-check-input" id="modal_pc_loop">
                                                    <label class="form-check-label small" for="modal_pc_loop">무한반복</label>
                                                </div>
                                                <div class="form-check mt-2">
                                                    <input type="checkbox" class="form-check-input" id="modal_pc_slide_cover">
                                                    <label class="form-check-label small" for="modal_pc_slide_cover">이미지 높이 맞춤 (cover)</label>
                                                </div>
                                                <small class="text-muted">가장 작은 이미지 높이에 맞춰 나머지를 크롭</small>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="mb-2 small text-muted">모바일 출력</h6>
                                            <div class="row g-2">
                                                <div class="col-6">
                                                    <label class="form-label small">스타일</label>
                                                    <select id="modal_mo_style" class="form-select form-select-sm">
                                                        <option value="list">리스트형</option>
                                                        <option value="slide">슬라이드형</option>
                                                        <option value="none">숨김</option>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label small">1줄 출력갯수</label>
                                                    <select id="modal_mo_cols" class="form-select form-select-sm">
                                                        <?php for ($n = 1; $n <= 7; $n++): ?>
                                                        <option value="<?= $n ?>" <?= $n == 2 ? 'selected' : '' ?>><?= $n ?>개</option>
                                                        <?php endfor; ?>
                                                        <option value="auto">자동</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div id="mo_slide_options" class="mt-2" style="display: none;">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="modal_mo_autoplay_check">
                                                    <label class="form-check-label small" for="modal_mo_autoplay_check">자동재생</label>
                                                </div>
                                                <div class="input-group input-group-sm mt-1">
                                                    <input type="number" id="modal_mo_autoplay_delay" class="form-control form-control-sm"
                                                           value="3000" min="1000" max="30000" step="500" disabled>
                                                    <span class="input-group-text">ms</span>
                                                </div>
                                                <div class="form-check mt-1">
                                                    <input type="checkbox" class="form-check-input" id="modal_mo_loop">
                                                    <label class="form-check-label small" for="modal_mo_loop">무한반복</label>
                                                </div>
                                                <div class="form-check mt-2">
                                                    <input type="checkbox" class="form-check-input" id="modal_mo_slide_cover">
                                                    <label class="form-check-label small" for="modal_mo_slide_cover">이미지 높이 맞춤 (cover)</label>
                                                </div>
                                                <small class="text-muted">가장 작은 이미지 높이에 맞춰 나머지를 크롭</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 아이템 선택 (board, boardgroup, menu) -->
                            <div class="col-12" id="content_items_container" style="display: none;">
                                <label class="form-label">아이템 선택</label>
                                <div class="dual-listbox-wrapper">
                                    <p class="text-muted">콘텐츠 타입을 선택하면 아이템 목록이 표시됩니다.</p>
                                </div>
                                <small class="text-muted">왼쪽에서 아이템을 선택하여 오른쪽으로 이동하세요. 더블클릭 또는 버튼을 사용할 수 있습니다.</small>
                            </div>

                            <!-- outlogin 타입용 설정 -->
                            <div class="col-12" id="outlogin_config_wrapper" style="display: none;">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="modal_outlogin_show_pc" checked>
                                            <label class="form-check-label" for="modal_outlogin_show_pc">PC 출력</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" id="modal_outlogin_show_mobile" checked>
                                            <label class="form-check-label" for="modal_outlogin_show_mobile">모바일 출력</label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- include 타입용 파일 선택 -->
                            <div class="col-12" id="include_path_wrapper" style="display: none;">
                                <label class="form-label">포함할 파일</label>
                                <select id="modal_include_path" class="form-select">
                                    <option value="">선택하세요</option>
                                    <?php
                                    $includeFiles = \Mublo\Core\Block\Renderer\IncludeRenderer::getAvailableFiles();
                                    foreach ($includeFiles as $incFile): ?>
                                    <option value="<?= htmlspecialchars($incFile) ?>"><?= htmlspecialchars($incFile) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($includeFiles)): ?>
                                <small class="text-muted">views/Block/include/ 디렉토리에 PHP 파일을 추가하세요.</small>
                                <?php else: ?>
                                <small class="text-muted">views/Block/include/ 내 파일 목록</small>
                                <?php endif; ?>
                            </div>

                            <!-- image 타입용 설정 -->
                            <div class="col-12" id="image_config_wrapper" style="display: none;">
                                <div class="card border-secondary">
                                    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                                        <i class="bi bi-image me-2 text-pastel-sky"></i>이미지 설정
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3" id="image_items_container">
                                            <!-- 이미지 아이템들이 여기에 동적으로 추가됩니다 -->
                                        </div>
                                        <div class="text-center mt-3">
                                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn_add_image">
                                                <i class="bi bi-plus-lg me-1"></i>이미지 추가
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- movie 타입용 설정 -->
                            <div class="col-12" id="movie_config_wrapper" style="display: none;">
                                <div class="card border-secondary">
                                    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                                        <i class="bi bi-play-circle me-2 text-pastel-orange"></i>동영상 설정
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-4">
                                                <label class="form-label">동영상 타입</label>
                                                <select id="modal_video_type" class="form-select">
                                                    <option value="youtube">YouTube</option>
                                                    <option value="vimeo">Vimeo</option>
                                                    <option value="url">직접 URL</option>
                                                </select>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="form-label" id="video_input_label">YouTube URL 또는 영상 ID</label>
                                                <input type="text" id="modal_video_url" class="form-control"
                                                       placeholder="https://www.youtube.com/watch?v=... 또는 영상 ID">
                                                <small class="text-muted" id="video_input_hint">YouTube 링크를 붙여넣거나 영상 ID만 입력하세요.</small>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input type="checkbox" id="modal_video_autoplay" class="form-check-input">
                                                    <label class="form-check-label" for="modal_video_autoplay">자동 재생</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input type="checkbox" id="modal_video_muted" class="form-check-input" checked>
                                                    <label class="form-check-label" for="modal_video_muted">음소거 (자동재생 시 필수)</label>
                                                </div>
                                            </div>
                                            <div class="col-12" id="video_preview_area" style="display: none;">
                                                <label class="form-label">미리보기</label>
                                                <div class="ratio ratio-16x9 border rounded overflow-hidden">
                                                    <iframe id="modal_video_preview" src="" allowfullscreen></iframe>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- HTML 콘텐츠용 에디터 (html 타입 선택 시 표시) -->
                            <div class="col-12" id="html_editor_wrapper" style="display: none;">
                                <label class="form-label">HTML 콘텐츠</label>
                                <?= editor_html('modal_html_content', '', ['height' => 400]) ?>
                                <small class="text-muted">직접 HTML을 입력하거나 에디터를 사용하여 콘텐츠를 작성하세요.</small>

                                <!-- CSS / JS 입력 (접이식) -->
                                <div class="mt-3">
                                    <div class="d-flex gap-2 mb-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#html_css_collapse">
                                            <i class="bi bi-filetype-css me-1"></i>CSS
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#html_js_collapse">
                                            <i class="bi bi-filetype-js me-1"></i>JavaScript
                                        </button>
                                    </div>
                                    <div class="collapse" id="html_css_collapse">
                                        <label class="form-label small text-muted mb-1">CSS <small>(&lt;style&gt; 태그 없이 작성)</small></label>
                                        <textarea id="modal_html_css" class="form-control font-monospace" rows="8" placeholder=".my-section { padding: 60px 20px; }&#10;.my-section h2 { font-size: 32px; }" style="font-size: 13px; tab-size: 4; line-height: 1.5;"></textarea>
                                    </div>
                                    <div class="collapse mt-2" id="html_js_collapse">
                                        <label class="form-label small text-muted mb-1">JavaScript <small>(&lt;script&gt; 태그 없이 작성)</small></label>
                                        <textarea id="modal_html_js" class="form-control font-monospace" rows="8" placeholder="(function(){&#10;    // your code here&#10;})();" style="font-size: 13px; tab-size: 4; line-height: 1.5;"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" onclick="saveColumnSettings()">적용</button>
            </div>
        </div>
    </div>
</div>

<!-- BlockRow Form JS -->
<script src="/assets/js/admin/blockrow-form.js"></script>
<script>
// 저장 완료 콜백
MubloRequest.registerCallback('blockrowSaved', function(response) {
    if (response.result === 'success') {
        MubloRequest.showToast(response.message || '<?= $isEdit ? '수정' : '등록' ?>되었습니다.', 'success');
        if (response.data && response.data.redirect) {
            location.href = response.data.redirect;
        }
    } else {
        MubloRequest.showAlert(response.message || '저장에 실패했습니다.', 'error');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    BlockRowForm.init({
        contentTypes: <?= json_encode($contentTypes) ?>,
        contentTypeGroups: <?= json_encode($contentTypeGroups) ?>,
        skinLists: <?= json_encode($skinLists ?? []) ?>,
        domainId: <?= json_encode($domainId ?? 1) ?>
    });

    // 와이드 가능 위치 (컨테이너 외부에 렌더링되는 위치)
    var wideAllowedPositions = ['index', 'subhead', 'subfoot'];

    // 출력 위치 변경 시 position_menu 표시/숨김 + 넓이 타입 제한
    var posSelect = document.getElementById('position_select');
    var menuWrapper = document.getElementById('position_menu_wrapper');
    var menuSelect = document.getElementById('position_menu_select');
    var widthTypeSelect = document.getElementById('width_type_select');
    var widthTypeHint = document.getElementById('width_type_hint');

    function updateWidthTypeByPosition() {
        if (!posSelect || !widthTypeSelect) return;
        var pos = posSelect.value;
        var isConstrained = pos && wideAllowedPositions.indexOf(pos) === -1;

        // disabled select는 FormData에 포함되지 않으므로 hidden input으로 보완
        var hiddenInput = document.getElementById('width_type_hidden');
        if (isConstrained) {
            widthTypeSelect.value = '1';
            widthTypeSelect.disabled = true;
            if (widthTypeHint) widthTypeHint.style.display = '';
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.id = 'width_type_hidden';
                hiddenInput.name = 'formData[width_type]';
                widthTypeSelect.parentNode.appendChild(hiddenInput);
            }
            hiddenInput.value = '1';
        } else {
            widthTypeSelect.disabled = false;
            if (widthTypeHint) widthTypeHint.style.display = 'none';
            if (hiddenInput) hiddenInput.remove();
        }
    }

    if (posSelect) {
        posSelect.addEventListener('change', function() {
            // position_menu 표시/숨김
            if (menuWrapper) {
                if (this.value === 'index') {
                    menuWrapper.style.display = 'none';
                    if (menuSelect) menuSelect.value = '';
                } else {
                    menuWrapper.style.display = '';
                }
            }
            // 넓이 타입 제한
            updateWidthTypeByPosition();
        });

        // 초기 상태 적용
        updateWidthTypeByPosition();
    }

    // 배경 색상: 컬러 픽커 ↔ 텍스트 입력 동기화
    var colorPicker = document.getElementById('row_bg_color_picker');
    var colorText = document.getElementById('row_bg_color');
    if (colorPicker && colorText) {
        colorPicker.addEventListener('input', function() {
            colorText.value = this.value;
        });
        colorText.addEventListener('input', function() {
            if (/^#[0-9a-fA-F]{6}$/.test(this.value)) {
                colorPicker.value = this.value;
            }
        });
    }

    // 배경 이미지: 옵션 영역 표시/숨김
    var bgImageInput = document.getElementById('row_bg_image');
    var bgImageOptions = document.getElementById('bg_image_options');
    var bgImageDel = document.getElementById('bg_image_del');
    if (bgImageInput && bgImageOptions) {
        bgImageInput.addEventListener('change', function() {
            if (this.files && this.files.length > 0) {
                bgImageOptions.style.display = '';
            }
        });
    }
    if (bgImageDel && bgImageOptions) {
        bgImageDel.addEventListener('change', function() {
            bgImageOptions.style.display = this.checked ? 'none' : '';
        });
    }
});
</script>

<?= editor_js() ?>
