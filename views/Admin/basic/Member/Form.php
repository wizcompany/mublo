<?php
/**
 * Admin Member - Form
 *
 * 회원 등록/수정 공용 폼
 *
 * @var string $pageTitle 페이지 제목
 * @var string $mode 'create' 또는 'edit'
 * @var array|null $member 회원 정보 (수정 시)
 * @var array $fieldDefinitions 추가 필드 정의
 * @var array $fieldValues 추가 필드 값 [field_id => value]
 * @var array $levelOptions 등급 옵션 [level_value => level_name]
 * @var array $statusOptions 상태 옵션 [status => label]
 */

$isEdit = ($mode === 'edit');
$memberId = $member['member_id'] ?? 0;
$submitUrl = $isEdit ? "/admin/member/update/{$memberId}" : '/admin/member/store';
?>
<form id="member-form">
    <?php if ($isEdit): ?>
    <input type="hidden" name="formData[member_id]" value="<?= $memberId ?>">
    <?php endif; ?>

    <div class="page-container form-container">
        <!-- 헤더 영역 -->
        <div class="sticky-header">
            <div class="row align-items-end page-navigation">
                <div class="col-sm">
                    <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '회원 관리') ?></h3>
                    <p class="text-muted mb-0">
                        <a href="/admin/member">회원 관리</a>
                        <i class="bi bi-chevron-right mx-1"></i>
                        <?= $isEdit ? '정보 수정' : '회원 등록' ?>
                    </p>
                </div>
                <div class="col-sm-auto my-2 my-sm-0">
                    <a href="/admin/member" class="btn btn-outline-secondary me-2">
                        <i class="bi bi-arrow-left me-1"></i>목록
                    </a>
                    <button type="button"
                            class="btn btn-primary mublo-submit"
                            data-target="<?= $submitUrl ?>"
                            data-callback="onMemberFormSuccess"
                            data-loading="true"
                            id="btn-save">
                        <i class="bi bi-check-lg me-1"></i>저장
                    </button>
                </div>
            </div>
        </div>

        <!-- 폼 내용 -->
        <div class="row mt-4">
            <!-- 기본 정보 -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                        <i class="bi bi-person me-2 text-pastel-blue"></i>기본 정보
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    아이디 <span class="text-danger">*</span>
                                </label>
                                <?php if ($isEdit): ?>
                                <input type="text"
                                       class="form-control-plaintext fw-bold"
                                       value="<?= htmlspecialchars($member['user_id'] ?? '') ?>"
                                       readonly>
                                <div class="form-text">아이디는 변경할 수 없습니다.</div>
                                <?php else: ?>
                                <div class="input-group">
                                    <input type="text"
                                           name="formData[user_id]"
                                           id="input-user_id"
                                           class="form-control"
                                           placeholder="영문, 숫자 4~20자"
                                           pattern="^[a-zA-Z0-9]{4,20}$"
                                           data-duplicate-checked="false"
                                           required>
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-check-duplicate"
                                            data-field="user_id"
                                            data-input="input-user_id">
                                        중복확인
                                    </button>
                                </div>
                                <div class="form-text" id="feedback-user_id">영문, 숫자 4~20자로 입력</div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    비밀번호 <?php if (!$isEdit): ?><span class="text-danger">*</span><?php endif; ?>
                                </label>
                                <input type="password"
                                       name="formData[password]"
                                       class="form-control"
                                       placeholder="<?= $isEdit ? '변경 시에만 입력' : '최소 6자 이상' ?>"
                                       minlength="6"
                                       <?= $isEdit ? '' : 'required' ?>>
                                <div class="form-text">
                                    <?= $isEdit ? '비워두면 기존 비밀번호 유지' : '최소 6자 이상 입력' ?>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    닉네임 <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="text"
                                           name="formData[nickname]"
                                           id="input-nickname"
                                           class="form-control"
                                           placeholder="2~20자"
                                           minlength="2"
                                           maxlength="20"
                                           value="<?= htmlspecialchars($member['nickname'] ?? '') ?>"
                                           data-duplicate-checked="<?= $isEdit ? 'true' : 'false' ?>"
                                           required>
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-check-duplicate"
                                            data-field="nickname"
                                            data-input="input-nickname">
                                        중복확인
                                    </button>
                                </div>
                                <div class="form-text" id="feedback-nickname">2~20자로 입력</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 추가 필드 -->
                <?php if (!empty($fieldDefinitions)): ?>
                <div class="card mb-4">
                    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                        <i class="bi bi-card-list me-2 text-pastel-green"></i>추가 정보
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($fieldDefinitions as $field):
                                $fieldId = $field['field_id'];
                                $fieldName = $field['field_name'];
                                $fieldLabel = $field['field_label'];
                                $fieldType = $field['field_type'];
                                $isRequired = (bool) ($field['is_required'] ?? false);
                                $isEncrypted = (bool) ($field['is_encrypted'] ?? false);
                                $isUnique = (bool) ($field['is_unique'] ?? false);
                                $value = $fieldValues[$fieldId] ?? '';
                                $options = !empty($field['field_options']) ? json_decode($field['field_options'], true) : [];
                            ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">
                                    <?php if ($isEncrypted): ?><i class="bi bi-shield-lock text-warning me-1"></i><?php endif; ?>
                                    <?= htmlspecialchars($fieldLabel) ?>
                                    <?php if ($isRequired): ?><span class="text-danger">*</span><?php endif; ?>
                                </label>

                                <?php if ($fieldType === 'textarea'): ?>
                                <textarea name="fields[<?= $fieldId ?>]"
                                          class="form-control"
                                          rows="3"
                                          <?= $isRequired ? 'required' : '' ?>><?= htmlspecialchars($value) ?></textarea>

                                <?php elseif ($fieldType === 'select'): ?>
                                <select name="fields[<?= $fieldId ?>]" class="form-select" <?= $isRequired ? 'required' : '' ?>>
                                    <option value="">선택하세요</option>
                                    <?php if (is_array($options)):
                                        foreach ($options as $opt):
                                            $optValue = $opt['value'] ?? '';
                                            $optLabel = $opt['label'] ?? $optValue;
                                    ?>
                                    <option value="<?= htmlspecialchars($optValue) ?>"
                                            <?= $value === $optValue ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($optLabel) ?>
                                    </option>
                                    <?php endforeach; endif; ?>
                                </select>

                                <?php elseif ($fieldType === 'radio'): ?>
                                <div>
                                    <?php if (is_array($options)):
                                        foreach ($options as $idx => $opt):
                                            $optValue = $opt['value'] ?? '';
                                            $optLabel = $opt['label'] ?? $optValue;
                                    ?>
                                    <div class="form-check form-check-inline">
                                        <input type="radio"
                                               class="form-check-input"
                                               name="fields[<?= $fieldId ?>]"
                                               id="field_<?= $fieldId ?>_<?= $idx ?>"
                                               value="<?= htmlspecialchars($optValue) ?>"
                                               <?= $value === $optValue ? 'checked' : '' ?>
                                               <?= $isRequired ? 'required' : '' ?>>
                                        <label class="form-check-label" for="field_<?= $fieldId ?>_<?= $idx ?>">
                                            <?= htmlspecialchars($optLabel) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; endif; ?>
                                </div>

                                <?php elseif ($fieldType === 'checkbox'): ?>
                                <div>
                                    <?php
                                    $checkedValues = is_array($value) ? $value : (is_string($value) && $value ? explode(',', $value) : []);
                                    if (is_array($options)):
                                        foreach ($options as $idx => $opt):
                                            $optValue = $opt['value'] ?? '';
                                            $optLabel = $opt['label'] ?? $optValue;
                                    ?>
                                    <div class="form-check form-check-inline">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               name="fields[<?= $fieldId ?>][]"
                                               id="field_<?= $fieldId ?>_<?= $idx ?>"
                                               value="<?= htmlspecialchars($optValue) ?>"
                                               <?= in_array($optValue, $checkedValues) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="field_<?= $fieldId ?>_<?= $idx ?>">
                                            <?= htmlspecialchars($optLabel) ?>
                                        </label>
                                    </div>
                                    <?php endforeach; endif; ?>
                                </div>

                                <?php elseif ($fieldType === 'address'): ?>
                                <?php
                                    $addrValue = is_array($value) ? $value : (is_string($value) ? json_decode($value, true) : []);
                                    $addrValue = is_array($addrValue) ? $addrValue : [];
                                ?>
                                <div class="address-field">
                                    <div class="input-group mb-2">
                                        <input type="text"
                                               name="fields[<?= $fieldId ?>][zipcode]"
                                               class="form-control"
                                               placeholder="우편번호"
                                               value="<?= htmlspecialchars($addrValue['zipcode'] ?? '') ?>"
                                               id="zipcode_<?= $fieldId ?>"
                                               readonly>
                                        <button type="button" class="btn btn-outline-secondary btn-search-address"
                                                data-field-id="<?= $fieldId ?>">
                                            <i class="bi bi-search me-1"></i>검색
                                        </button>
                                    </div>
                                    <input type="text"
                                           name="fields[<?= $fieldId ?>][address1]"
                                           class="form-control mb-2"
                                           placeholder="기본 주소"
                                           value="<?= htmlspecialchars($addrValue['address1'] ?? '') ?>"
                                           id="address1_<?= $fieldId ?>"
                                           readonly>
                                    <input type="text"
                                           name="fields[<?= $fieldId ?>][address2]"
                                           class="form-control"
                                           placeholder="상세 주소 (직접 입력)"
                                           value="<?= htmlspecialchars($addrValue['address2'] ?? '') ?>"
                                           id="address2_<?= $fieldId ?>">
                                </div>

                                <?php elseif ($fieldType === 'date'): ?>
                                <input type="date"
                                       name="fields[<?= $fieldId ?>]"
                                       class="form-control"
                                       value="<?= htmlspecialchars($value) ?>"
                                       <?= $isRequired ? 'required' : '' ?>>

                                <?php elseif ($fieldType === 'number'): ?>
                                <input type="number"
                                       name="fields[<?= $fieldId ?>]"
                                       class="form-control"
                                       value="<?= htmlspecialchars($value) ?>"
                                       <?= $isRequired ? 'required' : '' ?>>

                                <?php elseif ($fieldType === 'email'): ?>
                                <?php if ($isUnique): ?>
                                <div class="input-group">
                                    <input type="email"
                                           name="fields[<?= $fieldId ?>]"
                                           id="input-field-<?= $fieldId ?>"
                                           class="form-control"
                                           placeholder="example@email.com"
                                           value="<?= htmlspecialchars($value) ?>"
                                           data-duplicate-checked="<?= $isEdit ? 'true' : 'false' ?>"
                                           <?= $isRequired ? 'required' : '' ?>>
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-check-duplicate"
                                            data-field="<?= htmlspecialchars($fieldName) ?>"
                                            data-input="input-field-<?= $fieldId ?>">
                                        중복확인
                                    </button>
                                </div>
                                <div class="form-text" id="feedback-field-<?= $fieldId ?>"></div>
                                <?php else: ?>
                                <input type="email"
                                       name="fields[<?= $fieldId ?>]"
                                       class="form-control"
                                       placeholder="example@email.com"
                                       value="<?= htmlspecialchars($value) ?>"
                                       <?= $isRequired ? 'required' : '' ?>>
                                <?php endif; ?>

                                <?php elseif ($fieldType === 'tel'): ?>
                                <?php if ($isUnique): ?>
                                <div class="input-group">
                                    <input type="tel"
                                           name="fields[<?= $fieldId ?>]"
                                           id="input-field-<?= $fieldId ?>"
                                           class="form-control mask-hp"
                                           placeholder="010-1234-5678"
                                           value="<?= htmlspecialchars($value) ?>"
                                           data-duplicate-checked="<?= $isEdit ? 'true' : 'false' ?>"
                                           <?= $isRequired ? 'required' : '' ?>>
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-check-duplicate"
                                            data-field="<?= htmlspecialchars($fieldName) ?>"
                                            data-input="input-field-<?= $fieldId ?>">
                                        중복확인
                                    </button>
                                </div>
                                <div class="form-text" id="feedback-field-<?= $fieldId ?>"></div>
                                <?php else: ?>
                                <input type="tel"
                                       name="fields[<?= $fieldId ?>]"
                                       class="form-control mask-hp"
                                       placeholder="010-1234-5678"
                                       value="<?= htmlspecialchars($value) ?>"
                                       <?= $isRequired ? 'required' : '' ?>>
                                <?php endif; ?>

                                <?php elseif ($fieldType === 'file'): ?>
                                <?php
                                    // $value는 MemberService.getFieldValues()에서 이미 파싱된 배열
                                    // ['filename' => ..., 'url' => ..., 'size' => ...]
                                    $fileMeta = is_array($value) ? $value : null;
                                ?>
                                <?php if ($fileMeta && !empty($fileMeta['filename'])): ?>
                                <div class="d-flex align-items-center gap-2 p-2 border rounded">
                                    <i class="bi bi-file-earmark"></i>
                                    <a href="<?= htmlspecialchars($fileMeta['url'] ?? '#') ?>"
                                       target="_blank"
                                       class="text-decoration-none">
                                        <?= htmlspecialchars($fileMeta['filename']) ?>
                                    </a>
                                    <?php if (!empty($fileMeta['size'])): ?>
                                    <span class="text-muted small">(<?= round($fileMeta['size'] / 1024, 1) ?>KB)</span>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="text-muted small">첨부파일 없음</div>
                                <?php endif; ?>

                                <?php else: // text (기본) ?>
                                <?php if ($isUnique): ?>
                                <div class="input-group">
                                    <input type="text"
                                           name="fields[<?= $fieldId ?>]"
                                           id="input-field-<?= $fieldId ?>"
                                           class="form-control"
                                           value="<?= htmlspecialchars($value) ?>"
                                           data-duplicate-checked="<?= $isEdit ? 'true' : 'false' ?>"
                                           <?= $isRequired ? 'required' : '' ?>>
                                    <button type="button"
                                            class="btn btn-outline-secondary btn-check-duplicate"
                                            data-field="<?= htmlspecialchars($fieldName) ?>"
                                            data-input="input-field-<?= $fieldId ?>">
                                        중복확인
                                    </button>
                                </div>
                                <div class="form-text" id="feedback-field-<?= $fieldId ?>"></div>
                                <?php else: ?>
                                <input type="text"
                                       name="fields[<?= $fieldId ?>]"
                                       class="form-control"
                                       value="<?= htmlspecialchars($value) ?>"
                                       <?= $isRequired ? 'required' : '' ?>>
                                <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- 플러그인 확장 섹션 -->
                <?php if (!empty($pluginSections)): ?>
                    <?php foreach ($pluginSections as $sectionHtml): ?>
                        <?= $sectionHtml ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- 등급/상태 설정 -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                        <i class="bi bi-gear me-2 text-pastel-purple"></i>회원 설정
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">회원 등급</label>
                            <select name="formData[level_value]" class="form-select">
                                <?php foreach ($levelOptions as $levelValue => $levelName): ?>
                                <option value="<?= htmlspecialchars($levelValue) ?>"
                                        <?= ($member['level_value'] ?? 1) == $levelValue ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($levelName) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">계정 상태</label>
                            <select name="formData[status]" class="form-select">
                                <?php foreach ($statusOptions as $statusValue => $statusLabel): ?>
                                <option value="<?= htmlspecialchars($statusValue) ?>"
                                        <?= ($member['status'] ?? 'active') === $statusValue ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($statusLabel) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($isEdit && ($adminIsSuper ?? false)): ?>
                        <div class="mb-0">
                            <label class="form-label">플랫폼 권한</label>
                            <div class="form-check form-switch">
                                <input type="hidden" name="formData[can_create_site]" value="0">
                                <input class="form-check-input" type="checkbox"
                                       name="formData[can_create_site]" value="1"
                                       id="canCreateSite"
                                       <?= ($member['can_create_site'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="canCreateSite">
                                    사이트 생성 가능
                                </label>
                            </div>
                            <div class="form-text text-muted">활성화 시 이 회원이 하위 도메인(사이트)을 생성할 수 있습니다.</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                <div class="card mb-4">
                    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                        <i class="bi bi-clock-history me-2 text-pastel-sky"></i>가입 정보
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-5 text-muted">보유 포인트</dt>
                            <dd class="col-sm-7"><?= number_format($member['point'] ?? 0) ?> P</dd>

                            <dt class="col-sm-5 text-muted">가입일</dt>
                            <dd class="col-sm-7"><?= htmlspecialchars($member['created_at'] ?? '-') ?></dd>

                            <dt class="col-sm-5 text-muted">최종 로그인</dt>
                            <dd class="col-sm-7 mb-0"><?= htmlspecialchars($member['last_login_at'] ?? '-') ?></dd>
                        </dl>
                    </div>
                </div>

                <?php if (!empty($recentPointLogs)): ?>
                <div class="card mb-4">
                    <div class="px-3 pt-3 pb-0 fw-semibold d-flex justify-content-between align-items-center" style="font-size:0.9rem">
                        <span><i class="bi bi-coin me-2 text-pastel-orange"></i>최근 포인트 내역</span>
                        <a href="/admin/memberpoint/history?member_id=<?= $memberId ?>" class="btn btn-sm btn-outline-secondary">전체보기</a>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0">
                            <tbody>
                            <?php foreach ($recentPointLogs as $log): ?>
                            <tr>
                                <td class="text-muted ps-3" style="font-size:.8rem; white-space:nowrap">
                                    <?= substr($log['created_at'], 0, 16) ?>
                                </td>
                                <td class="text-truncate" style="max-width:100px" title="<?= htmlspecialchars($log['message']) ?>">
                                    <?= htmlspecialchars($log['message']) ?>
                                </td>
                                <td class="text-end pe-3 fw-bold <?= $log['amount'] >= 0 ? 'text-primary' : 'text-danger' ?>" style="white-space:nowrap">
                                    <?= $log['amount'] >= 0 ? '+' : '' ?><?= number_format($log['amount']) ?> P
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<!-- 다음 주소 검색 API -->
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var memberId = <?= json_encode($memberId) ?>;

    // ========================================
    // 중복 체크 버튼 클릭
    // ========================================
    document.querySelectorAll('.btn-check-duplicate').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var fieldName = this.dataset.field;
            var inputId = this.dataset.input;
            var inputEl = document.getElementById(inputId);
            var feedbackEl = document.getElementById('feedback-' + inputId.replace('input-', ''));

            if (!inputEl) return;

            var value = inputEl.value.trim();
            if (!value) {
                showFeedback(feedbackEl, '값을 입력해주세요.', 'warning');
                inputEl.focus();
                return;
            }

            // AJAX 중복 체크
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            MubloRequest.requestJson('/admin/member/check-duplicate', {
                field_name: fieldName,
                value: value,
                member_id: memberId || null
            }).then(function(response) {
                btn.disabled = false;
                btn.textContent = '중복확인';

                if (response.result === 'success') {
                    if (response.data.duplicate) {
                        showFeedback(feedbackEl, response.message, 'danger');
                        inputEl.classList.add('is-invalid');
                        inputEl.classList.remove('is-valid');
                        inputEl.dataset.duplicateChecked = 'false';
                    } else {
                        showFeedback(feedbackEl, response.message, 'success');
                        inputEl.classList.add('is-valid');
                        inputEl.classList.remove('is-invalid');
                        inputEl.dataset.duplicateChecked = 'true';
                    }
                } else {
                    showFeedback(feedbackEl, response.message || '오류가 발생했습니다.', 'danger');
                }
            }).catch(function(err) {
                btn.disabled = false;
                btn.textContent = '중복확인';
                showFeedback(feedbackEl, '서버 오류가 발생했습니다.', 'danger');
            });
        });
    });

    // 피드백 표시 함수
    function showFeedback(el, message, type) {
        if (!el) return;
        el.textContent = message;
        el.className = 'form-text';
        if (type === 'success') el.classList.add('text-success');
        else if (type === 'danger') el.classList.add('text-danger');
        else if (type === 'warning') el.classList.add('text-warning');
    }

    // 입력값 변경 시 중복체크 상태 초기화
    document.querySelectorAll('[data-duplicate-checked]').forEach(function(input) {
        input.addEventListener('input', function() {
            this.dataset.duplicateChecked = 'false';
            this.classList.remove('is-valid', 'is-invalid');
            var feedbackEl = document.getElementById('feedback-' + this.id.replace('input-', ''));
            if (feedbackEl) {
                feedbackEl.textContent = '';
                feedbackEl.className = 'form-text';
            }
        });
    });

    // ========================================
    // 폼 제출 전 중복 체크 검증 (MubloRequest 통합)
    // ========================================
    MubloRequest.configure({
        formValidator: function(form) {
            var uncheckedFields = [];
            form.querySelectorAll('[data-duplicate-checked="false"]').forEach(function(input) {
                if (input.value.trim()) {
                    var label = input.closest('.col-md-6')?.querySelector('.form-label')?.textContent?.trim() || input.name;
                    uncheckedFields.push(label.replace('*', '').trim());
                }
            });

            if (uncheckedFields.length > 0) {
                MubloRequest.showAlert('다음 필드의 중복 확인이 필요합니다:\n- ' + uncheckedFields.join('\n- '), 'warning');
                return false;
            }
            return true;
        }
    });

    // ========================================
    // 주소 검색 버튼 클릭
    // ========================================
    document.querySelectorAll('.btn-search-address').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var fieldId = this.dataset.fieldId;
            new daum.Postcode({
                oncomplete: function(data) {
                    document.getElementById('zipcode_' + fieldId).value = data.zonecode;
                    document.getElementById('address1_' + fieldId).value = data.roadAddress || data.jibunAddress;
                    document.getElementById('address2_' + fieldId).focus();
                }
            }).open();
        });
    });

    // ========================================
    // 폼 제출 성공 콜백
    // ========================================
    window.onMemberFormSuccess = function(response) {
        MubloRequest.showToast(response.message || '저장되었습니다.', 'success');
        const redirect = response.data?.redirect || '/admin/member';
        location.href = redirect;
    };
});

<?php if (!empty($pluginScripts)): ?>
<?php foreach ($pluginScripts as $script): ?>
<?= $script ?>

<?php endforeach; ?>
<?php endif; ?>
</script>
