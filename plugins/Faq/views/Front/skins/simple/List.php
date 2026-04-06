<?php
/**
 * FAQ 프론트 스킨: simple
 *
 * 아코디언 없이 Q&A를 직접 나열하는 심플 스킨
 *
 * @var string $pageTitle
 * @var array $categories [{category_id, category_name, category_slug, item_count}, ...]
 * @var array $grouped [{category_name, category_slug, items: [{faq_id, question, answer}]}, ...]
 * @var string|null $activeSlug
 */
$categories = $categories ?? [];
$grouped = $grouped ?? [];
$activeSlug = $activeSlug ?? null;
?>

<style>
.faq-simple { max-width: 900px; margin: 0 auto; padding: 30px 16px 50px; }
@media (min-width: 768px) { .faq-simple { padding: 50px 20px 80px; } }

/* 헤더 */
.faq-simple__header { text-align: center; margin-bottom: 30px; }
@media (min-width: 768px) { .faq-simple__header { margin-bottom: 50px; } }
.faq-simple__title-wrap { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 12px; }
@media (min-width: 768px) { .faq-simple__title-wrap { margin-bottom: 16px; } }
.faq-simple__icon { width: 28px; height: 28px; color: #3071ff; }
@media (min-width: 768px) { .faq-simple__icon { width: 36px; height: 36px; } }
.faq-simple__title { font-size: 22px; font-weight: 700; color: #222; margin: 0; }
@media (min-width: 768px) { .faq-simple__title { font-size: 32px; } }
.faq-simple__subtitle { font-size: 13px; color: #7f8894; margin: 0; }
@media (min-width: 768px) { .faq-simple__subtitle { font-size: 16px; } }

/* 카테고리 필터 */
.faq-simple-filter { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-bottom: 24px; }
@media (min-width: 768px) { .faq-simple-filter { gap: 10px; margin-bottom: 32px; } }
.faq-simple-filter__btn {
    display: inline-flex; align-items: center; gap: 6px;
    border: 0; padding: 8px 14px; border-radius: 9999px;
    font-size: 12px; font-weight: 500; cursor: pointer;
    transition: all 0.2s; text-decoration: none;
    background: #f5f5f5; color: #666;
}
@media (min-width: 768px) { .faq-simple-filter__btn { padding: 10px 20px; font-size: 14px; } }
.faq-simple-filter__btn:hover { background: #e8e8e8; color: #444; text-decoration: none; }
.faq-simple-filter__btn--active { background: #3071ff !important; color: #fff !important; }
.faq-simple-filter__btn--active:hover { background: #2560e0 !important; }
.faq-simple-filter__count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; padding: 0 6px;
    border-radius: 10px; font-size: 11px; font-weight: 600;
    background: rgba(0,0,0,0.08); color: inherit;
}
.faq-simple-filter__btn--active .faq-simple-filter__count { background: rgba(255,255,255,0.25); }

/* 카테고리 헤더 */
.faq-simple__category {
    font-size: 16px; font-weight: 700; color: #222;
    margin: 32px 0 16px; padding-bottom: 8px;
    border-bottom: 2px solid #3071ff; display: inline-block;
}
@media (min-width: 768px) { .faq-simple__category { font-size: 18px; margin: 40px 0 20px; } }

/* FAQ 아이템 */
.faq-simple-items { display: flex; flex-direction: column; gap: 0; }
.faq-simple-item {
    padding: 20px 0; border-bottom: 1px solid #f0f0f0;
}
.faq-simple-item:first-child { padding-top: 0; }
.faq-simple-item:last-child { border-bottom: none; }
@media (min-width: 768px) { .faq-simple-item { padding: 24px 0; } }

.faq-simple-item__question {
    display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px;
}
@media (min-width: 768px) { .faq-simple-item__question { gap: 14px; margin-bottom: 16px; } }
.faq-simple-item__q-mark {
    font-size: 14px; font-weight: 700; color: #3071ff;
    flex-shrink: 0; line-height: 1.6;
}
@media (min-width: 768px) { .faq-simple-item__q-mark { font-size: 16px; } }
.faq-simple-item__q-text {
    font-size: 14px; font-weight: 600; color: #222; line-height: 1.6;
}
@media (min-width: 768px) { .faq-simple-item__q-text { font-size: 16px; } }

.faq-simple-item__answer {
    display: flex; align-items: flex-start; gap: 10px;
    padding-left: 0; margin-left: 24px;
}
@media (min-width: 768px) { .faq-simple-item__answer { gap: 14px; margin-left: 30px; } }
.faq-simple-item__a-mark {
    font-size: 14px; font-weight: 700; color: #7f8894;
    flex-shrink: 0; line-height: 1.8;
}
@media (min-width: 768px) { .faq-simple-item__a-mark { font-size: 16px; } }
.faq-simple-item__a-text {
    font-size: 13px; color: #555; line-height: 1.8;
}
.faq-simple-item__a-text p { margin: 0; }
.faq-simple-item__a-text img { max-width: 100%; height: auto; border-radius: 8px; margin: 8px 0; }
@media (min-width: 768px) { .faq-simple-item__a-text { font-size: 15px; } }

/* 빈 상태 */
.faq-simple-empty { text-align: center; padding: 60px 20px; color: #7f8894; font-size: 15px; }
</style>

<div class="faq-simple">
    <!-- 헤더 -->
    <div class="faq-simple__header">
        <div class="faq-simple__title-wrap">
            <svg class="faq-simple__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                <path d="M12 17h.01"></path>
            </svg>
            <h1 class="faq-simple__title"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <p class="faq-simple__subtitle">궁금한 점을 확인해보세요</p>
    </div>

    <!-- 카테고리 필터 -->
    <?php if (!empty($categories)): ?>
    <div class="faq-simple-filter">
        <a href="/faq" class="faq-simple-filter__btn <?= $activeSlug === null ? 'faq-simple-filter__btn--active' : '' ?>">전체</a>
        <?php foreach ($categories as $cat): ?>
            <a href="/faq/<?= htmlspecialchars($cat['category_slug']) ?>"
               class="faq-simple-filter__btn <?= $activeSlug === $cat['category_slug'] ? 'faq-simple-filter__btn--active' : '' ?>">
                <?= htmlspecialchars($cat['category_name']) ?>
                <?php if (isset($cat['item_count']) && (int) $cat['item_count'] > 0): ?>
                    <span class="faq-simple-filter__count"><?= $cat['item_count'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- FAQ 목록 -->
    <?php if (empty($grouped)): ?>
        <div class="faq-simple-empty">등록된 FAQ가 없습니다.</div>
    <?php else: ?>
        <?php foreach ($grouped as $group): ?>
            <?php if ($activeSlug === null && count($grouped) > 1): ?>
                <div class="faq-simple__category"><?= htmlspecialchars($group['category_name']) ?></div>
            <?php endif; ?>

            <div class="faq-simple-items">
                <?php foreach ($group['items'] as $item): ?>
                    <div class="faq-simple-item">
                        <div class="faq-simple-item__question">
                            <span class="faq-simple-item__q-mark">Q.</span>
                            <span class="faq-simple-item__q-text"><?= htmlspecialchars($item['question']) ?></span>
                        </div>
                        <div class="faq-simple-item__answer">
                            <span class="faq-simple-item__a-mark">A.</span>
                            <div class="faq-simple-item__a-text"><?= $item['answer'] ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
