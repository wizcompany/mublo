<?php
/**
 * @var array  $survey
 * @var array  $questions
 * @var int    $responseCount
 * @var bool   $canJoin
 * @var string $joinMessage
 */
$surveyId      = (int) $survey['survey_id'];
$responseCount = (int) ($responseCount ?? 0);
?>
<div class="sv-page container py-5" style="max-width:700px">

    <!-- 헤더 -->
    <div class="sv-page-header mb-4">
        <h2 class="sv-page-title"><?= htmlspecialchars($survey['title']) ?></h2>
        <?php if (!empty($survey['description'])): ?>
        <p class="sv-page-desc"><?= nl2br(htmlspecialchars($survey['description'])) ?></p>
        <?php endif; ?>
        <div class="sv-page-meta">
            <span class="sv-meta-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                    <circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <span id="sv-count"><?= number_format($responseCount) ?></span>명 참여
            </span>
            <?php if (!empty($survey['end_at'])): ?>
            <span class="sv-meta-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                ~<?= substr($survey['end_at'], 0, 10) ?>
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 참여 불가 안내 -->
    <?php if (!$canJoin): ?>
    <div class="sv-notice sv-notice--warn">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <?= htmlspecialchars($joinMessage) ?>
    </div>

    <?php else: ?>
    <!-- 설문 폼 -->
    <div id="sv-form-area">
        <!-- 제출 완료 패널 (숨김, form과 같은 영역 안에서 교체) -->
        <div id="sv-done" style="display:none">
            <div class="sv-done-card">
                <div class="sv-done-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 6 9 17l-5-5"/>
                    </svg>
                </div>
                <h3 class="sv-done-title">참여해 주셔서 감사합니다!</h3>
                <p class="sv-done-msg">소중한 의견이 잘 전달되었습니다.</p>
                <div class="sv-done-count">
                    현재 <strong id="sv-done-count"><?= number_format($responseCount + 1) ?></strong>명이 참여했습니다
                </div>
            </div>
        </div>

        <form id="sv-form" novalidate>
            <?php foreach ($questions as $no => $q):
                $qid     = (int) $q['question_id'];
                $req     = !empty($q['required']);
                $options = $q['options'] ?? [];
            ?>
            <div class="sv-card sv-question mb-3" data-qid="<?= $qid ?>">
                <div class="sv-q-num-row">
                    <span class="sv-q-num">Q<?= $no + 1 ?></span>
                    <?php if ($req): ?><span class="sv-required">필수</span><?php endif; ?>
                </div>
                <div class="sv-q-title">
                    <?= htmlspecialchars($q['title']) ?>
                </div>
                <?php if (!empty($q['description'])): ?>
                <div class="sv-q-hint"><?= htmlspecialchars($q['description']) ?></div>
                <?php endif; ?>

                <div class="sv-q-body">
                <?php if ($q['type'] === 'radio'): ?>
                    <?php foreach ($options as $idx => $label): ?>
                    <label class="sv-choice">
                        <input type="radio" name="q_<?= $qid ?>" value="<?= $idx ?>" <?= $req ? 'required' : '' ?>>
                        <span class="sv-choice-mark sv-choice-mark--radio"></span>
                        <?= htmlspecialchars($label) ?>
                    </label>
                    <?php endforeach; ?>

                <?php elseif ($q['type'] === 'checkbox'): ?>
                    <?php foreach ($options as $idx => $label): ?>
                    <label class="sv-choice">
                        <input type="checkbox" class="sv-cb" data-qid="<?= $qid ?>" value="<?= $idx ?>">
                        <span class="sv-choice-mark sv-choice-mark--check"></span>
                        <?= htmlspecialchars($label) ?>
                    </label>
                    <?php endforeach; ?>

                <?php elseif ($q['type'] === 'select'): ?>
                    <select class="sv-select" name="q_<?= $qid ?>" <?= $req ? 'required' : '' ?>>
                        <option value="">선택하세요</option>
                        <?php foreach ($options as $idx => $label): ?>
                        <option value="<?= $idx ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($q['type'] === 'text'): ?>
                    <input type="text" class="sv-input" name="q_<?= $qid ?>"
                           placeholder="답변을 입력하세요" <?= $req ? 'required' : '' ?>>

                <?php elseif ($q['type'] === 'textarea'): ?>
                    <textarea class="sv-textarea" name="q_<?= $qid ?>" rows="4"
                              placeholder="답변을 입력하세요" <?= $req ? 'required' : '' ?>></textarea>

                <?php elseif ($q['type'] === 'rating'): ?>
                    <div class="sv-rating" data-qid="<?= $qid ?>">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                        <button type="button" class="sv-star" data-value="<?= $s ?>">★</button>
                        <?php endfor; ?>
                        <span class="sv-rating-label">선택하세요</span>
                    </div>
                    <input type="hidden" name="q_<?= $qid ?>" class="sv-rating-val">
                <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="sv-submit-row">
                <button type="submit" class="sv-submit-btn" id="sv-submit">
                    <span class="sv-spinner" id="sv-spinner"></span>
                    설문 제출하기
                </button>
            </div>
        </form>
    </div>

    <?php endif; ?>

</div>

<style>
.sv-page-title {
    font-size: 22px; font-weight: 800; color: #111827; margin: 0 0 8px;
}
.sv-page-desc {
    font-size: 14px; color: #6b7280; margin: 0 0 12px; line-height: 1.6;
}
.sv-page-meta { display: flex; flex-wrap: wrap; gap: 8px; }
.sv-meta-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 20px;
    background: #f3f4f6; font-size: 12px; color: #6b7280;
}

/* 참여불가 알림 */
.sv-notice {
    display: flex; align-items: center; gap: 10px;
    padding: 14px 18px; border-radius: 10px;
    font-size: 14px; font-weight: 500;
}
.sv-notice--warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }

/* 카드 */
.sv-card {
    background: #fff; border: 1px solid #e5e7eb;
    border-radius: 12px; padding: 20px 22px;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}
.sv-q-num-row { display: flex; align-items: center; gap: 8px; margin-bottom: 8px; }
.sv-q-num {
    font-size: 11px; font-weight: 700; color: #6366f1;
    background: #eef2ff; padding: 2px 8px; border-radius: 4px;
}
.sv-required {
    font-size: 10px; font-weight: 700; color: #ef4444;
    background: #fef2f2; padding: 2px 6px; border-radius: 4px;
}
.sv-q-title {
    font-size: 15px; font-weight: 600; color: #111827; margin-bottom: 4px; line-height: 1.5;
}
.sv-q-hint { font-size: 12px; color: #9ca3af; margin-bottom: 12px; }
.sv-q-body { margin-top: 14px; }

/* 선택지 */
.sv-choice {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px; margin-bottom: 6px;
    border: 1.5px solid #e5e7eb; border-radius: 8px;
    cursor: pointer; font-size: 14px; color: #374151;
    transition: border-color .15s, background .15s;
    user-select: none;
}
.sv-choice:last-child { margin-bottom: 0; }
.sv-choice:hover { border-color: #6366f1; background: #eef2ff; }
.sv-choice input { display: none; }

.sv-choice-mark {
    width: 16px; height: 16px; flex-shrink: 0;
    border: 2px solid #d1d5db; background: #fff;
    transition: border-color .15s, background .15s;
    display: flex; align-items: center; justify-content: center;
}
.sv-choice-mark--radio { border-radius: 50%; }
.sv-choice-mark--check { border-radius: 4px; }
.sv-choice-mark--radio::after {
    content: ''; width: 6px; height: 6px;
    border-radius: 50%; background: #fff; opacity: 0; transition: opacity .15s;
}
.sv-choice-mark--check::after {
    content: ''; width: 8px; height: 5px;
    border-left: 2px solid #fff; border-bottom: 2px solid #fff;
    transform: rotate(-45deg) translate(1px, -1px); opacity: 0; transition: opacity .15s;
}
.sv-choice.checked {
    border-color: #6366f1; background: #eef2ff;
}
.sv-choice.checked .sv-choice-mark {
    border-color: #6366f1; background: #6366f1;
}
.sv-choice.checked .sv-choice-mark::after { opacity: 1; }

/* select / input / textarea */
.sv-select, .sv-input, .sv-textarea {
    width: 100%; padding: 10px 14px;
    border: 1.5px solid #e5e7eb; border-radius: 8px;
    font-size: 14px; color: #111827; outline: none;
    font-family: inherit; transition: border-color .15s;
    box-sizing: border-box;
}
.sv-select { appearance: none; cursor: pointer; padding-right: 36px;
    background: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%239ca3af' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 10px center / 16px; }
.sv-textarea { resize: vertical; }
.sv-select:focus, .sv-input:focus, .sv-textarea:focus { border-color: #6366f1; }
.sv-input::placeholder, .sv-textarea::placeholder { color: #d1d5db; }

/* 별점 */
.sv-rating { display: flex; align-items: center; gap: 6px; }
.sv-star {
    font-size: 30px; color: #e5e7eb; cursor: pointer;
    background: none; border: none; padding: 0; line-height: 1;
    transition: color .1s, transform .1s;
}
.sv-star.on, .sv-star.hov { color: #f59e0b; transform: scale(1.15); }
.sv-rating-label { font-size: 12px; color: #9ca3af; margin-left: 4px; }

/* 제출 버튼 */
.sv-submit-row { margin-top: 24px; }
.sv-submit-btn {
    width: 100%; padding: 14px;
    background: #6366f1; color: #fff;
    font-size: 15px; font-weight: 700; border: none; border-radius: 10px;
    cursor: pointer; transition: background .15s, transform .1s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    letter-spacing: -.01em;
}
.sv-submit-btn:hover { background: #4f46e5; }
.sv-submit-btn:active { transform: scale(.99); }
.sv-submit-btn:disabled { opacity: .6; cursor: not-allowed; }
.sv-spinner {
    display: none; width: 16px; height: 16px;
    border: 2px solid rgba(255,255,255,.4); border-top-color: #fff;
    border-radius: 50%; animation: sv-spin .6s linear infinite;
}
.sv-submit-btn.loading .sv-spinner { display: inline-block; }
@keyframes sv-spin { to { transform: rotate(360deg); } }

/* 완료 카드 */
.sv-done-card {
    background: #fff; border: 1px solid #e5e7eb; border-radius: 16px;
    padding: 48px 32px; text-align: center;
    box-shadow: 0 4px 24px rgba(0,0,0,.06);
}
.sv-done-icon {
    width: 64px; height: 64px; border-radius: 50%;
    background: #dcfce7; margin: 0 auto 20px;
    display: flex; align-items: center; justify-content: center;
}
.sv-done-icon svg { width: 32px; height: 32px; color: #10b981; }
.sv-done-title { font-size: 20px; font-weight: 800; color: #111827; margin: 0 0 8px; }
.sv-done-msg { font-size: 14px; color: #6b7280; margin: 0 0 20px; }
.sv-done-count {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 8px 20px; background: #eef2ff; border-radius: 20px;
    font-size: 14px; color: #4f46e5;
}
.sv-done-count strong { font-size: 18px; font-weight: 800; }
</style>

<script>
(function () {
    /* 라디오/체크박스 커스텀 스타일 */
    document.querySelectorAll('.sv-choice input[type="radio"]').forEach(function (input) {
        input.addEventListener('change', function () {
            var name = this.name;
            document.querySelectorAll('.sv-choice input[name="' + name + '"]').forEach(function (r) {
                r.closest('.sv-choice').classList.toggle('checked', r.checked);
            });
        });
    });
    document.querySelectorAll('.sv-choice input[type="checkbox"]').forEach(function (input) {
        input.addEventListener('change', function () {
            this.closest('.sv-choice').classList.toggle('checked', this.checked);
        });
    });

    /* 별점 */
    document.querySelectorAll('.sv-rating').forEach(function (ratingEl) {
        var stars   = ratingEl.querySelectorAll('.sv-star');
        var qid     = ratingEl.dataset.qid;
        var hidden  = document.querySelector('.sv-rating-val[name="q_' + qid + '"]');
        var label   = ratingEl.querySelector('.sv-rating-label');
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
                if (hidden) hidden.value = current;
                paint(0, false);
                if (label) label.textContent = labels[current] || '';
            });
        });
    });

    /* 폼 제출 */
    var form = document.getElementById('sv-form');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        var answers = {};

        form.querySelectorAll('[name^="q_"]').forEach(function (el) {
            if (el.type === 'radio'  && !el.checked) return;
            if (el.type === 'hidden' && !el.value)   return;
            if (el.classList.contains('sv-cb'))         return;
            if (el.classList.contains('sv-rating-val')) return;
            var qid = el.name.replace('q_', '');
            if (el.value !== '') answers[qid] = el.value;
        });
        form.querySelectorAll('.sv-cb:checked').forEach(function (el) {
            var qid = el.dataset.qid;
            if (!answers[qid]) answers[qid] = [];
            answers[qid].push(parseInt(el.value));
        });
        form.querySelectorAll('.sv-rating-val').forEach(function (el) {
            var qid = el.name.replace('q_', '');
            if (el.value) answers[qid] = parseInt(el.value);
        });

        var btn = document.getElementById('sv-submit');
        btn.disabled = true;
        btn.classList.add('loading');

        MubloRequest.requestJson('/survey/<?= $surveyId ?>/submit', { answers: answers })
            .then(function (res) {
                /* 같은 영역 안에서 form → 완료 패널로 교체 */
                form.style.display = 'none';
                document.getElementById('sv-done').style.display = '';

                /* 참여자 수 +1 반영 */
                var countEl = document.getElementById('sv-count');
                if (countEl) {
                    var n = parseInt(countEl.textContent.replace(/,/g, ''), 10) || 0;
                    countEl.textContent = (n + 1).toLocaleString();
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.classList.remove('loading');
            });
    });
})();
</script>
