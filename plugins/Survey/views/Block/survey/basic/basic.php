<?php
/**
 * Block Skin: survey/basic
 *
 * 설문조사 블록 기본 스킨 — 자체 CSS, Bootstrap 미사용
 *
 * @var \Mublo\Entity\Block\BlockColumn $column
 * @var string $titlePartial
 * @var string $mode        'form' | 'result'
 * @var array  $survey
 * @var array  $questions
 * @var int    $totalResponses
 * @var array  $config
 */

$config       = $config ?? [];
$survey       = $survey ?? [];
$questions    = $questions ?? [];
$mode         = $mode ?? 'form';
$canJoin      = $canJoin ?? true;
$joinMessage  = $joinMessage ?? '';
$showTitle    = !empty($config['show_title']);
$showDesc     = !empty($config['show_desc']);
$surveyId     = (int) ($survey['survey_id'] ?? 0);
$blockId      = 'bsv_' . $column->getColumnId();
$totalQ       = count($questions);
?>
<style>
/* ── Survey Block Basic Skin ─────────────────────────────── */
.block-survey--basic {
    --sv-primary:      #6366f1;
    --sv-primary-dk:   #4f46e5;
    --sv-primary-lt:   #eef2ff;
    --sv-star:         #f59e0b;
    --sv-success:      #10b981;
    --sv-border:       #e5e7eb;
    --sv-bg:           #ffffff;
    --sv-bg-muted:     #f9fafb;
    --sv-text:         #111827;
    --sv-text-muted:   #6b7280;
    --sv-text-sm:      13px;
    --sv-radius:       12px;
    --sv-radius-sm:    8px;
    font-family: inherit;
}

/* ── 래퍼 ─────────────────────────────────────────────────── */
.block-survey--basic .bsv-wrap {
    background: var(--sv-bg);
    border: 1px solid var(--sv-border);
    border-radius: var(--sv-radius);
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.04);
}

/* ── 헤더 ─────────────────────────────────────────────────── */
.block-survey--basic .bsv-header {
    padding: 20px 24px 16px;
    border-bottom: 1px solid var(--sv-border);
    background: linear-gradient(135deg, var(--sv-primary-lt) 0%, #fff 100%);
}
.block-survey--basic .bsv-header-icon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 36px; height: 36px; border-radius: 10px;
    background: var(--sv-primary); color: #fff;
    margin-bottom: 10px;
}
.block-survey--basic .bsv-header-icon svg { width: 18px; height: 18px; }
.block-survey--basic .bsv-title {
    font-size: 16px; font-weight: 700; color: var(--sv-text);
    margin: 0 0 4px; line-height: 1.4;
}
.block-survey--basic .bsv-desc {
    font-size: var(--sv-text-sm); color: var(--sv-text-muted); margin: 0;
    line-height: 1.6;
}

/* ── 질문 목록 ──────────────────────────────────────────────── */
.block-survey--basic .bsv-body { padding: 0; }

.block-survey--basic .bsv-question {
    padding: 20px 24px;
    border-bottom: 1px solid var(--sv-border);
    transition: background .15s;
}
.block-survey--basic .bsv-question:last-child { border-bottom: none; }

.block-survey--basic .bsv-q-header {
    display: flex; align-items: flex-start; gap: 10px; margin-bottom: 14px;
}
.block-survey--basic .bsv-q-num {
    flex-shrink: 0;
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 24px; height: 24px; padding: 0 7px;
    background: var(--sv-primary); color: #fff;
    font-size: 11px; font-weight: 700; border-radius: 6px;
    margin-top: 1px;
}
.block-survey--basic .bsv-q-title {
    font-size: 14px; font-weight: 600; color: var(--sv-text); line-height: 1.5;
    flex: 1;
}
.block-survey--basic .bsv-required {
    color: #ef4444; font-size: 13px; margin-left: 2px;
}
.block-survey--basic .bsv-q-hint {
    font-size: 12px; color: var(--sv-text-muted); margin: -8px 0 10px 34px;
    line-height: 1.5;
}

/* ── 라디오 / 체크박스 ──────────────────────────────────────── */
.block-survey--basic .bsv-choices { display: flex; flex-direction: column; gap: 8px; padding-left: 34px; }

.block-survey--basic .bsv-choice {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px; border-radius: var(--sv-radius-sm);
    border: 1.5px solid var(--sv-border);
    background: var(--sv-bg); cursor: pointer;
    transition: border-color .15s, background .15s;
    font-size: var(--sv-text-sm); color: var(--sv-text);
    user-select: none;
}
.block-survey--basic .bsv-choice:hover {
    border-color: var(--sv-primary); background: var(--sv-primary-lt);
}
.block-survey--basic .bsv-choice input[type="radio"],
.block-survey--basic .bsv-choice input[type="checkbox"] { display: none; }

.block-survey--basic .bsv-choice-dot {
    width: 16px; height: 16px; border-radius: 50%; flex-shrink: 0;
    border: 2px solid #d1d5db; background: #fff;
    transition: border-color .15s, background .15s;
    display: flex; align-items: center; justify-content: center;
}
.block-survey--basic .bsv-choice-dot::after {
    content: ''; width: 6px; height: 6px; border-radius: 50%;
    background: #fff; opacity: 0; transition: opacity .15s;
}
.block-survey--basic .bsv-choice.bsv-choice--box .bsv-choice-dot {
    border-radius: 4px;
}
.block-survey--basic .bsv-choice.bsv-choice--box .bsv-choice-dot::after {
    width: 9px; height: 6px; border-radius: 0;
    background: none; border-left: 2px solid #fff; border-bottom: 2px solid #fff;
    transform: rotate(-45deg) translate(1px, -1px);
}
.block-survey--basic .bsv-choice.checked {
    border-color: var(--sv-primary); background: var(--sv-primary-lt);
}
.block-survey--basic .bsv-choice.checked .bsv-choice-dot {
    border-color: var(--sv-primary); background: var(--sv-primary);
}
.block-survey--basic .bsv-choice.checked .bsv-choice-dot::after { opacity: 1; }

/* ── select ─────────────────────────────────────────────────── */
.block-survey--basic .bsv-select {
    margin-left: 34px; width: calc(100% - 34px);
    padding: 9px 36px 9px 12px; border-radius: var(--sv-radius-sm);
    border: 1.5px solid var(--sv-border); background: var(--sv-bg);
    font-size: var(--sv-text-sm); color: var(--sv-text); outline: none;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%239ca3af' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' viewBox='0 0 24 24'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    cursor: pointer;
    transition: border-color .15s;
}
.block-survey--basic .bsv-select:focus { border-color: var(--sv-primary); }

/* ── text / textarea ─────────────────────────────────────────── */
.block-survey--basic .bsv-input,
.block-survey--basic .bsv-textarea {
    margin-left: 34px; width: calc(100% - 34px);
    padding: 9px 12px; border-radius: var(--sv-radius-sm);
    border: 1.5px solid var(--sv-border); background: var(--sv-bg);
    font-size: var(--sv-text-sm); color: var(--sv-text); outline: none;
    font-family: inherit; resize: vertical; transition: border-color .15s;
    box-sizing: border-box;
}
.block-survey--basic .bsv-input:focus,
.block-survey--basic .bsv-textarea:focus { border-color: var(--sv-primary); }
.block-survey--basic .bsv-input::placeholder,
.block-survey--basic .bsv-textarea::placeholder { color: #d1d5db; }

/* ── 별점 ────────────────────────────────────────────────────── */
.block-survey--basic .bsv-rating { display: flex; gap: 6px; padding-left: 34px; }
.block-survey--basic .bsv-star {
    font-size: 28px; color: #e5e7eb; cursor: pointer; line-height: 1;
    transition: color .1s, transform .1s;
    background: none; border: none; padding: 0;
}
.block-survey--basic .bsv-star:hover,
.block-survey--basic .bsv-star.hov { color: var(--sv-star); transform: scale(1.15); }
.block-survey--basic .bsv-star.on { color: var(--sv-star); }
.block-survey--basic .bsv-rating-label {
    margin-left: 4px; align-self: center;
    font-size: 12px; color: var(--sv-text-muted);
}

/* ── 푸터 / 제출 ─────────────────────────────────────────────── */
.block-survey--basic .bsv-footer {
    padding: 16px 24px;
    background: var(--sv-bg-muted);
    border-top: 1px solid var(--sv-border);
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px;
}
.block-survey--basic .bsv-progress {
    font-size: 12px; color: var(--sv-text-muted);
    display: flex; align-items: center; gap: 8px;
}
.block-survey--basic .bsv-progress-bar {
    width: 80px; height: 4px; background: var(--sv-border); border-radius: 2px;
    overflow: hidden;
}
.block-survey--basic .bsv-progress-fill {
    height: 100%; background: var(--sv-primary); border-radius: 2px;
    transition: width .3s ease;
    width: 0%;
}
.block-survey--basic .bsv-submit {
    padding: 9px 24px; border-radius: var(--sv-radius-sm);
    background: var(--sv-primary); color: #fff;
    font-size: 14px; font-weight: 600; border: none; cursor: pointer;
    transition: background .15s, transform .1s, opacity .15s;
    display: inline-flex; align-items: center; gap: 6px;
    letter-spacing: -.01em;
}
.block-survey--basic .bsv-submit:hover { background: var(--sv-primary-dk); }
.block-survey--basic .bsv-submit:active { transform: scale(.98); }
.block-survey--basic .bsv-submit:disabled { opacity: .6; cursor: not-allowed; }
.block-survey--basic .bsv-submit .bsv-spinner {
    display: none; width: 14px; height: 14px;
    border: 2px solid rgba(255,255,255,.4); border-top-color: #fff;
    border-radius: 50%; animation: bsv-spin .6s linear infinite;
}
.block-survey--basic .bsv-submit.loading .bsv-spinner { display: inline-block; }
@keyframes bsv-spin { to { transform: rotate(360deg); } }

/* ── 완료 / 안내 메시지 ─────────────────────────────────────── */
.block-survey--basic .bsv-done {
    display: none; padding: 40px 24px; text-align: center;
}
.block-survey--basic .bsv-done-icon {
    width: 56px; height: 56px; border-radius: 50%;
    background: #dcfce7; margin: 0 auto 16px;
    display: flex; align-items: center; justify-content: center;
}
.block-survey--basic .bsv-done-icon svg { width: 28px; height: 28px; color: var(--sv-success); }
.block-survey--basic .bsv-done-msg {
    font-size: 16px; font-weight: 700; color: var(--sv-text); margin: 0 0 6px;
}
.block-survey--basic .bsv-done-sub {
    font-size: var(--sv-text-sm); color: var(--sv-text-muted); margin: 0;
}

.block-survey--basic .bsv-notice {
    padding: 28px 24px; text-align: center;
    color: var(--sv-text-muted); font-size: var(--sv-text-sm);
    display: flex; flex-direction: column; align-items: center; gap: 8px;
}
.block-survey--basic .bsv-notice svg { width: 32px; height: 32px; color: #d1d5db; }

/* ── 결과 모드 ─────────────────────────────────────────────── */
.block-survey--basic .bsv-result-header {
    padding: 16px 24px;
    display: flex; align-items: center; gap: 10px;
    border-bottom: 1px solid var(--sv-border);
    background: var(--sv-bg-muted);
}
.block-survey--basic .bsv-result-badge {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 20px;
    background: var(--sv-primary-lt); color: var(--sv-primary);
    font-size: 13px; font-weight: 600;
}
.block-survey--basic .bsv-result-badge svg { width: 14px; height: 14px; }

/* ── 결과 바 차트 ────────────────────────────────────────────── */
.block-survey--basic .bsv-bar-list { display: flex; flex-direction: column; gap: 10px; padding-left: 34px; }
.block-survey--basic .bsv-bar-row { }
.block-survey--basic .bsv-bar-meta {
    display: flex; justify-content: space-between; align-items: baseline;
    margin-bottom: 4px;
}
.block-survey--basic .bsv-bar-label {
    font-size: 12px; color: var(--sv-text); font-weight: 500;
    flex: 1; padding-right: 8px;
}
.block-survey--basic .bsv-bar-val {
    font-size: 12px; font-weight: 700; color: var(--sv-primary); flex-shrink: 0;
}
.block-survey--basic .bsv-bar-track {
    height: 8px; background: var(--sv-border); border-radius: 4px; overflow: hidden;
}
.block-survey--basic .bsv-bar-fill {
    height: 100%; border-radius: 4px;
    background: linear-gradient(90deg, var(--sv-primary) 0%, #818cf8 100%);
    transition: width .6s cubic-bezier(.4,0,.2,1);
}

/* ── 결과 별점 ───────────────────────────────────────────────── */
.block-survey--basic .bsv-result-rating { padding-left: 34px; display: flex; align-items: center; gap: 12px; }
.block-survey--basic .bsv-result-avg {
    font-size: 32px; font-weight: 800; color: var(--sv-text); line-height: 1;
}
.block-survey--basic .bsv-result-stars { display: flex; gap: 3px; }
.block-survey--basic .bsv-result-stars span { font-size: 20px; }
.block-survey--basic .bsv-result-stars .on  { color: var(--sv-star); }
.block-survey--basic .bsv-result-stars .off { color: #e5e7eb; }
.block-survey--basic .bsv-result-stars .half {
    position: relative; color: #e5e7eb;
    display: inline-block; overflow: hidden;
}
.block-survey--basic .bsv-result-stars .half::after {
    content: '★'; position: absolute; left: 0; top: 0;
    color: var(--sv-star);
    width: 50%; overflow: hidden; display: block;
}

/* ── 결과 텍스트 답변 ────────────────────────────────────────── */
.block-survey--basic .bsv-text-list {
    padding-left: 34px; display: flex; flex-direction: column; gap: 6px;
}
.block-survey--basic .bsv-text-item {
    padding: 8px 12px; background: var(--sv-bg-muted);
    border-radius: var(--sv-radius-sm); border-left: 3px solid var(--sv-primary);
    font-size: 13px; color: var(--sv-text); line-height: 1.5;
}
.block-survey--basic .bsv-text-more {
    font-size: 12px; color: var(--sv-text-muted); padding-left: 34px; margin-top: 4px;
}

/* ── 빈 상태 ─────────────────────────────────────────────────── */
.block-survey--basic .bsv-empty {
    padding: 40px 24px; text-align: center;
    color: var(--sv-text-muted); font-size: var(--sv-text-sm);
}

/* ── 종료/참여불가 안내 ───────────────────────────────────────── */
.block-survey--basic .bsv-closed {
    padding: 28px 24px; text-align: center;
    display: flex; flex-direction: column; align-items: center; gap: 10px;
}
.block-survey--basic .bsv-closed-icon {
    width: 44px; height: 44px; border-radius: 50%;
    background: #f3f4f6; display: flex; align-items: center; justify-content: center;
}
.block-survey--basic .bsv-closed-icon svg { width: 22px; height: 22px; color: #9ca3af; }
.block-survey--basic .bsv-closed-msg {
    font-size: 14px; font-weight: 600; color: var(--sv-text); margin: 0;
}
.block-survey--basic .bsv-closed-sub {
    font-size: 12px; color: var(--sv-text-muted); margin: 0;
}
</style>

<div class="block-survey block-survey--basic">
    <?php include $titlePartial; ?>

    <div class="bsv-wrap">

        <?php /* ────── 헤더 ────── */ ?>
        <?php if ($showTitle || $showDesc): ?>
        <div class="bsv-header">
            <div class="bsv-header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
            </div>
            <?php if ($showTitle && !empty($survey['title'])): ?>
            <p class="bsv-title"><?= htmlspecialchars($survey['title']) ?></p>
            <?php endif; ?>
            <?php if ($showDesc && !empty($survey['description'])): ?>
            <p class="bsv-desc"><?= nl2br(htmlspecialchars($survey['description'])) ?></p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($mode === 'form'): ?>
        <?php /* ════════════════════════════════════════════════
               폼 모드
               ════════════════════════════════════════════════ */ ?>

        <?php if (!$canJoin): ?>
        <div class="bsv-closed">
            <div class="bsv-closed-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>
            <p class="bsv-closed-msg"><?= htmlspecialchars($joinMessage ?: '참여할 수 없는 설문입니다.') ?></p>
            <?php if (!empty($survey['title'])): ?>
            <p class="bsv-closed-sub"><?= htmlspecialchars($survey['title']) ?></p>
            <?php endif; ?>
        </div>

        <?php elseif (empty($questions)): ?>
        <div class="bsv-notice">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/>
            </svg>
            <span>등록된 질문이 없습니다.</span>
        </div>

        <?php else: ?>
        <form class="bsv-form" data-survey-id="<?= $surveyId ?>" novalidate>
            <div class="bsv-body">
            <?php foreach ($questions as $no => $q):
                $qid     = (int) $q['question_id'];
                $req     = !empty($q['required']);
                $options = $q['options'] ?? [];
            ?>
            <div class="bsv-question" data-qid="<?= $qid ?>">
                <div class="bsv-q-header">
                    <span class="bsv-q-num"><?= $no + 1 ?></span>
                    <span class="bsv-q-title">
                        <?= htmlspecialchars($q['title']) ?>
                        <?php if ($req): ?><span class="bsv-required">*</span><?php endif; ?>
                    </span>
                </div>
                <?php if (!empty($q['description'])): ?>
                <div class="bsv-q-hint"><?= htmlspecialchars($q['description']) ?></div>
                <?php endif; ?>

                <?php if ($q['type'] === 'radio'): ?>
                    <div class="bsv-choices">
                    <?php foreach ($options as $idx => $label): ?>
                        <label class="bsv-choice">
                            <input type="radio" name="q_<?= $qid ?>" value="<?= $idx ?>"
                                   <?= $req ? 'required' : '' ?>>
                            <span class="bsv-choice-dot"></span>
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                    </div>

                <?php elseif ($q['type'] === 'checkbox'): ?>
                    <div class="bsv-choices">
                    <?php foreach ($options as $idx => $label): ?>
                        <label class="bsv-choice bsv-choice--box">
                            <input type="checkbox" class="bsv-cb" data-qid="<?= $qid ?>" value="<?= $idx ?>">
                            <span class="bsv-choice-dot"></span>
                            <?= htmlspecialchars($label) ?>
                        </label>
                    <?php endforeach; ?>
                    </div>

                <?php elseif ($q['type'] === 'select'): ?>
                    <select name="q_<?= $qid ?>" class="bsv-select" <?= $req ? 'required' : '' ?>>
                        <option value="">선택하세요</option>
                        <?php foreach ($options as $idx => $label): ?>
                        <option value="<?= $idx ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($q['type'] === 'text'): ?>
                    <input type="text" name="q_<?= $qid ?>" class="bsv-input"
                           placeholder="답변을 입력하세요" <?= $req ? 'required' : '' ?>>

                <?php elseif ($q['type'] === 'textarea'): ?>
                    <textarea name="q_<?= $qid ?>" class="bsv-textarea" rows="3"
                              placeholder="답변을 입력하세요" <?= $req ? 'required' : '' ?>></textarea>

                <?php elseif ($q['type'] === 'rating'): ?>
                    <div class="bsv-rating" data-qid="<?= $qid ?>">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <button type="button" class="bsv-star" data-value="<?= $s ?>">★</button>
                        <?php endfor; ?>
                        <span class="bsv-rating-label">클릭해서 평가하세요</span>
                    </div>
                    <input type="hidden" name="q_<?= $qid ?>" class="bsv-rating-val">
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            </div>

            <div class="bsv-footer">
                <div class="bsv-progress">
                    <span class="bsv-progress-bar"><span class="bsv-progress-fill"></span></span>
                    <span class="bsv-progress-text">0 / <?= $totalQ ?></span>
                </div>
                <button type="submit" class="bsv-submit">
                    <span class="bsv-spinner"></span>
                    <span class="bsv-submit-txt">제출하기</span>
                </button>
            </div>
        </form>

        <div class="bsv-done">
            <div class="bsv-done-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
            </div>
            <p class="bsv-done-msg">참여해 주셔서 감사합니다!</p>
            <p class="bsv-done-sub">소중한 의견이 잘 전달되었습니다.</p>
        </div>

        <?php endif; ?>

        <?php else: ?>
        <?php /* ════════════════════════════════════════════════
               결과 모드
               ════════════════════════════════════════════════ */ ?>

        <div class="bsv-result-header">
            <div class="bsv-result-badge">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <strong><?= number_format($totalResponses ?? 0) ?></strong>명 참여
            </div>
        </div>

        <div class="bsv-body">
        <?php if (empty($questions)): ?>
        <div class="bsv-empty">아직 집계된 결과가 없습니다.</div>
        <?php else: ?>
        <?php foreach ($questions as $no => $q): ?>
        <div class="bsv-question">
            <div class="bsv-q-header">
                <span class="bsv-q-num"><?= $no + 1 ?></span>
                <span class="bsv-q-title"><?= htmlspecialchars($q['title']) ?></span>
            </div>

            <?php if (in_array($q['type'], ['radio', 'checkbox', 'select'])): ?>
                <div class="bsv-bar-list">
                <?php foreach (($q['stats'] ?? []) as $stat): ?>
                <div class="bsv-bar-row">
                    <div class="bsv-bar-meta">
                        <span class="bsv-bar-label"><?= htmlspecialchars($stat['label']) ?></span>
                        <span class="bsv-bar-val"><?= $stat['pct'] ?>%</span>
                    </div>
                    <div class="bsv-bar-track">
                        <div class="bsv-bar-fill" style="width:<?= min(100, (float) $stat['pct']) ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                </div>

            <?php elseif ($q['type'] === 'rating'): ?>
                <?php $avg = round((float) ($q['stats']['avg'] ?? 0), 1); ?>
                <div class="bsv-result-rating">
                    <span class="bsv-result-avg"><?= $avg ?></span>
                    <div>
                        <div class="bsv-result-stars">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <?php if ($s <= floor($avg)): ?>
                            <span class="on">★</span>
                            <?php elseif ($s - 0.5 <= $avg): ?>
                            <span class="half">★</span>
                            <?php else: ?>
                            <span class="off">★</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        </div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:3px">5점 만점</div>
                    </div>
                </div>

            <?php else: ?>
                <?php $texts = array_slice($q['stats'] ?? [], 0, 5); ?>
                <?php if (!empty($texts)): ?>
                <div class="bsv-text-list">
                    <?php foreach ($texts as $txt): ?>
                    <div class="bsv-text-item"><?= htmlspecialchars($txt) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php $total_cnt = count($q['stats'] ?? []); ?>
                <?php if ($total_cnt > 5): ?>
                <p class="bsv-text-more">외 <?= $total_cnt - 5 ?>개 응답</p>
                <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        </div>

        <?php endif; ?>

    </div><!-- /.bsv-wrap -->
</div><!-- /.block-survey--basic -->

<?php if ($mode === 'form' && !empty($questions)): ?>
<script>
(function () {
    var SURVEY_ID = <?= $surveyId ?>;
    var TOTAL_Q   = <?= $totalQ ?>;
    var wrap = document.getElementById('<?= $blockId ?>') ||
               document.querySelector('.bsv-wrap .bsv-form[data-survey-id="' + SURVEY_ID + '"]')?.closest('.block-survey--basic');

    /* ── 좀 더 안전한 래퍼 탐색 ─────────────────────── */
    var form = document.querySelector('.bsv-form[data-survey-id="' + SURVEY_ID + '"]');
    if (!form) return;
    var blockWrap  = form.closest('.block-survey--basic');
    var donePanel  = blockWrap.querySelector('.bsv-done');
    var progressFill = blockWrap.querySelector('.bsv-progress-fill');
    var progressTxt  = blockWrap.querySelector('.bsv-progress-text');

    /* ── 커스텀 라디오/체크박스 선택 표시 ─────────────── */
    form.querySelectorAll('.bsv-choice input[type="radio"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var name = this.name;
            form.querySelectorAll('.bsv-choice input[name="' + name + '"]').forEach(function (r) {
                r.closest('.bsv-choice').classList.toggle('checked', r.checked);
            });
            updateProgress();
        });
    });
    form.querySelectorAll('.bsv-choice input[type="checkbox"]').forEach(function (input) {
        input.addEventListener('change', function () {
            this.closest('.bsv-choice').classList.toggle('checked', this.checked);
            updateProgress();
        });
    });
    form.querySelectorAll('.bsv-select, .bsv-input, .bsv-textarea').forEach(function (el) {
        el.addEventListener('input', updateProgress);
        el.addEventListener('change', updateProgress);
    });

    /* ── 별점 인터랙션 ────────────────────────────────── */
    form.querySelectorAll('.bsv-rating').forEach(function (ratingEl) {
        var stars   = ratingEl.querySelectorAll('.bsv-star');
        var qid     = ratingEl.dataset.qid;
        var hiddenEl = form.querySelector('input.bsv-rating-val[name="q_' + qid + '"]');
        var label   = ratingEl.querySelector('.bsv-rating-label');
        var labels  = ['', '별로예요', '그저 그래요', '보통이에요', '좋아요', '매우 좋아요'];
        var current = 0;

        function paint(upTo, hover) {
            stars.forEach(function (s, i) {
                s.classList.toggle('on',  !hover && i < current);
                s.classList.toggle('hov', hover  && i < upTo);
            });
        }
        stars.forEach(function (star, idx) {
            star.addEventListener('mouseenter', function () { paint(idx + 1, true); });
            star.addEventListener('mouseleave', function () { paint(0, false); });
            star.addEventListener('click', function () {
                current = idx + 1;
                hiddenEl.value = current;
                paint(0, false);
                if (label) label.textContent = labels[current] || '';
                updateProgress();
            });
        });
    });

    /* ── 진행률 ───────────────────────────────────────── */
    function getAnsweredCount() {
        var answered = 0;
        form.querySelectorAll('.bsv-question').forEach(function (qEl) {
            var qid = qEl.dataset.qid;
            var radio  = qEl.querySelector('input[type="radio"]:checked');
            var cb     = qEl.querySelector('input[type="checkbox"]:checked');
            var sel    = qEl.querySelector('.bsv-select');
            var txt    = qEl.querySelector('.bsv-input, .bsv-textarea');
            var rating = qEl.querySelector('.bsv-rating-val');
            if (radio)  { answered++; return; }
            if (cb)     { answered++; return; }
            if (sel  && sel.value)    { answered++; return; }
            if (txt  && txt.value.trim()) { answered++; return; }
            if (rating && rating.value)   { answered++; return; }
        });
        return answered;
    }
    function updateProgress() {
        var cnt = getAnsweredCount();
        var pct = TOTAL_Q > 0 ? (cnt / TOTAL_Q * 100) : 0;
        if (progressFill) progressFill.style.width = pct + '%';
        if (progressTxt)  progressTxt.textContent  = cnt + ' / ' + TOTAL_Q;
    }

    /* ── 폼 제출 ──────────────────────────────────────── */
    form.addEventListener('submit', function (e) {
        e.preventDefault();

        /* 필수 체크 */
        var missing = false;
        form.querySelectorAll('.bsv-question').forEach(function (qEl) {
            var req = qEl.querySelector('[required], .bsv-choice input[required]');
            if (!req) {
                /* 체크박스는 required 없이 data 체크 */
                return;
            }
            /* 라디오 */
            var radioGroup = qEl.querySelectorAll('input[type="radio"]');
            if (radioGroup.length > 0) {
                var checked = qEl.querySelector('input[type="radio"]:checked');
                if (!checked) { missing = true; qEl.style.outline = '2px solid #ef4444'; }
                else qEl.style.outline = '';
                return;
            }
            /* select, input, textarea */
            var inp = qEl.querySelector('.bsv-select, .bsv-input, .bsv-textarea');
            if (inp && !inp.value.trim()) {
                missing = true; inp.style.borderColor = '#ef4444';
                inp.addEventListener('input', function () { this.style.borderColor = ''; }, { once: true });
            }
        });
        if (missing) return;

        var answers = {};
        form.querySelectorAll('[name^="q_"]').forEach(function (el) {
            if (el.type === 'radio'  && !el.checked) return;
            if (el.type === 'hidden' && !el.value)   return;
            if (el.classList.contains('bsv-cb'))      return;
            if (el.classList.contains('bsv-rating-val')) return;
            var qid = el.name.replace('q_', '');
            if (el.value !== '') answers[qid] = el.value;
        });
        form.querySelectorAll('.bsv-cb:checked').forEach(function (el) {
            var qid = el.dataset.qid;
            if (!answers[qid]) answers[qid] = [];
            answers[qid].push(parseInt(el.value));
        });
        form.querySelectorAll('.bsv-rating-val').forEach(function (el) {
            var qid = el.name.replace('q_', '');
            if (el.value) answers[qid] = parseInt(el.value);
        });

        var btn = form.querySelector('.bsv-submit');
        btn.disabled = true;
        btn.classList.add('loading');

        MubloRequest.requestJson('/survey/' + SURVEY_ID + '/submit', { answers: answers })
            .then(function () {
                form.style.display = 'none';
                if (donePanel) donePanel.style.display = '';
            })
            .catch(function () {
                btn.disabled = false;
                btn.classList.remove('loading');
            });
    });
})();
</script>
<?php endif; ?>
