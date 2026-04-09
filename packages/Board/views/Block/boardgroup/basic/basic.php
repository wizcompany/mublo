<?php
/**
 * Block Skin: boardgroup/basic
 *
 * 게시판 그룹 기본 스킨 (탭 형식으로 여러 게시판 표시)
 *
 * MubloItemLayout 비적용:
 *   탭 전환 구조 — 각 게시판 패널이 독립적이므로 레이아웃 모듈 불필요.
 *
 * @var array $titleConfig 타이틀 설정
 * @var string $titlePartial 타이틀 파셜 경로
 * @var array $contentConfig 콘텐츠 설정
 * @var \Mublo\Entity\Block\BlockColumn $column 블록 칸 엔티티
 * @var \Mublo\Entity\Board\BoardGroup|null $group 게시판 그룹
 * @var array $boardsData 게시판별 데이터 [{board, articles}, ...]
 * @var string $displayType tab|list|grid
 */

$showDate = $contentConfig['show_date'] ?? true;
$uniqueId = 'boardgroup_' . uniqid();
?>
<div class="block-boardgroup block-boardgroup-basic">
    <?php include $titlePartial; ?>

    <div class="block-content">
        <?php if (empty($boardsData)): ?>
        <p class="block-empty">게시판이 설정되지 않았습니다.</p>
        <?php else: ?>
        <!-- 탭 헤더 -->
        <ul class="nav nav-tabs" role="tablist">
            <?php foreach ($boardsData as $idx => $data): ?>
            <?php $board = $data['board']; ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $idx === 0 ? 'active' : '' ?>"
                        id="<?= $uniqueId ?>-tab-<?= $idx ?>"
                        data-bs-toggle="tab"
                        data-bs-target="#<?= $uniqueId ?>-pane-<?= $idx ?>"
                        type="button"
                        role="tab">
                    <?= htmlspecialchars($board->getBoardName()) ?>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>

        <!-- 탭 콘텐츠 -->
        <div class="tab-content">
            <?php foreach ($boardsData as $idx => $data): ?>
            <?php $articles = $data['articles']; ?>
            <div class="tab-pane fade <?= $idx === 0 ? 'show active' : '' ?>"
                 id="<?= $uniqueId ?>-pane-<?= $idx ?>"
                 role="tabpanel">
                <?php if (empty($articles)): ?>
                <p class="text-muted py-3 text-center">등록된 글이 없습니다.</p>
                <?php else: ?>
                <ul class="boardgroup-list">
                    <?php foreach ($articles as $article): ?>
                    <li class="boardgroup-item">
                        <a href="<?= htmlspecialchars($article['url']) ?>">
                            <span class="item-title">
                                <?php if (!empty($article['is_new'])): ?>
                                <span class="boardgroup-badge-new">N</span>
                                <?php endif; ?>
                                <?= $article['title_safe'] ?>
                            </span>
                            <?php if ($showDate && !empty($article['date_short'])): ?>
                            <span class="item-date"><?= $article['date_short'] ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
