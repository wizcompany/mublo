<?php
/**
 * Admin Boardconfig - Form
 *
 * 게시판 설정 생성/수정 폼
 *
 * @var string $pageTitle 페이지 제목
 * @var bool $isEdit 수정 모드 여부
 * @var array|null $board 게시판 데이터
 * @var int $articleCount 게시글 수 (수정 시)
 * @var array $groups 그룹 옵션 [['value' => id, 'label' => name], ...]
 * @var array $allCategories 전체 카테고리 목록 (BoardCategory Entity 배열)
 * @var array $selectedCategoryIds 선택된 카테고리 ID 배열
 * @var array $skins 스킨 옵션
 * @var array $editors 에디터 옵션
 * @var bool $isSuper 현재 로그인 관리자가 Super 여부 (is_global 체크박스 노출 제어)
 */

$board = $board ?? [];
$isEdit = $isEdit ?? false;
$isSuper = $isSuper ?? false;

$anchor = [
    'anc_basic' => '기본정보',
    'anc_permission' => '권한설정',
    'anc_ui' => 'UI설정',
    'anc_feature' => '기능설정',
    'anc_file' => '파일설정',
];
?>
<form name="frm" id="frm">
    <div class="page-container form-container">
        <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center">
            <div class="flex-grow-1">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '게시판 추가') ?></h3>
                <p class="text-muted mb-0">
                    <?php if ($isEdit): ?>
                    게시판 설정을 수정합니다.
                    <?php if (($articleCount ?? 0) > 0): ?>
                    <span class="text-info">(게시글 <?= number_format($articleCount) ?>개)</span>
                    <?php endif; ?>
                    <?php else: ?>
                    새로운 게시판을 생성합니다.
                    <?php endif; ?>
                </p>
            </div>
            <div class="flex-grow-1 flex-sm-grow-0 d-flex gap-2">
                <a href="/admin/board/config" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>목록
                </a>
                <button type="button"
                    class="btn btn-primary mublo-submit"
                    data-target="/admin/board/config/store"
                    data-callback="boardSaved">
                    <i class="bi bi-check-lg me-1"></i>저장
                </button>
            </div>
        </div>

        <!-- 숨김 필드 -->
        <?php if ($isEdit): ?>
        <input type="hidden" name="formData[board_id]" value="<?= $board['board_id'] ?? '' ?>">
        <?php endif; ?>

        <div class="sticky-spy mt-3" data-bs-spy="scroll" data-bs-target="#my-nav" data-bs-smooth-scroll="true" tabindex="0">
            <div class="sticky-top">
                <nav id="my-nav" class="navbar">
                    <ul class="nav nav-tabs w-100">
                        <?php $isFirst = true; foreach ($anchor as $id => $label): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $isFirst ? 'active' : '' ?>" href="#<?= $id ?>">
                                <?= $label ?>
                            </a>
                        </li>
                        <?php $isFirst = false; endforeach; ?>
                    </ul>
                </nav>
            </div>

            <div class="sticky-section">
                <!-- ===== 기본정보 ===== -->
                <section id="anc_basic" class="mb-2 pt-2" data-section="anc_basic">
                    <h5 class="mb-3">기본정보</h5>
                    <div class="card mb-4">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-info-circle me-2 text-pastel-blue"></i>게시판 정보
                        </div>
                        <div class="card-body">
                            <div class="row gy-3 gy-md-0 mb-3">
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">그룹 <span class="text-danger">*</span></label>
                                    <select class="form-select" name="formData[group_id]" required>
                                        <option value="">그룹 선택</option>
                                        <?php foreach ($groups ?? [] as $group): ?>
                                        <option value="<?= $group['value'] ?>" <?= ($board['group_id'] ?? $defaultGroupId ?? '') == $group['value'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($group['label']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <a href="/admin/board/group" target="_blank">그룹 관리 <i class="bi bi-box-arrow-up-right"></i></a>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">슬러그 <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text">/board/</span>
                                        <input type="text"
                                               class="form-control"
                                               name="formData[board_slug]"
                                               id="board_slug"
                                               value="<?= htmlspecialchars($board['board_slug'] ?? '') ?>"
                                               pattern="[a-z0-9\-]+"
                                               placeholder="notice"
                                               required
                                               <?= $isEdit && ($articleCount ?? 0) > 0 ? 'readonly' : '' ?>>
                                        <button type="button" class="btn btn-outline-secondary" id="btn-check-slug">
                                            중복확인
                                        </button>
                                    </div>
                                    <div class="form-text">영문 소문자, 숫자, 하이픈(-) 사용 가능</div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">상태</label>
                                    <div class="form-check form-switch mt-2">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               name="formData[is_active]"
                                               id="is_active"
                                               value="1"
                                               <?= ($board['is_active'] ?? true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_active">사용</label>
                                    </div>
                                    <?php if ($isSuper): ?>
                                    <div class="form-check form-switch mt-2">
                                        <input type="checkbox"
                                               class="form-check-input"
                                               name="formData[is_global]"
                                               id="is_global"
                                               value="1"
                                               <?= ($board['is_global'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_global">
                                            전체 공지(모든 도메인 공통)
                                            <i class="bi bi-shield-lock-fill text-danger ms-1" title="Super 관리자 전용"></i>
                                        </label>
                                        <div class="form-text">
                                            활성화 시 모든 도메인의 Front 에서 이 게시판을 볼 수 있습니다. 글 작성/수정/삭제는 게시판 소유 도메인 관리자만 가능합니다.
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="row gy-3 gy-md-0">
                                <div class="col-12 col-md-6">
                                    <label class="form-label">게시판명 <span class="text-danger">*</span></label>
                                    <input type="text"
                                           class="form-control"
                                           name="formData[board_name]"
                                           value="<?= htmlspecialchars($board['board_name'] ?? '') ?>"
                                           placeholder="공지사항"
                                           required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label class="form-label">게시판 설명</label>
                                    <input type="text"
                                           class="form-control"
                                           name="formData[board_description]"
                                           value="<?= htmlspecialchars($board['board_description'] ?? '') ?>"
                                           placeholder="게시판에 대한 간단한 설명 (선택사항)">
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===== 권한설정 ===== -->
                <section id="anc_permission" class="mb-2 pt-2" data-section="anc_permission">
                    <h5 class="mb-3">권한설정</h5>
                    <div class="card mb-4">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-shield-lock me-2 text-pastel-green"></i>접근 권한
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                그룹에서 설정한 권한을 게시판별로 다르게 설정할 수 있습니다.
                            </p>
                            <?php
                            $levelFields = [
                                'list_level'     => ['label' => '목록 보기 레벨', 'default' => 0],
                                'read_level'     => ['label' => '글 읽기 레벨',   'default' => 0],
                                'write_level'    => ['label' => '글쓰기 레벨',    'default' => 1],
                                'comment_level'  => ['label' => '댓글 쓰기 레벨', 'default' => 1],
                                'download_level' => ['label' => '다운로드 레벨',  'default' => 0],
                            ];
                            ?>
                            <div class="row gy-3 gy-md-0">
                                <?php foreach ($levelFields as $field => $info): ?>
                                <div class="col-12 col-sm-6 col-md-4 mb-3">
                                    <label class="form-label"><?= $info['label'] ?></label>
                                    <select class="form-select" name="formData[<?= $field ?>]">
                                        <?php foreach ($levelOptions ?? [] as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($board[$field] ?? $info['default']) == $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-clock-history me-2 text-pastel-purple"></i>레벨별 1일 작성 제한
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                비워두면 해당 레벨은 제한 없이 작성할 수 있습니다. 0으로 설정하면 작성이 금지됩니다.
                            </p>
                            <?php
                            $writeLimit = $board['daily_write_limit'] ?? [];
                            $commentLimit = $board['daily_comment_limit'] ?? [];
                            ?>
                            <div class="row gy-3">
                                <?php foreach ($levelOptions ?? [] as $levelValue => $levelLabel): ?>
                                <div class="col-12 col-sm-6 col-md-3">
                                    <label class="form-label fw-semibold"><?= htmlspecialchars($levelLabel) ?></label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="input-group">
                                                <input type="number" class="form-control"
                                                       name="formData[daily_write_limit][<?= $levelValue ?>]"
                                                       value="<?= $writeLimit[$levelValue] ?? '' ?>"
                                                       min="0" max="9999" placeholder="무제한">
                                                <span class="input-group-text">글</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="input-group">
                                                <input type="number" class="form-control"
                                                       name="formData[daily_comment_limit][<?= $levelValue ?>]"
                                                       value="<?= $commentLimit[$levelValue] ?? '' ?>"
                                                       min="0" max="9999" placeholder="무제한">
                                                <span class="input-group-text">댓글</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===== UI설정 ===== -->
                <section id="anc_ui" class="mb-2 pt-2" data-section="anc_ui">
                    <h5 class="mb-3">UI설정</h5>
                    <div class="card mb-4">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-palette me-2 text-pastel-sky"></i>스킨 · 에디터
                        </div>
                        <div class="card-body">
                            <div class="row gy-3 gy-md-0">
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">게시판 스킨</label>
                                    <select class="form-select" name="formData[board_skin]">
                                        <?php foreach ($skins ?? ['basic' => 'basic'] as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($board['board_skin'] ?? 'basic') === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">에디터</label>
                                    <select class="form-select" name="formData[board_editor]">
                                        <?php foreach ($editors ?? ['mublo-editor' => 'Mublo Editor'] as $value => $label): ?>
                                        <option value="<?= $value ?>" <?= ($board['board_editor'] ?? 'mublo-editor') === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-list-ol me-2 text-pastel-orange"></i>목록 설정
                        </div>
                        <div class="card-body">
                            <div class="row gy-3 gy-md-0">
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">공지글 상단 고정 개수</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="formData[notice_count]"
                                               value="<?= $board['notice_count'] ?? 5 ?>" min="0" max="20">
                                        <span class="input-group-text">개</span>
                                    </div>
                                    <div class="form-text">목록 상단에 고정할 공지글 개수</div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">페이지당 글 수</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="formData[per_page]"
                                               value="<?= $board['per_page'] ?? 0 ?>" min="0" max="100">
                                        <span class="input-group-text">개</span>
                                    </div>
                                    <div class="form-text">0 = 도메인 기본값 사용</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ===== 기능설정 ===== -->
                <section id="anc_feature" class="mb-2 pt-2" data-section="anc_feature">
                    <h5 class="mb-3">기능설정</h5>
                    <div class="card mb-4">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-toggles me-2 text-pastel-blue"></i>기능 사용
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input me-2" name="formData[use_secret]" id="use_secret" value="1" <?= ($board['use_secret'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="use_secret">비밀글 사용</label>
                                    </div>
                                    <div class="form-text">작성자가 개별 글을 비밀글로 선택</div>
                                </div>
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input me-2" name="formData[is_secret_board]" id="is_secret_board" value="1" <?= ($board['is_secret_board'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="is_secret_board">비밀게시판</label>
                                    </div>
                                    <div class="form-text">모든 글이 비밀글로 작성 (1:1 문의 등)</div>
                                </div>
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input me-2" name="formData[use_category]" id="use_category" value="1" <?= ($board['use_category'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="use_category">카테고리 사용</label>
                                    </div>
                                    <div class="form-text">게시글 분류에 카테고리 사용</div>
                                </div>
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input me-2" name="formData[use_comment]" id="use_comment" value="1" <?= ($board['use_comment'] ?? true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="use_comment">댓글 사용</label>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input me-2" name="formData[use_reaction]" id="use_reaction" value="1" <?= ($board['use_reaction'] ?? true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="use_reaction">반응(좋아요 등) 사용</label>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input me-2" name="formData[use_link]" id="use_link" value="1" <?= ($board['use_link'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="use_link">링크 사용</label>
                                    </div>
                                    <div class="form-text">OG 메타 정보 자동 추출</div>
                                </div>
                                <div class="col-sm-6 col-md-4 mb-3">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input me-2" name="formData[use_file]" id="use_file" value="1" <?= ($board['use_file'] ?? true) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="use_file">파일 첨부 사용</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 카테고리 설정 -->
                    <div class="card mb-4" id="category-card" style="<?= ($board['use_category'] ?? false) ? '' : 'display:none;' ?>">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-tags me-2 text-pastel-green"></i>사용 카테고리 설정
                        </div>
                        <div class="card-body">
                            <?php if (!empty($allCategories)): ?>
                            <div class="border rounded p-3">
                                <div class="row g-4">
                                    <?php foreach ($allCategories as $category): ?>
                                    <div class="col-auto">
                                        <div class="form-check">
                                            <input type="checkbox"
                                                class="form-check-input"
                                                name="formData[category_ids][]"
                                                id="category_<?= $category->getCategoryId() ?>"
                                                value="<?= $category->getCategoryId() ?>"
                                                <?= in_array($category->getCategoryId(), $selectedCategoryIds ?? []) ? 'checked' : '' ?>>

                                            <label class="form-check-label"
                                                for="category_<?= $category->getCategoryId() ?>">
                                                <?= htmlspecialchars($category->getCategoryName()) ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-text mt-2">
                                <a href="/admin/board/category" target="_blank">카테고리 관리 <i class="bi bi-box-arrow-up-right"></i></a>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info mb-0">
                                <i class="bi bi-info-circle me-1"></i>
                                등록된 카테고리가 없습니다.
                                <a href="/admin/board/category/create" target="_blank">카테고리 추가</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- 반응 설정 -->
                    <div class="card mb-4" id="reaction-card" style="<?= ($board['use_reaction'] ?? true) ? '' : 'display:none;' ?>">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-emoji-smile me-2 text-pastel-purple"></i>반응 설정
                        </div>
                        <div class="card-body">
                            <div id="reaction-list">
                                <?php
                                $reactionConfig = $board['reaction_config'] ?? [
                                    'like' => ['label' => '좋아요', 'icon' => '👍', 'color' => '#3B82F6', 'enabled' => true]
                                ];
                                $reactionIndex = 0;
                                foreach ($reactionConfig as $key => $config):
                                ?>
                                <div class="reaction-item row g-2 mb-2 align-items-center" data-index="<?= $reactionIndex ?>">
                                    <input type="hidden" name="formData[reaction_config][<?= $reactionIndex ?>][key]" value="<?= htmlspecialchars($key) ?>">
                                    <div class="col-4">
                                        <input type="text" class="form-control form-control-sm"
                                               name="formData[reaction_config][<?= $reactionIndex ?>][label]"
                                               value="<?= htmlspecialchars($config['label'] ?? '') ?>" placeholder="라벨" required>
                                    </div>
                                    <div class="col-4">
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control form-control-sm emoji-input"
                                                   name="formData[reaction_config][<?= $reactionIndex ?>][icon]"
                                                   value="<?= htmlspecialchars($config['icon'] ?? '') ?>" placeholder="이모지">
                                            <button type="button" class="btn btn-outline-secondary btn-emoji-picker" title="이모지 선택">
                                                <i class="bi bi-emoji-smile"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-4 d-flex gap-1">
                                        <input type="color" class="form-control form-control-sm form-control-color p-0"
                                               name="formData[reaction_config][<?= $reactionIndex ?>][color]"
                                               value="<?= htmlspecialchars($config['color'] ?? '#3B82F6') ?>"
                                               style="width: 28px; height: 28px;" title="색상">
                                        <input type="hidden" name="formData[reaction_config][<?= $reactionIndex ?>][enabled]" value="1">
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-remove-reaction" title="삭제">
                                            <i class="bi bi-x"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php $reactionIndex++; endforeach; ?>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="btn-add-reaction">
                                <i class="bi bi-plus-lg me-1"></i>추가
                            </button>
                            <div class="form-text mt-2">라벨: 표시 텍스트 / 이모지: 아이콘</div>
                        </div>
                    </div>
                </section>

                <!-- ===== 파일설정 ===== -->
                <section id="anc_file" class="mb-2 pt-2" data-section="anc_file">
                    <h5 class="mb-3">파일설정</h5>
                    <div class="card mb-4">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-paperclip me-2 text-pastel-sky"></i>첨부파일
                        </div>
                        <div class="card-body">
                            <div class="row gy-3 gy-md-0">
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">첨부 개수 제한</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="formData[file_count_limit]"
                                               value="<?= $board['file_count_limit'] ?? 2 ?>" min="1" max="20">
                                        <span class="input-group-text">개</span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">파일 크기 제한</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="formData[file_size_limit_mb]"
                                               value="<?= isset($board['file_size_limit']) ? round($board['file_size_limit'] / 1048576, 2) : 2 ?>"
                                               min="0.1" max="100" step="0.1">
                                        <span class="input-group-text">MB</span>
                                    </div>
                                </div>
                                <div class="col-12 col-sm-6 col-md-4">
                                    <label class="form-label">허용 확장자</label>
                                    <input type="text" class="form-control" name="formData[file_extension_allowed]"
                                           value="<?= htmlspecialchars($board['file_extension_allowed'] ?? 'jpg,jpeg,png,gif,pdf,zip') ?>"
                                           placeholder="jpg,jpeg,png,gif,pdf,zip">
                                    <div class="form-text">쉼표로 구분</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card mb-4" style="display:none;">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-database me-2 text-pastel-orange"></i>테이블 분리 설정
                        </div>
                        <div class="card-body">
                            <div class="row gy-3 gy-md-0">
                                <div class="col-12 col-sm-6">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input"
                                               name="formData[use_separate_table]" id="use_separate_table" value="1"
                                               <?= ($board['use_separate_table'] ?? false) ? 'checked' : '' ?>
                                               <?= $isEdit ? 'disabled' : '' ?>>
                                        <label class="form-check-label" for="use_separate_table">별도 테이블 사용</label>
                                    </div>
                                    <div class="form-text">대용량 게시판용 (생성 후 변경 불가)</div>
                                </div>
                                <div class="col-12 col-sm-6">
                                    <label class="form-label">테이블명</label>
                                    <input type="text" class="form-control" name="formData[table_name]"
                                           value="<?= htmlspecialchars($board['table_name'] ?? '') ?>"
                                           placeholder="board_articles_notice"
                                           <?= $isEdit ? 'readonly' : '' ?>>
                                    <div class="form-text">비워두면 자동 생성</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>

        <div class="sticky-act mt-3 sticky-status">
            <a href="/admin/board/config" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>목록
            </a>
            <button type="button"
                class="btn btn-primary mublo-submit"
                data-target="/admin/board/config/store"
                data-callback="boardSaved">
                <i class="bi bi-check-lg me-1"></i>저장
            </button>
        </div>
    </div>
</form>

<script>
// 저장 완료 콜백
MubloRequest.registerCallback('boardSaved', function(response) {
    if (response.result === 'success') {
        alert(response.message || '저장되었습니다.');
        if (response.data && response.data.board_id && !document.querySelector('input[name="formData[board_id]"]')) {
            location.href = '/admin/board/config/edit?id=' + response.data.board_id;
        }
    } else {
        alert(response.message || '저장에 실패했습니다.');
    }
});

// 슬러그 중복 확인
document.getElementById('btn-check-slug')?.addEventListener('click', function() {
    const slugInput = document.getElementById('board_slug');
    const slug = slugInput.value.trim();

    if (!slug) {
        alert('슬러그를 입력해주세요.');
        slugInput.focus();
        return;
    }

    if (!/^[a-z0-9-]+$/.test(slug)) {
        alert('슬러그는 영문 소문자, 숫자, 하이픈만 사용 가능합니다.');
        return;
    }

    const excludeId = document.querySelector('input[name="formData[board_id]"]')?.value || 0;

    MubloRequest.requestJson('/admin/board/config/check-slug', {
        slug: slug,
        exclude_id: parseInt(excludeId)
    }).then(response => {
        alert(response.message || '사용 가능한 슬러그입니다.');
    });
});

// 슬러그 입력 시 소문자 변환
document.getElementById('board_slug')?.addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
});

// 별도 테이블 사용 시 테이블명 자동 생성
document.getElementById('use_separate_table')?.addEventListener('change', function() {
    const tableNameInput = document.querySelector('input[name="formData[table_name]"]');
    const slugInput = document.getElementById('board_slug');
    if (this.checked && tableNameInput && !tableNameInput.value && slugInput.value) {
        tableNameInput.value = 'board_articles_' + slugInput.value.replace(/-/g, '_');
    }
});

// 카테고리 사용 토글
document.getElementById('use_category')?.addEventListener('change', function() {
    document.getElementById('category-card').style.display = this.checked ? '' : 'none';
});

// 반응 사용 토글
document.getElementById('use_reaction')?.addEventListener('change', function() {
    document.getElementById('reaction-card').style.display = this.checked ? '' : 'none';
});

// =====================================================
// 이모지 피커
// =====================================================
const EMOJI_CATEGORIES = {
    '반응': ['👍', '👎', '❤️', '💔', '💕', '💖', '💗', '💓', '💘', '💝', '🔥', '💯', '⭐', '✨', '💪', '👏', '🙏', '🎉', '🎊', '✅', '❌', '⚠️', '❗', '❓', '💡', '🔔', '📌', '🏆', '🥇', '🎯'],
    '표정': ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '😊', '😇', '🥰', '😍', '🤩', '😘', '😗', '😚', '😋', '😛', '😜', '🤪', '😎', '🤓', '🧐', '🤔', '🤨', '😐', '😑', '😶', '🙄'],
    '감정': ['😏', '😒', '🙃', '😌', '😔', '😪', '🤤', '😴', '😷', '🤒', '🤕', '🤢', '🤮', '🤧', '🥵', '🥶', '🥴', '😵', '🤯', '😱', '😨', '😰', '😥', '😢', '😭', '😤', '😠', '😡', '🤬', '😈'],
    '손짓': ['👋', '🤚', '🖐️', '✋', '🖖', '👌', '🤌', '🤏', '✌️', '🤞', '🤟', '🤘', '🤙', '👈', '👉', '👆', '👇', '☝️', '👍', '👎', '✊', '👊', '🤛', '🤜', '👐', '🙌', '👏', '🤝', '🙏', '💅'],
    '기타': ['🐶', '🐱', '🍎', '☀️', '💻', '📱', '🎮', '🎧', '🚗', '🏠', '💰', '💎', '🎁', '🎸', '⚽', '🏀']
};

let activeEmojiPicker = null;
let currentEmojiCategory = '반응';

function createEmojiPicker(targetInput) {
    closeEmojiPicker();

    const picker = document.createElement('div');
    picker.className = 'emoji-picker-popup';
    picker.style.cssText = 'position:absolute;z-index:1050;background:var(--bs-body-bg,#fff);border:1px solid var(--bs-border-color,#dee2e6);border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);width:320px;max-height:300px;overflow:hidden;';

    const tabs = document.createElement('div');
    tabs.style.cssText = 'display:flex;flex-wrap:wrap;gap:2px;padding:8px 8px 4px;border-bottom:1px solid var(--bs-border-color,#eee);';
    Object.keys(EMOJI_CATEGORIES).forEach(cat => {
        const tab = document.createElement('button');
        tab.type = 'button';
        tab.className = 'btn btn-sm ' + (cat === currentEmojiCategory ? 'btn-primary' : 'btn-outline-secondary');
        tab.style.cssText = 'font-size:11px;padding:2px 6px;';
        tab.textContent = cat;
        tab.addEventListener('click', (e) => {
            e.stopPropagation();
            currentEmojiCategory = cat;
            tabs.querySelectorAll('button').forEach(b => b.className = 'btn btn-sm btn-outline-secondary');
            tab.className = 'btn btn-sm btn-primary';
            renderEmojis(grid, cat, targetInput);
        });
        tabs.appendChild(tab);
    });
    picker.appendChild(tabs);

    const grid = document.createElement('div');
    grid.style.cssText = 'display:grid;grid-template-columns:repeat(10,1fr);gap:2px;padding:8px;max-height:200px;overflow-y:auto;';
    renderEmojis(grid, currentEmojiCategory, targetInput);
    picker.appendChild(grid);

    const rect = targetInput.getBoundingClientRect();
    picker.style.top = (rect.bottom + window.scrollY + 4) + 'px';
    picker.style.left = Math.min(rect.left + window.scrollX, window.innerWidth - 330) + 'px';

    document.body.appendChild(picker);
    activeEmojiPicker = picker;
    setTimeout(() => document.addEventListener('click', closeEmojiPickerOnClickOutside), 0);
}

function renderEmojis(container, category, targetInput) {
    container.innerHTML = '';
    (EMOJI_CATEGORIES[category] || []).forEach(emoji => {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-light btn-sm p-1';
        btn.style.cssText = 'font-size:18px;line-height:1;';
        btn.textContent = emoji;
        btn.addEventListener('click', () => { targetInput.value = emoji; closeEmojiPicker(); });
        container.appendChild(btn);
    });
}

function closeEmojiPicker() {
    if (activeEmojiPicker) {
        activeEmojiPicker.remove();
        activeEmojiPicker = null;
        document.removeEventListener('click', closeEmojiPickerOnClickOutside);
    }
}

function closeEmojiPickerOnClickOutside(e) {
    if (activeEmojiPicker && !activeEmojiPicker.contains(e.target) && !e.target.closest('.btn-emoji-picker')) {
        closeEmojiPicker();
    }
}

// 이모지 피커 버튼 (이벤트 위임)
document.getElementById('reaction-list')?.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-emoji-picker');
    if (btn) {
        e.stopPropagation();
        const input = btn.closest('.input-group').querySelector('.emoji-input');
        if (input) createEmojiPicker(input);
    }
});

// 반응 추가
let reactionIndex = document.querySelectorAll('.reaction-item').length;

document.getElementById('btn-add-reaction')?.addEventListener('click', function() {
    const reactionList = document.getElementById('reaction-list');
    const newItem = document.createElement('div');
    newItem.className = 'reaction-item row g-2 mb-2 align-items-center';
    newItem.dataset.index = reactionIndex;
    const autoKey = 'r_' + Math.random().toString(36).substring(2, 10);
    newItem.innerHTML = `
        <input type="hidden" name="formData[reaction_config][${reactionIndex}][key]" value="${autoKey}">
        <div class="col-4"><input type="text" class="form-control form-control-sm" name="formData[reaction_config][${reactionIndex}][label]" placeholder="라벨" required></div>
        <div class="col-4"><div class="input-group input-group-sm"><input type="text" class="form-control form-control-sm emoji-input" name="formData[reaction_config][${reactionIndex}][icon]" placeholder="이모지"><button type="button" class="btn btn-outline-secondary btn-emoji-picker" title="이모지 선택"><i class="bi bi-emoji-smile"></i></button></div></div>
        <div class="col-4 d-flex gap-1"><input type="color" class="form-control form-control-sm form-control-color p-0" name="formData[reaction_config][${reactionIndex}][color]" value="#3B82F6" style="width:28px;height:28px;" title="색상"><input type="hidden" name="formData[reaction_config][${reactionIndex}][enabled]" value="1"><button type="button" class="btn btn-outline-danger btn-sm btn-remove-reaction" title="삭제"><i class="bi bi-x"></i></button></div>
    `;
    reactionList.appendChild(newItem);
    reactionIndex++;
});

// 반응 삭제 (이벤트 위임)
document.getElementById('reaction-list')?.addEventListener('click', function(e) {
    const btn = e.target.closest('.btn-remove-reaction');
    if (btn) {
        if (document.querySelectorAll('.reaction-item').length <= 1) {
            alert('최소 1개의 반응은 필요합니다.');
            return;
        }
        btn.closest('.reaction-item').remove();
    }
});
</script>
