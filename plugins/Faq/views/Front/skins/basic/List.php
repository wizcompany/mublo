<?php
/**
 * FAQ 프론트 스킨: basic
 *
 * 모던 아코디언 UI (라운드 카드 + 부드러운 전환)
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
.faq-page { max-width: 900px; margin: 0 auto; padding: 30px 16px 50px; }
@media (min-width: 768px) { .faq-page { padding: 50px 20px 80px; } }

/* 헤더 */
.faq-page__header { text-align: center; margin-bottom: 30px; }
@media (min-width: 768px) { .faq-page__header { margin-bottom: 50px; } }
.faq-page__title-wrap { display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 12px; }
@media (min-width: 768px) { .faq-page__title-wrap { margin-bottom: 16px; } }
.faq-page__icon { width: 28px; height: 28px; color: #3071ff; }
@media (min-width: 768px) { .faq-page__icon { width: 36px; height: 36px; } }
.faq-page__title { font-size: 22px; font-weight: 700; color: #222; margin: 0; }
@media (min-width: 768px) { .faq-page__title { font-size: 32px; } }
.faq-page__subtitle { font-size: 13px; color: #7f8894; margin: 0; }
@media (min-width: 768px) { .faq-page__subtitle { font-size: 16px; } }

/* 카테고리 필터 */
.faq-filter { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; margin-bottom: 24px; }
@media (min-width: 768px) { .faq-filter { gap: 10px; margin-bottom: 32px; } }
.faq-filter__btn {
    display: inline-flex; align-items: center; justify-content: center;
    border: 0; padding: 8px 14px; border-radius: 9999px;
    font-size: 12px; font-weight: 500; cursor: pointer;
    transition: all 0.2s; text-decoration: none;
    background: #f5f5f5; color: #666;
}
@media (min-width: 768px) { .faq-filter__btn { padding: 10px 20px; font-size: 14px; } }
.faq-filter__btn:hover { background: #e8e8e8; color: #444; text-decoration: none; }
.faq-filter__btn--active { background: #3071ff !important; color: #fff !important; }
.faq-filter__btn--active:hover { background: #2560e0 !important; }

/* 카테고리 헤더 */
.faq-category-title {
    font-size: 16px; font-weight: 700; color: #222;
    margin: 32px 0 16px; padding-bottom: 8px;
    border-bottom: 2px solid #3071ff; display: inline-block;
}
@media (min-width: 768px) { .faq-category-title { font-size: 18px; margin: 40px 0 20px; } }

/* FAQ 아이템 */
.faq-items { display: flex; flex-direction: column; gap: 12px; }
@media (min-width: 768px) { .faq-items { gap: 16px; } }
.faq-item {
    border: 1px solid #f0f0f0; border-radius: 12px;
    overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    transition: box-shadow 0.2s;
}
@media (min-width: 768px) { .faq-item { border-radius: 16px; } }
.faq-item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }

.faq-item__question {
    width: 100%; padding: 16px; display: flex; align-items: center;
    justify-content: space-between; background: #fff; border: 0;
    cursor: pointer; transition: background 0.15s; text-align: left;
}
@media (min-width: 768px) { .faq-item__question { padding: 20px 24px; } }
.faq-item__question:hover { background: #fafbfc; }
.faq-item__question-inner { display: flex; align-items: flex-start; gap: 10px; flex: 1; }
@media (min-width: 768px) { .faq-item__question-inner { gap: 14px; } }
.faq-item__q-mark { font-size: 14px; font-weight: 700; color: #3071ff; flex-shrink: 0; }
@media (min-width: 768px) { .faq-item__q-mark { font-size: 16px; } }
.faq-item__q-text { font-size: 13px; font-weight: 600; color: #222; }
@media (min-width: 768px) { .faq-item__q-text { font-size: 15px; } }

.faq-item__chevron {
    width: 18px; height: 18px; color: #aaa; flex-shrink: 0;
    margin-left: 12px; transition: transform 0.3s;
}
@media (min-width: 768px) { .faq-item__chevron { width: 22px; height: 22px; } }
.faq-item--open .faq-item__chevron { transform: rotate(180deg); }

.faq-item__answer {
    max-height: 0; overflow: hidden;
    transition: max-height 0.3s ease-out;
}
.faq-item--open .faq-item__answer { max-height: 500px; transition: max-height 0.4s ease-in; }

.faq-item__answer-inner { padding: 0 16px 16px; background: #fafbfc; }
@media (min-width: 768px) { .faq-item__answer-inner { padding: 0 24px 20px; } }
.faq-item__answer-content { display: flex; align-items: flex-start; gap: 10px; }
@media (min-width: 768px) { .faq-item__answer-content { gap: 14px; } }
.faq-item__a-mark { font-size: 14px; font-weight: 700; color: #7f8894; flex-shrink: 0; }
@media (min-width: 768px) { .faq-item__a-mark { font-size: 16px; } }
.faq-item__a-text { font-size: 12px; color: #555; line-height: 1.8; }
.faq-item__a-text p { margin: 0; }
.faq-item__a-text img { max-width: 100%; height: auto; border-radius: 8px; margin: 8px 0; }
@media (min-width: 768px) { .faq-item__a-text { font-size: 14px; } }

/* 빈 상태 */
.faq-empty { text-align: center; padding: 60px 20px; color: #7f8894; font-size: 15px; }
</style>

<div class="faq-page">
    <!-- 헤더 -->
    <div class="faq-page__header">
        <div class="faq-page__title-wrap">
            <svg class="faq-page__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path>
                <path d="M12 17h.01"></path>
            </svg>
            <h1 class="faq-page__title"><?= htmlspecialchars($pageTitle) ?></h1>
        </div>
        <p class="faq-page__subtitle">궁금한 점을 확인해보세요</p>
    </div>

    <!-- 카테고리 필터 -->
    <?php if (!empty($categories)): ?>
    <div class="faq-filter">
        <a href="/faq" class="faq-filter__btn <?= $activeSlug === null ? 'faq-filter__btn--active' : '' ?>">전체</a>
        <?php foreach ($categories as $cat): ?>
            <a href="/faq/<?= htmlspecialchars($cat['category_slug']) ?>"
               class="faq-filter__btn <?= $activeSlug === $cat['category_slug'] ? 'faq-filter__btn--active' : '' ?>">
                <?= htmlspecialchars($cat['category_name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- FAQ 목록 -->
    <?php if (empty($grouped)): ?>
        <div class="faq-empty">등록된 FAQ가 없습니다.</div>
    <?php else: ?>
        <?php foreach ($grouped as $group): ?>
            <?php if ($activeSlug === null && count($grouped) > 1): ?>
                <div class="faq-category-title"><?= htmlspecialchars($group['category_name']) ?></div>
            <?php endif; ?>

            <div class="faq-items">
                <?php foreach ($group['items'] as $item): ?>
                    <div class="faq-item" data-faq-id="<?= $item['faq_id'] ?>">
                        <button type="button" class="faq-item__question" onclick="this.closest('.faq-item').classList.toggle('faq-item--open')">
                            <div class="faq-item__question-inner">
                                <span class="faq-item__q-mark">Q.</span>
                                <span class="faq-item__q-text"><?= htmlspecialchars($item['question']) ?></span>
                            </div>
                            <svg class="faq-item__chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m6 9 6 6 6-6"></path>
                            </svg>
                        </button>
                        <div class="faq-item__answer">
                            <div class="faq-item__answer-inner">
                                <div class="faq-item__answer-content">
                                    <span class="faq-item__a-mark">A.</span>
                                    <div class="faq-item__a-text"><?= $item['answer'] ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
