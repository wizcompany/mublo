<?php
/**
 * 상품 목록
 *
 * View Context 접근:
 * - $this->columns() : ListColumnBuilder 팩토리
 * - $this->listRenderHelper : ListRenderHelper 인스턴스
 * - $this->pagination($data) : 페이지네이션 렌더링
 * - $this->component($name, $data) : 컴포넌트 렌더링
 *
 * @var string $pageTitle 페이지 제목
 * @var array $products 상품 목록
 * @var array $pagination 페이지네이션
 * @var array $categories 카테고리 트리
 * @var array $filters 현재 필터
 */

// 컬럼 정의
$columns = $this->columns()
    ->checkbox('chk', '', ['id_key' => 'goods_id', '_th_attr' => ['style' => 'width:40px']])
    ->add('goods_id', 'No.', ['sortable' => true, '_th_attr' => ['style' => 'width:60px']])
    ->callback('goods_name', '상품명', function ($row) {
        $id = $row['goods_id'];
        $name = htmlspecialchars($row['goods_name'] ?? '');
        $html = "<a href='/admin/shop/products/{$id}/edit'>{$name}</a>";
        if (!empty($row['goods_badge'])) {
            $badge = htmlspecialchars($row['goods_badge']);
            $html .= " <span class='badge bg-info'>{$badge}</span>";
        }
        return $html;
    })
    ->add('category_code', '카테고리')
    ->callback('display_price', '판매가', function ($row) {
        return number_format($row['display_price'] ?? 0) . '원';
    }, ['_th_attr' => ['class' => 'text-end'], '_td_attr' => ['class' => 'text-end']])
    ->callback('stock_quantity', '재고', function ($row) {
        return number_format($row['stock_quantity'] ?? 0);
    }, ['_th_attr' => ['class' => 'text-center'], '_td_attr' => ['class' => 'text-center']])
    ->callback('is_active', '상태', function ($row) {
        if ($row['is_active']) {
            return "<span class='badge bg-success'>판매중</span>";
        }
        return "<span class='badge bg-secondary'>중지</span>";
    }, ['_th_attr' => ['class' => 'text-center'], '_td_attr' => ['class' => 'text-center']])
    ->callback('hit', '조회', function ($row) {
        return number_format($row['hit'] ?? 0);
    }, ['_th_attr' => ['class' => 'text-center'], '_td_attr' => ['class' => 'text-center']])
    ->actions('actions', '관리', function ($row) {
        $id = $row['goods_id'];
        return "
            <a href='/admin/shop/products/{$id}/edit' class='btn btn-sm btn-default'>수정</a>
            <button type='button' class='btn btn-sm btn-default' onclick='deleteProduct({$id})'>삭제</button>
        ";
    })
    ->build();
?>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '상품 관리') ?></h3>
                <p class="text-muted mb-0">쇼핑몰 상품을 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/shop/products/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>상품 등록
                </a>
            </div>
        </div>
    </div>

    <!-- 검색 영역 -->
    <form method="get" name="fsearch" id="fsearch" class="mt-4 mb-2">
        <div class="row align-items-center gy-2 gy-xl-0">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/shop/products">전체</a></span>
                    <span class="ov-num"><b><?= number_format($pagination['totalItems'] ?? 0) ?></b> 건</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2">
                    <div class="col col-xl-auto">
                        <select name="category_code" class="form-select">
                            <option value="">전체 카테고리</option>
                            <?php foreach ($categories ?? [] as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['category_code']) ?>"
                                    <?= ($filters['category_code'] ?? '') === $cat['category_code'] ? 'selected' : '' ?>>
                                <?= str_repeat('─ ', ($cat['depth'] ?? 1) - 1) ?><?= htmlspecialchars($cat['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col col-xl-auto">
                        <select name="is_active" class="form-select">
                            <option value="">전체 상태</option>
                            <option value="1" <?= ($filters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>판매중</option>
                            <option value="0" <?= ($filters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>판매중지</option>
                        </select>
                    </div>
                    <div class="col col-xl-auto">
                        <div class="search-wrapper">
                            <label for="search_keyword" class="visually-hidden">검색</label>
                            <input type="text" name="keyword" id="search_keyword" class="form-control"
                                   placeholder="상품명 검색"
                                   value="<?= htmlspecialchars($filters['keyword'] ?? '') ?>">
                            <i class="bi bi-search search-icon"></i>
                            <?php if (!empty($filters['keyword'])): ?>
                            <i class="bi bi-x-lg search-reset-icon" onclick="location.href='/admin/shop/products'"></i>
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

    <!-- 상품 목록 폼 -->
    <form name="flist" id="flist">
        <div class="table-responsive">
            <?= $this->listRenderHelper
                ->setColumns($columns)
                ->setRows($products)
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
                        data-target="/admin/shop/products/listDelete"
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

// 상품 삭제 (단건)
function deleteProduct(goodsId) {
    if (!confirm('정말 이 상품을 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.')) {
        return;
    }

    MubloRequest.requestJson('/admin/shop/products/' + goodsId + '/delete', {}, { method: 'POST', loading: true })
        .then(function(data) {
            if (data.result === 'success') {
                location.reload();
            } else {
                alert(data.message || '삭제에 실패했습니다.');
            }
        })
        .catch(function(error) {
            console.error('Error:', error);
            alert('삭제 중 오류가 발생했습니다.');
        });
}

// 일괄 삭제 후 콜백
function afterBulkDelete(data) {
    if (data.result === 'success') {
        alert(data.message || '삭제되었습니다.');
        location.reload();
    } else {
        alert(data.message || '삭제에 실패했습니다.');
    }
}
</script>
