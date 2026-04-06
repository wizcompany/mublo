<?php
/**
 * Admin Blockpage - Index
 *
 * 블록 페이지 목록
 *
 * @var string $pageTitle 페이지 제목
 * @var array $pages 페이지 목록
 * @var array|null $pagination 페이지네이션 정보
 */

// 컬럼 정의
$columns = $this->columns()
    ->checkbox('chk', '', ['id_key' => 'page_id', '_th_attr' => ['style' => 'width:40px']])
    ->add('page_id', 'ID', [
        'render' => fn($row) => '<small class="text-muted">#' . $row['page_id'] . '</small>',
        '_th_attr' => ['style' => 'width:60px']
    ])
    ->add('page_code', '코드', [
        'render' => fn($row) => '<code>' . htmlspecialchars($row['page_code']) . '</code>'
    ])
    ->add('page_title', '제목', [
        'render' => function($row) {
            $html = '<strong>' . htmlspecialchars($row['page_title']) . '</strong>';
            if (!empty($row['page_description'])) {
                $desc = mb_substr($row['page_description'], 0, 30);
                $desc .= mb_strlen($row['page_description']) > 30 ? '...' : '';
                $html .= '<br><small class="text-muted">' . htmlspecialchars($desc) . '</small>';
            }
            return $html;
        }
    ])
    ->add('row_count', '행 수', [
        'render' => fn($row) => '<span class="badge bg-secondary">' . ($row['row_count'] ?? 0) . '</span>',
        '_th_attr' => ['style' => 'width:70px', 'class' => 'text-center'],
        '_td_attr' => ['class' => 'text-center']
    ])
    ->add('allow_level', '접근레벨', [
        'render' => function($row) {
            $level = $row['allow_level'] ?? 0;
            return $level == 0 ? '<small class="text-muted">모두</small>' : "Lv.{$level}";
        },
        '_th_attr' => ['style' => 'width:80px', 'class' => 'text-center'],
        '_td_attr' => ['class' => 'text-center']
    ])
    ->add('layout', '레이아웃', [
        'render' => function($row) {
            $mode = (int) ($row['use_fullpage'] ?? 0);
            if ($mode === 1) return '<span class="badge bg-info">와이드</span>';
            if ($mode === 2) return '<span class="badge bg-warning text-dark">' . (int)($row['custom_width'] ?? 0) . 'px</span>';
            return '<span class="badge bg-light text-dark">기본</span>';
        },
        '_th_attr' => ['style' => 'width:90px', 'class' => 'text-center'],
        '_td_attr' => ['class' => 'text-center']
    ])
    ->select('is_active', '상태', [
        1 => '사용',
        0 => '미사용',
    ], ['id_key' => 'page_id', '_th_attr' => ['style' => 'width:100px']])
    ->actions('actions', '관리', function($row) {
        $id = $row['page_id'];
        $name = htmlspecialchars($row['page_title'], ENT_QUOTES);
        $code = htmlspecialchars($row['page_code'], ENT_QUOTES);
        $rowCount = $row['row_count'] ?? 0;

        $html = "<a href='/admin/block-page/edit?id={$id}' class='btn btn-sm btn-default'>수정</a> ";
        $html .= "<a href='/admin/block-row?page_id={$id}' class='btn btn-sm btn-default'>행 관리</a> ";

        if ($rowCount === 0) {
            $html .= "<button type='button' class='btn btn-sm btn-default' onclick=\"deletePage({$id}, '{$name}')\">삭제</button>";
        } else {
            $html .= "<button type='button' class='btn btn-sm btn-default' disabled title='연결된 행이 있어 삭제 불가'>삭제</button>";
        }

        return $html;
    }, ['_th_attr' => ['style' => 'width:180px']])
    ->build();
?>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '블록 페이지 관리') ?></h3>
                <p class="text-muted mb-0">블록으로 구성된 개별 페이지를 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/block-page/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>페이지 추가
                </a>
            </div>
        </div>
    </div>

    <!-- 요약 영역 -->
    <div class="mt-4 mb-2">
        <span class="ov">
            <span class="ov-txt"><a href="/admin/block-page">전체</a></span>
            <span class="ov-num"><b><?= count($pages) ?></b> 개</span>
        </span>
    </div>

    <!-- 페이지 목록 폼 -->
    <form name="flist" id="flist">
        <div class="table-responsive">
            <?= $this->listRenderHelper
                ->setColumns($columns)
                ->setRows($pages)
                ->setSkin('table/basic')
                ->setWrapAttr(['class' => 'table table-hover align-middle'])
                ->showHeader(true)
                ->render() ?>
        </div>

        <!-- 하단 액션바 -->
        <div class="row gx-2 justify-content-between align-items-center my-2">
            <div class="col-auto">
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-default mublo-submit"
                            data-target="/admin/block-page/list-delete"
                            data-callback="afterListDelete">
                        <i class="d-inline d-md-none bi bi-trash"></i>
                        <span class="d-none d-md-inline">선택 삭제</span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- 페이지네이션 -->
    <?php if ($pagination && $pagination['total_pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?= $i == $pagination['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>

    <!-- 안내 -->
    <div class="card mt-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
            <i class="bi bi-info-circle me-2 text-pastel-sky"></i>블록 페이지 안내
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <li>블록 페이지는 <code>/p/{코드}</code> URL로 접근할 수 있습니다.</li>
                <li>각 페이지에 행을 추가하여 레이아웃을 구성하세요.</li>
                <li>행에는 최대 4개의 칸을 배치할 수 있습니다.</li>
            </ul>
        </div>
    </div>
</div>

<script>
// 전체 선택
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.querySelector('input[name="chk_all"]');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="chk[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = checkAll.checked;
            });
        });
    }
});

// 페이지 삭제 (단건)
function deletePage(pageId, pageName) {
    MubloRequest.showConfirm(`'${pageName}' 페이지를 삭제하시겠습니까?`, function() {
        MubloRequest.requestJson('/admin/block-page/delete', {
            page_id: pageId
        }).then(response => {
            MubloRequest.showToast(response.message || '삭제되었습니다.', 'success');
            location.reload();
        }).catch(err => {
            console.error(err);
        });
    }, { type: 'warning' });
}

// 일괄 삭제 후 콜백
function afterListDelete(data) {
    if (data.result === 'success') {
        MubloRequest.showToast(data.message || '삭제되었습니다.', 'success');
        location.reload();
    } else {
        MubloRequest.showAlert(data.message || '삭제에 실패했습니다.', 'error');
    }
}
</script>
