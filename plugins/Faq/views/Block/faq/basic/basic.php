<?php
/**
 * Block Skin: faq/basic
 *
 * FAQ 블록 기본 스킨 (아코디언) — 자체 CSS, 부트스트랩 미사용
 *
 * @var array $titleConfig 타이틀 설정
 * @var string $titlePartial 타이틀 파셜 경로
 * @var array $contentConfig 콘텐츠 설정
 * @var \Mublo\Entity\Block\BlockColumn $column 블록 칸 엔티티
 * @var string $skinDir 스킨 디렉토리 경로
 * @var \Mublo\Core\Rendering\AssetManager|null $assets 에셋 매니저
 * @var array $grouped FAQ 그룹 배열
 * @var array $config content_config
 */

$grouped = $grouped ?? [];
$config = $config ?? [];
$showCategory = (bool) ($config['show_category'] ?? true);
$blockId = 'block_faq_' . $column->getColumnId();
?>
<style>
.block-faq--basic .bfaq-category {
    font-size: 14px; font-weight: 700; color: #222;
    margin: 16px 0 10px; padding-bottom: 6px;
    border-bottom: 2px solid #3071ff; display: inline-block;
}
.block-faq--basic .bfaq-category:first-child { margin-top: 0; }

.block-faq--basic .bfaq-items { display: flex; flex-direction: column; gap: 8px; margin-bottom: 12px; }

.block-faq--basic .bfaq-item {
    border: 1px solid #f0f0f0; border-radius: 10px;
    overflow: hidden; box-shadow: 0 1px 2px rgba(0,0,0,0.03);
    transition: box-shadow 0.2s;
}
.block-faq--basic .bfaq-item:hover { box-shadow: 0 3px 8px rgba(0,0,0,0.06); }

.block-faq--basic .bfaq-question {
    width: 100%; padding: 12px 14px; display: flex; align-items: center;
    justify-content: space-between; background: #fff; border: 0;
    cursor: pointer; transition: background 0.15s; text-align: left;
    font-family: inherit;
}
.block-faq--basic .bfaq-question:hover { background: #fafbfc; }
.block-faq--basic .bfaq-question-inner { display: flex; align-items: flex-start; gap: 8px; flex: 1; }
.block-faq--basic .bfaq-q-mark { font-size: 13px; font-weight: 700; color: #3071ff; flex-shrink: 0; }
.block-faq--basic .bfaq-q-text { font-size: 13px; font-weight: 600; color: #222; }

.block-faq--basic .bfaq-chevron {
    width: 16px; height: 16px; color: #aaa; flex-shrink: 0;
    margin-left: 8px; transition: transform 0.3s;
}
.block-faq--basic .bfaq-item--open .bfaq-chevron { transform: rotate(180deg); }

.block-faq--basic .bfaq-answer {
    max-height: 0; overflow: hidden;
    transition: max-height 0.3s ease-out;
}
.block-faq--basic .bfaq-item--open .bfaq-answer {
    max-height: 500px; transition: max-height 0.4s ease-in;
}

.block-faq--basic .bfaq-answer-inner { padding: 0 14px 12px; background: #fafbfc; }
.block-faq--basic .bfaq-answer-content { display: flex; align-items: flex-start; gap: 8px; }
.block-faq--basic .bfaq-a-mark { font-size: 13px; font-weight: 700; color: #7f8894; flex-shrink: 0; }
.block-faq--basic .bfaq-a-text { font-size: 12px; color: #555; line-height: 1.7; }
.block-faq--basic .bfaq-a-text p { margin: 0; }
.block-faq--basic .bfaq-a-text img { max-width: 100%; height: auto; border-radius: 6px; margin: 6px 0; }
</style>

<div class="block-faq block-faq--basic">
    <?php include $titlePartial; ?>

    <div class="block-faq__content">
        <?php if (empty($grouped)): ?>
        <div class="block-empty">
            <p>등록된 FAQ가 없습니다.</p>
        </div>
        <?php else: ?>
            <?php foreach ($grouped as $gIdx => $group): ?>
                <?php if ($showCategory && count($grouped) > 1): ?>
                    <div class="bfaq-category"><?= htmlspecialchars($group['category_name']) ?></div>
                <?php endif; ?>

                <div class="bfaq-items">
                    <?php foreach ($group['items'] as $idx => $item): ?>
                        <div class="bfaq-item" data-faq-id="<?= $item['faq_id'] ?>">
                            <button type="button" class="bfaq-question" onclick="this.closest('.bfaq-item').classList.toggle('bfaq-item--open')">
                                <div class="bfaq-question-inner">
                                    <span class="bfaq-q-mark">Q.</span>
                                    <span class="bfaq-q-text"><?= htmlspecialchars($item['question']) ?></span>
                                </div>
                                <svg class="bfaq-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="m6 9 6 6 6-6"></path>
                                </svg>
                            </button>
                            <div class="bfaq-answer">
                                <div class="bfaq-answer-inner">
                                    <div class="bfaq-answer-content">
                                        <span class="bfaq-a-mark">A.</span>
                                        <div class="bfaq-a-text"><?= $item['answer'] ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
