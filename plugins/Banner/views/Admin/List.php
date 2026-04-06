<?php
/**
 * 배너 목록
 *
 * View Context 접근:
 * - $this->columns() : ListColumnBuilder 팩토리
 * - $this->listRenderHelper : ListRenderHelper 인스턴스
 * - $this->pagination($data) : 페이지네이션 렌더링
 *
 * @var string $pageTitle 페이지 제목
 * @var array $items 배너 목록
 * @var array $pagination 페이지네이션
 * @var array $search 검색 조건
 */
$items = $items ?? [];
$pagination = $pagination ?? [];
$search = $search ?? [];
$keyword = htmlspecialchars($search['keyword'] ?? '');

// 컬럼 정의
$columns = $this->columns()
    ->checkbox('chk', '', ['id_key' => 'banner_id', '_th_attr' => ['style' => 'width:40px']])
    ->add('banner_id', 'No.', ['sortable' => true, '_th_attr' => ['style' => 'width:60px']])
    ->add('preview', '미리보기', [
        '_th_attr' => ['style' => 'width:80px'],
        'render' => function ($row) {
            if (!empty($row['pc_image_url'])) {
                $src = htmlspecialchars($row['pc_image_url']);
                return "<img src='{$src}' alt='' style='max-width:60px; max-height:40px; object-fit:cover; border-radius:4px;'>";
            }
            return '<span class="text-muted small">-</span>';
        },
    ])
    ->add('title', '배너명', [
        'render' => function ($row) {
            $id = (int) $row['banner_id'];
            $title = htmlspecialchars($row['title'] ?? '');
            $html = "<a href='/admin/banner/{$id}/edit' class='text-decoration-none'><strong>{$title}</strong></a>";
            if (!empty($row['link_url'])) {
                $link = htmlspecialchars($row['link_url']);
                $html .= "<br><small class='text-muted'>{$link}</small>";
            }
            return $html;
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
                return '<span class="badge bg-success">사용</span>';
            }
            return '<span class="badge bg-secondary">미사용</span>';
        },
    ])
    ->actions('actions', '관리', function ($row) {
        $id = (int) $row['banner_id'];
        return "
            <a href='/admin/banner/{$id}/edit' class='btn btn-sm btn-default'>수정</a>
            <button type='button' class='btn btn-sm btn-default' onclick='deleteBanner({$id}, this)'>삭제</button>
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
                <p class="text-muted mb-0">배너를 등록하고 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/banner/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>배너 추가
                </a>
            </div>
        </div>
    </div>

    <!-- 검색 영역 -->
    <form method="get" name="fsearch" id="fsearch" action="/admin/banner/list" class="mt-4 mb-2">
        <div class="row align-items-center gy-2 gy-xl-0">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/banner/list">전체</a></span>
                    <span class="ov-num"><b><?= number_format($pagination['totalItems'] ?? 0) ?></b> 개</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2">
                    <div class="col col-xl-auto">
                        <div class="search-wrapper">
                            <label for="search_keyword" class="visually-hidden">검색</label>
                            <input type="text" name="keyword" id="search_keyword" class="form-control"
                                   placeholder="배너명 검색"
                                   value="<?= $keyword ?>">
                            <i class="bi bi-search search-icon"></i>
                            <?php if (!empty($keyword)): ?>
                            <i class="bi bi-x-lg search-reset-icon" onclick="location.href='/admin/banner/list'"></i>
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

    <!-- 배너 목록 폼 -->
    <form name="flist" id="flist">
        <!-- 배너 목록 테이블 -->
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
                        data-target="/admin/banner/listDelete"
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

// 배너 삭제 (단건)
function deleteBanner(bannerId) {
    if (!confirm('이 배너를 삭제하시겠습니까?')) {
        return;
    }

    MubloRequest.requestJson('/admin/banner/' + bannerId + '/delete', {}, { method: 'POST', loading: true })
        .then(function() {
            location.reload();
        });
}

// 일괄 삭제 후 콜백
function afterBulkDelete(data) {
    alert(data.message || '삭제되었습니다.');
    location.reload();
}
</script>
