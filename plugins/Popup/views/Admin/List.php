<?php
/**
 * 팝업 목록
 *
 * View Context 접근:
 * - $this->columns() : ListColumnBuilder 팩토리
 * - $this->listRenderHelper : ListRenderHelper 인스턴스
 * - $this->pagination($data) : 페이지네이션 렌더링
 *
 * @var string $pageTitle 페이지 제목
 * @var array $items 팝업 목록
 * @var array $pagination 페이지네이션
 * @var array $search 검색 조건
 * @var array $config 팝업 설정
 * @var array $skinOptions 스킨 목록
 */
$items = $items ?? [];
$pagination = $pagination ?? [];
$search = $search ?? [];
$keyword = htmlspecialchars($search['keyword'] ?? '');
$config = $config ?? [];
$skinOptions = $skinOptions ?? ['basic' => 'basic'];
$currentSkin = $config['popup_skin'] ?? 'basic';

// 컬럼 정의
$columns = $this->columns()
    ->checkbox('chk', '', ['id_key' => 'popup_id', '_th_attr' => ['style' => 'width:40px']])
    ->add('popup_id', 'No.', ['sortable' => true, '_th_attr' => ['style' => 'width:60px']])
    ->add('title', '팝업명', [
        'render' => function ($row) {
            $id = (int) $row['popup_id'];
            $title = htmlspecialchars($row['title'] ?? '');
            return "<a href='/admin/popup/{$id}/edit' class='text-decoration-none'><strong>{$title}</strong></a>";
        },
    ])
    ->add('position', '위치', [
        '_th_attr' => ['style' => 'width:80px; text-align:center'],
        '_cell_attr' => ['style' => 'text-align:center'],
        'render' => function ($row) {
            $position = htmlspecialchars($row['position'] ?? '');
            return "<span class='badge bg-info'>{$position}</span>";
        },
    ])
    ->add('display_device', '디바이스', [
        '_th_attr' => ['style' => 'width:100px; text-align:center'],
        '_cell_attr' => ['style' => 'text-align:center'],
        'render' => function ($row) {
            $device = $row['display_device'] ?? 'all';
            if ($device === 'pc') {
                return '<span class="badge bg-info">PC전용</span>';
            }
            if ($device === 'mo') {
                return '<span class="badge bg-warning">모바일전용</span>';
            }
            return '<span class="badge bg-secondary">전체</span>';
        },
    ])
    ->add('period', '노출 기간', [
        '_th_attr' => ['style' => 'width:150px'],
        'render' => function ($row) {
            $start = $row['start_date'] ?? '';
            $end = $row['end_date'] ?? '';
            if ($start || $end) {
                $s = $start ?: '시작일 없음';
                $e = $end ?: '종료일 없음';
                return "<small>{$s}<br>~ {$e}</small>";
            }
            return '<span class="text-muted small">상시</span>';
        },
    ])
    ->add('hide_duration', '숨김 시간', [
        '_th_attr' => ['style' => 'width:90px; text-align:center'],
        '_cell_attr' => ['style' => 'text-align:center'],
        'render' => function ($row) {
            $hours = (int) ($row['hide_duration'] ?? 0);
            if ($hours <= 0) {
                return '<span class="text-muted small">-</span>';
            }
            return "<small>{$hours}시간</small>";
        },
    ])
    ->add('sort_order', '순서', [
        '_th_attr' => ['style' => 'width:60px; text-align:center'],
        '_cell_attr' => ['style' => 'text-align:center'],
        'render' => function ($row) {
            $order = (int) ($row['sort_order'] ?? 0);
            return "<span class='badge bg-light text-dark'>{$order}</span>";
        },
    ])
    ->add('is_active', '상태', [
        '_th_attr' => ['style' => 'width:80px; text-align:center'],
        '_cell_attr' => ['style' => 'text-align:center'],
        'render' => function ($row) {
            $active = (int) ($row['is_active'] ?? 1);
            if ($active) {
                return '<span class="badge bg-success">활성</span>';
            }
            return '<span class="badge bg-secondary">비활성</span>';
        },
    ])
    ->actions('actions', '관리', function ($row) {
        $id = (int) $row['popup_id'];
        return "
            <a href='/admin/popup/{$id}/edit' class='btn btn-sm btn-default'>수정</a>
            <button type='button' class='btn btn-sm btn-default' onclick='deletePopup({$id}, this)'>삭제</button>
        ";
    })
    ->build();
?>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle) ?></h3>
                <p class="text-muted mb-0">팝업을 등록하고 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/popup/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>팝업 추가
                </a>
            </div>
        </div>
    </div>

    <!-- 스킨 설정 -->
    <div class="card mt-4 mb-3">
        <div class="card-body py-2">
            <form id="skinConfigForm" class="row align-items-center g-2">
                <div class="col-auto">
                    <label class="form-label mb-0 fw-bold"><i class="bi bi-palette me-1"></i>팝업 스킨</label>
                </div>
                <div class="col-auto">
                    <select name="formData[popup_skin]" class="form-select form-select-sm" style="width:auto">
                        <?php foreach ($skinOptions as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key) ?>"<?= $key === $currentSkin ? ' selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="button" class="btn btn-primary btn-sm mublo-submit"
                            data-target="/admin/popup/config/save"
                            data-callback="onSkinSaved">
                        저장
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 검색 영역 -->
    <form method="get" name="fsearch" id="fsearch" action="/admin/popup/list" class="mt-4 mb-2">
        <div class="row align-items-center gy-2 gy-xl-0">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/popup/list">전체</a></span>
                    <span class="ov-num"><b><?= number_format($pagination['totalItems'] ?? 0) ?></b> 개</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2">
                    <div class="col col-xl-auto">
                        <div class="search-wrapper">
                            <label for="search_keyword" class="visually-hidden">검색</label>
                            <input type="text" name="keyword" id="search_keyword" class="form-control"
                                   placeholder="팝업명 검색"
                                   value="<?= $keyword ?>">
                            <i class="bi bi-search search-icon"></i>
                            <?php if (!empty($keyword)): ?>
                            <i class="bi bi-x-lg search-reset-icon" onclick="location.href='/admin/popup/list'"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-default">
                            <i class="bi bi-search me-1"></i>검색
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- 팝업 목록 폼 -->
    <form name="flist" id="flist">
        <!-- 팝업 목록 테이블 -->
        <div class="table-responsive">
            <?= $this->listRenderHelper
                ->setColumns($columns)
                ->setRows($items)
                ->setSkin('table/basic')
                ->setWrapAttr(['class' => 'table table-hover align-middle'])
                ->showHeader(true)
                ->render() ?>
        </div>

        <!-- 하단 액션바 + 페이지네이션 -->
        <div class="row gx-2 justify-content-between align-items-center my-2">
            <div class="col-auto">
                <div class="d-flex gap-1">
                    <button
                        type="button"
                        class="btn btn-default mublo-submit"
                        data-target="/admin/popup/listDelete"
                        data-callback="afterBulkDelete"
                    >
                        <i class="d-inline d-md-none bi bi-trash"></i>
                        <span class="d-none d-md-inline">선택 삭제</span>
                    </button>
                </div>
            </div>
            <div class="col-auto d-none d-md-block">
                <?= $pagination['currentPage'] ?? 1 ?> / <?= $pagination['totalPages'] ?? 1 ?> 페이지
            </div>
            <div class="col-auto">
                <?= $this->pagination($pagination) ?>
            </div>
        </div>
    </form>
</div>

<script>
// 전체 선택
document.addEventListener('DOMContentLoaded', function() {
    var checkAll = document.querySelector('input[name="chk_all"]');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            var checkboxes = document.querySelectorAll('input[name="chk[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = checkAll.checked;
            });
        });
    }
});

// 팝업 삭제 (단건)
function deletePopup(popupId) {
    if (!confirm('이 팝업을 삭제하시겠습니까?')) {
        return;
    }

    MubloRequest.requestJson('/admin/popup/' + popupId + '/delete', {}, { method: 'POST', loading: true })
        .then(function() {
            location.reload();
        });
}

// 일괄 삭제 후 콜백
function afterBulkDelete(data) {
    alert(data.message || '삭제되었습니다.');
    location.reload();
}

// 스킨 설정 저장 콜백
MubloRequest.registerCallback('onSkinSaved', function(response) {
    alert(response.message || '저장되었습니다.');
});
</script>
