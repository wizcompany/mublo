<?php
/**
 * Admin Boardcategory - Index
 *
 * 게시판 카테고리 목록
 *
 * View Context 접근:
 * - $this->columns() : ListColumnBuilder 팩토리
 * - $this->listRenderHelper : ListRenderHelper 인스턴스
 * - $this->pagination($data) : 페이지네이션 렌더링
 *
 * @var string $pageTitle 페이지 제목
 * @var array $categories 카테고리 목록
 * @var array $pagination 페이지네이션 정보
 */

// 컬럼 정의
$columns = $this->columns()
    ->checkbox('chk', '', ['id_key' => 'category_id', '_th_attr' => ['style' => 'width:40px']])
    ->add('sort', '', [
        'render' => fn($row) => '<i class="bi bi-arrows-move text-muted handle" style="cursor:grab"></i>',
        '_th_attr' => ['style' => 'width:40px']
    ])
    ->add('category_id', 'ID', [
        'render' => fn($row) => '<small class="text-muted">#' . $row['category_id'] . '</small>',
        '_th_attr' => ['style' => 'width:60px']
    ])
    ->add('category_slug', '슬러그', [
        'render' => fn($row) => '<code>' . htmlspecialchars($row['category_slug']) . '</code>'
    ])
    ->add('category_name', '카테고리명', [
        'render' => function($row) {
            $html = '<strong>' . htmlspecialchars($row['category_name']) . '</strong>';
            if (!empty($row['category_description'])) {
                $html .= '<br><small class="text-muted">' . htmlspecialchars($row['category_description']) . '</small>';
            }
            return $html;
        }
    ])
    ->add('board_count', '사용 게시판', [
        'render' => fn($row) => '<span class="badge bg-secondary">' . ($row['board_count'] ?? 0) . '</span>',
        '_th_attr' => ['style' => 'width:90px', 'class' => 'text-center'],
        '_td_attr' => ['class' => 'text-center']
    ])
    ->select('is_active', '상태', [
        1 => '사용',
        0 => '미사용',
    ], ['id_key' => 'category_id', '_th_attr' => ['style' => 'width:100px']])
    ->actions('actions', '관리', function($row) {
        $id = $row['category_id'];
        $name = htmlspecialchars($row['category_name'], ENT_QUOTES);
        $boardCount = $row['board_count'] ?? 0;

        $html = "<a href='/admin/board/category/edit?id={$id}' class='btn btn-sm btn-default'>수정</a> ";

        if ($boardCount === 0) {
            $html .= "<button type='button' class='btn btn-sm btn-default' onclick=\"deleteCategory({$id}, '{$name}')\">삭제</button>";
        } else {
            $html .= "<button type='button' class='btn btn-sm btn-default' disabled title='사용 중인 게시판이 있어 삭제 불가'>삭제</button>";
        }

        return $html;
    }, ['_th_attr' => ['style' => 'width:120px']])
    ->build();
?>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '게시판 카테고리 관리') ?></h3>
                <p class="text-muted mb-0">게시판에서 사용할 카테고리를 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/board/category/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>카테고리 추가
                </a>
            </div>
        </div>
    </div>

    <!-- 요약 영역 -->
    <div class="mt-4 mb-2">
        <span class="ov">
            <span class="ov-txt"><a href="/admin/board/category">전체</a></span>
            <span class="ov-num"><b><?= number_format($pagination['totalItems'] ?? 0) ?></b> 개</span>
        </span>
    </div>

    <!-- 카테고리 목록 폼 -->
    <form name="flist" id="flist">
        <div class="table-responsive">
            <?= $this->listRenderHelper
                ->setColumns($columns)
                ->setRows($categories)
                ->setSkin('table/basic')
                ->setWrapAttr(['class' => 'table table-hover align-middle'])
                ->setTrAttr(fn($row) => ['data-category-id' => $row['category_id']])
                ->showHeader(true)
                ->render() ?>
        </div>

        <!-- 하단 액션바 -->
        <div class="row gx-2 justify-content-between align-items-center my-2">
            <div class="col-auto">
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-default mublo-submit"
                            data-target="/admin/board/category/list-update"
                            data-callback="afterListUpdate">
                        <i class="d-inline d-md-none bi bi-pencil-square"></i>
                        <span class="d-none d-md-inline">선택 수정</span>
                    </button>
                    <button type="button" class="btn btn-default mublo-submit"
                            data-target="/admin/board/category/list-delete"
                            data-callback="afterListDelete">
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

    <!-- 안내 -->
    <div class="card mt-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
            <i class="bi bi-question-circle me-2 text-pastel-blue"></i>카테고리 안내
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <li>카테고리는 여러 게시판에서 공유하여 사용할 수 있습니다.</li>
                <li>게시판 설정에서 카테고리 사용을 활성화하고, 사용할 카테고리를 선택합니다.</li>
                <li>게시판에서 사용 중인 카테고리는 삭제할 수 없습니다.</li>
                <li>드래그하여 카테고리 순서를 변경할 수 있습니다.</li>
            </ul>
        </div>
    </div>
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

// 카테고리 삭제 (단건)
function deleteCategory(categoryId, categoryName) {
    if (!confirm('\'' + categoryName + '\' 카테고리를 삭제하시겠습니까?')) {
        return;
    }

    MubloRequest.requestJson('/admin/board/category/delete', {
        category_id: categoryId
    }).then(function(response) {
        alert(response.message || '카테고리가 삭제되었습니다.');
        location.reload();
    });
}

// 일괄 수정 후 콜백
function afterListUpdate(data) {
    if (data.result === 'success') {
        alert(data.message || '수정되었습니다.');
        location.reload();
    }
}

// 일괄 삭제 후 콜백
function afterListDelete(data) {
    if (data.result === 'success') {
        alert(data.message || '삭제되었습니다.');
        location.reload();
    }
}

// 드래그 앤 드롭 정렬 (Sortable.js)
document.addEventListener('DOMContentLoaded', function() {
    var tbody = document.querySelector('#flist tbody');
    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            handle: '.handle',
            animation: 150,
            onEnd: function() {
                var categoryIds = [];
                tbody.querySelectorAll('tr[data-category-id]').forEach(function(row) {
                    categoryIds.push(parseInt(row.dataset.categoryId));
                });

                MubloRequest.requestJson('/admin/board/category/order-update', {
                    category_ids: categoryIds
                }).then(function() {
                    // 성공 시 조용히 저장
                });
            }
        });
    }
});
</script>
