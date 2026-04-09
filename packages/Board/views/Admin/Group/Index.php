<?php
/**
 * Admin Boardgroup - Index
 *
 * 게시판 그룹 목록
 *
 * View Context 접근:
 * - $this->columns() : ListColumnBuilder 팩토리
 * - $this->listRenderHelper : ListRenderHelper 인스턴스
 *
 * @var string $pageTitle 페이지 제목
 * @var array $groups 그룹 목록
 * @var array $levelOptions 권한 레벨 옵션 [value => label]
 */

// 컬럼 정의
$columns = $this->columns()
    ->checkbox('chk', '', ['id_key' => 'group_id', '_th_attr' => ['style' => 'width:40px']])
    ->add('group_id', 'ID', [
        'render' => fn($row) => '<small class="text-muted">#' . $row['group_id'] . '</small>',
        '_th_attr' => ['style' => 'width:60px']
    ])
    ->add('group_slug', '슬러그', [
        'render' => fn($row) => '<code>' . htmlspecialchars($row['group_slug']) . '</code>'
    ])
    ->add('group_name', '그룹명', [
        'render' => function($row) {
            $html = '<strong>' . htmlspecialchars($row['group_name']) . '</strong>';
            if (!empty($row['group_description'])) {
                $html .= '<br><small class="text-muted">' . htmlspecialchars($row['group_description']) . '</small>';
            }
            return $html;
        }
    ])
    ->add('board_count', '게시판', [
        'render' => fn($row) => '<span class="badge bg-secondary">' . ($row['board_count'] ?? 0) . '</span>',
        '_th_attr' => ['style' => 'width:70px', 'class' => 'text-center'],
        '_td_attr' => ['class' => 'text-center']
    ])
    ->select('list_level', '목록', $levelOptions, [
        'id_key' => 'group_id',
        '_th_attr' => ['style' => 'width:120px']
    ])
    ->select('read_level', '읽기', $levelOptions, [
        'id_key' => 'group_id',
        '_th_attr' => ['style' => 'width:120px']
    ])
    ->select('write_level', '쓰기', $levelOptions, [
        'id_key' => 'group_id',
        '_th_attr' => ['style' => 'width:120px']
    ])
    ->select('comment_level', '댓글', $levelOptions, [
        'id_key' => 'group_id',
        '_th_attr' => ['style' => 'width:120px']
    ])
    ->select('download_level', '다운', $levelOptions, [
        'id_key' => 'group_id',
        '_th_attr' => ['style' => 'width:120px']
    ])
    ->select('is_active', '상태', [
        1 => '사용',
        0 => '미사용',
    ], ['id_key' => 'group_id', '_th_attr' => ['style' => 'width:100px']])
    ->actions('actions', '관리', function($row) {
        $id = $row['group_id'];
        $name = htmlspecialchars($row['group_name'], ENT_QUOTES);
        $boardCount = $row['board_count'] ?? 0;

        $html = "<a href='/admin/board/group/edit?id={$id}' class='btn btn-sm btn-default'>수정</a> ";

        if ($boardCount === 0) {
            $html .= "<button type='button' class='btn btn-sm btn-default' onclick=\"deleteGroup({$id}, '{$name}')\">삭제</button>";
        } else {
            $html .= "<button type='button' class='btn btn-sm btn-default' disabled title='게시판이 있어 삭제 불가'>삭제</button>";
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
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '게시판 그룹 관리') ?></h3>
                <p class="text-muted mb-0">게시판을 분류하는 그룹을 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/board/group/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>그룹 추가
                </a>
            </div>
        </div>
    </div>

    <!-- 요약 영역 -->
    <div class="mt-4 mb-2">
        <span class="ov">
            <span class="ov-txt"><a href="/admin/board/group">전체</a></span>
            <span class="ov-num"><b><?= count($groups) ?></b> 개</span>
        </span>
    </div>

    <!-- 그룹 목록 폼 -->
    <form name="flist" id="flist">
        <div class="table-responsive">
            <?= $this->listRenderHelper
                ->setColumns($columns)
                ->setRows($groups)
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
                            data-target="/admin/board/group/list-update"
                            data-callback="afterListUpdate">
                        <i class="d-inline d-md-none bi bi-pencil-square"></i>
                        <span class="d-none d-md-inline">선택 수정</span>
                    </button>
                    <button type="button" class="btn btn-default mublo-submit"
                            data-target="/admin/board/group/list-delete"
                            data-callback="afterListDelete">
                        <i class="d-inline d-md-none bi bi-trash"></i>
                        <span class="d-none d-md-inline">선택 삭제</span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- 레벨 설명 -->
    <div class="card mt-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
            <i class="bi bi-question-circle me-2 text-pastel-blue"></i>권한 레벨 안내
        </div>
        <div class="card-body">
            <ul class="list-inline mb-2">
                <?php foreach ($levelOptions as $value => $label): ?>
                <li class="list-inline-item me-3">
                    <span class="badge bg-secondary">Lv.<?= $value ?></span>
                    <?= htmlspecialchars($label) ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <small class="text-muted">* 게시판별로 그룹 권한을 오버라이드할 수 있습니다.</small>
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

// 그룹 삭제 (단건)
function deleteGroup(groupId, groupName) {
    if (!confirm(`'${groupName}' 그룹을 삭제하시겠습니까?`)) {
        return;
    }

    MubloRequest.requestJson('/admin/board/group/delete', {
        group_id: groupId
    }).then(response => {
        if (response.result === 'success') {
            alert(response.message || '그룹이 삭제되었습니다.');
            location.reload();
        } else {
            alert(response.message || '삭제에 실패했습니다.');
        }
    }).catch(err => {
        alert('오류가 발생했습니다.');
        console.error(err);
    });
}

// 일괄 수정 후 콜백
function afterListUpdate(data) {
    if (data.result === 'success') {
        alert(data.message || '수정되었습니다.');
        location.reload();
    } else {
        alert(data.message || '수정에 실패했습니다.');
    }
}

// 일괄 삭제 후 콜백
function afterListDelete(data) {
    if (data.result === 'success') {
        alert(data.message || '삭제되었습니다.');
        location.reload();
    } else {
        alert(data.message || '삭제에 실패했습니다.');
    }
}
</script>
