<?php
/**
 * Admin Member - Index
 *
 * 회원 목록
 *
 * View Context 접근:
 * - $this->columns() : ListColumnBuilder 팩토리
 * - $this->listRenderHelper : ListRenderHelper 인스턴스
 * - $this->pagination($data) : 페이지네이션 렌더링
 * - $this->component($name, $data) : 컴포넌트 렌더링
 *
 * @var string $pageTitle 페이지 제목
 * @var array $members 회원 목록 데이터
 * @var array $listFields 추가 필드 정보
 * @var array $pagination 페이지네이션 정보
 * @var array $searchFields 검색 필드 옵션
 * @var array $levelOptions 등급 선택 옵션 [level_value => level_name]
 * @var array $currentSearch 현재 검색 조건
 */

// 컬럼 정의 (View에서 직접 정의)
$columnBuilder = $this->columns()
    ->checkbox('chk', '', ['id_key' => 'member_id', '_th_attr' => ['style' => 'width:40px']])
    ->add('member_id', 'No.', ['sortable' => true, '_th_attr' => ['style' => 'width:60px']])
    ->add('user_id', '아이디', ['sortable' => true])
    ->select('level_value', '등급', $levelOptions, ['id_key' => 'member_id'])
    ->select('status', '상태', [
        'active' => '활성',
        'inactive' => '비활성',
        'pending' => '대기',
        'blocked' => '차단',
    ], ['id_key' => 'member_id']);

// 추가 필드 컬럼 동적 추가
foreach ($listFields as $field) {
    $fieldKey = 'field_' . $field['field_name'];
    $fieldLabel = $field['field_label'];
    // 암호화 필드는 🔒 표시
    if (!empty($field['is_encrypted'])) {
        $fieldLabel = "🔒 {$fieldLabel}";
    }

    if ($field['field_type'] === 'file') {
        $columnBuilder->add($fieldKey, $fieldLabel, [
            'render' => function ($row) use ($fieldKey) {
                $val = $row[$fieldKey] ?? '';
                if (empty($val) || !is_array($val) || ($val['_type'] ?? '') !== 'file') {
                    return '-';
                }
                $filename = htmlspecialchars($val['filename'] ?? '', ENT_QUOTES);
                $url = htmlspecialchars($val['download_url'] ?? '', ENT_QUOTES);
                if (!$url) {
                    return $filename ?: '-';
                }
                return "<a href=\"{$url}\" title=\"{$filename}\" class=\"text-decoration-none\"><i class=\"bi bi-file-earmark-arrow-down\"></i> {$filename}</a>";
            },
        ]);
    } else {
        $columnBuilder->add($fieldKey, $fieldLabel);
    }
}

// 기본 컬럼 계속
$columns = $columnBuilder
    ->add('point', '포인트', [
        'render' => fn($row) => number_format($row['point'] ?? 0) . ' P',
        '_th_attr' => ['style' => 'width:100px; text-align:right'],
        '_td_attr' => ['style' => 'text-align:right'],
    ])
    ->add('created_at', '가입일', ['sortable' => true])
    ->add('last_login_at', '최종로그인')
    ->actions('actions', '관리', function ($row) {
        $id = $row['member_id'];
        return "
            <a href='/admin/member/edit/{$id}' class='btn btn-sm btn-default'>수정</a>
            <button type='button' class='btn btn-sm btn-default' onclick='deleteMember({$id})'>삭제</button>
        ";
    })
    ->build();
?>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '회원 관리') ?></h3>
                <p class="text-muted mb-0">사이트 회원을 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/member/create" class="btn btn-primary">
                    <i class="bi bi-person-plus me-1"></i>회원 등록
                </a>
            </div>
        </div>
    </div>

    <!-- 검색 영역 -->
    <form method="get" name="fsearch" id="fsearch" class="mt-4 mb-2">
        <div class="row align-items-center gy-2 gy-xl-0">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/member">전체</a></span>
                    <span class="ov-num"><b><?= number_format($pagination['totalItems'] ?? 0) ?></b> 명</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2">
                    <div class="col col-xl-auto">
                        <select name="search_field" class="form-select">
                            <option value="">검색 필드</option>
                            <?php foreach ($searchFields as $key => $label): ?>
                            <option value="<?= $key ?>" <?= ($currentSearch['field'] ?? '') === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col col-xl-auto">
                        <div class="search-wrapper">
                            <label for="search_keyword" class="visually-hidden">검색</label>
                            <input type="text" name="search_keyword" id="search_keyword" class="form-control"
                                   placeholder="검색어 입력"
                                   value="<?= htmlspecialchars($currentSearch['keyword'] ?? '') ?>">
                            <i class="bi bi-search search-icon"></i>
                            <?php if (!empty($currentSearch['keyword'])): ?>
                            <i class="bi bi-x-lg search-reset-icon" onclick="location.href='/admin/member'"></i>
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

    <!-- 회원 목록 폼 -->
    <form name="flist" id="flist">
        <!-- 회원 목록 테이블 -->
        <div class="table-responsive">
            <?= $this->listRenderHelper
                ->setColumns($columns)
                ->setRows($members)
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
                        data-target="/admin/member/listModify"
                        data-callback="afterBulkUpdate"
                    >
                        <i class="d-inline d-md-none bi bi-pencil-square"></i>
                        <span class="d-none d-md-inline">선택 수정</span>
                    </button>
                    <button
                        type="button"
                        class="btn btn-default mublo-submit"
                        data-target="/admin/member/listDelete"
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

// 회원 삭제 (단건)
function deleteMember(memberId) {
    MubloRequest.showConfirm('정말 이 회원을 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.', function() {
        MubloRequest.requestJson('/admin/member/delete/' + memberId, {}, { method: 'DELETE', loading: true })
            .then(function(data) {
                location.reload();
            })
            .catch(function(error) {
                console.error('Error:', error);
                MubloRequest.showAlert('삭제 중 오류가 발생했습니다.', 'error');
            });
    }, { type: 'warning' });
}

// 일괄 수정 후 콜백
function afterBulkUpdate(data) {
    MubloRequest.showToast(data.message || '수정되었습니다.', 'success');
    location.reload();
}

// 일괄 삭제 후 콜백
function afterBulkDelete(data) {
    MubloRequest.showToast(data.message || '삭제되었습니다.', 'success');
    location.reload();
}
</script>
