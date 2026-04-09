<?php
/**
 * 테마 설정 섹션
 *
 * Front 프레임: views/Front/frame/{스킨명}/
 * Front 콘텐츠: views/Front/{Group}/{스킨명}/
 *
 * @var array $skinOptions 컴포넌트별 스킨 목록
 */

// 스킨 select 옵션 출력 헬퍼
$renderSkinOptions = function(string $component) use ($skinOptions): string {
    $skins = $skinOptions[$component] ?? ['basic'];
    $html = '';
    foreach ($skins as $skin) {
        $html .= '<option value="' . htmlspecialchars($skin) . '">' . htmlspecialchars($skin) . '</option>';
    }
    return $html;
};
?>
<!-- Admin 스킨 -->
<div class="row">
    <!-- 관리자 스킨 카드 -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card h-100">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-gear me-2 text-pastel-blue"></i>관리자 스킨
            </div>
            <div class="card-body">
                <label for="theme_admin" class="form-label">Admin 스킨</label>
                <select name="formData[theme][admin]" id="theme_admin" class="form-select">
                    <?= $renderSkinOptions('admin') ?>
                </select>
                <small class="text-muted">views/Admin/{스킨명}/</small>
            </div>
        </div>
    </div>

    <!-- 프레임 스킨 카드 -->
    <div class="col-12 col-lg-6 mb-4">
        <div class="card h-100">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-layout-text-window me-2 text-pastel-green"></i>프레임 스킨 (Head, Header, Layout, Footer, Foot)
            </div>
            <div class="card-body">
                <label for="theme_frame" class="form-label">Frame 스킨</label>
                <select name="formData[theme][frame]" id="theme_frame" class="form-select">
                    <?= $renderSkinOptions('frame') ?>
                </select>
                <small class="text-muted">views/Front/frame/{스킨명}/</small>
            </div>
        </div>
    </div>
</div>

<!-- Front 콘텐츠 스킨 설정 -->
<div class="card mb-4">
    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
        <i class="bi bi-palette me-2 text-pastel-purple"></i>프론트 콘텐츠 스킨 설정
    </div>
    <div class="card-body">
        <div class="row gy-3 gy-md-0 mb-3">
            <div class="col-12 col-sm-6 col-md-4">
                <label for="theme_index" class="form-label">Index 스킨</label>
                <select name="formData[theme][index]" id="theme_index" class="form-select">
                    <?= $renderSkinOptions('index') ?>
                </select>
                <small class="text-muted">views/Front/Index/{스킨명}/</small>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label for="theme_member" class="form-label">Member 스킨</label>
                <select name="formData[theme][member]" id="theme_member" class="form-select">
                    <?= $renderSkinOptions('member') ?>
                </select>
                <small class="text-muted">views/Front/Member/{스킨명}/</small>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label for="theme_auth" class="form-label">Auth 스킨</label>
                <select name="formData[theme][auth]" id="theme_auth" class="form-select">
                    <?= $renderSkinOptions('auth') ?>
                </select>
                <small class="text-muted">views/Front/Auth/{스킨명}/</small>
            </div>
        </div>
        <div class="row gy-3 gy-md-0">
            <div class="col-12 col-sm-6 col-md-4">
                <label for="theme_policy" class="form-label">Policy 스킨</label>
                <select name="formData[theme][policy]" id="theme_policy" class="form-select">
                    <?= $renderSkinOptions('policy') ?>
                </select>
                <small class="text-muted">views/Front/Policy/{스킨명}/</small>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label for="theme_mypage" class="form-label">Mypage 스킨</label>
                <select name="formData[theme][mypage]" id="theme_mypage" class="form-select">
                    <?= $renderSkinOptions('mypage') ?>
                </select>
                <small class="text-muted">views/Front/Mypage/{스킨명}/</small>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label for="theme_search" class="form-label">Search 스킨</label>
                <select name="formData[theme][search]" id="theme_search" class="form-select">
                    <?= $renderSkinOptions('search') ?>
                </select>
                <small class="text-muted">views/Front/Search/{스킨명}/</small>
            </div>
        </div>
    </div>
</div>
