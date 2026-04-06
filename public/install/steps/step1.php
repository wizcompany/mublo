<?php
/**
 * Step 1: 환경 체크
 */

$checks = $installer->checkEnvironment();
$canInstall = $installer->canInstall();
?>

<div class="content">
    <h2>Step 1. 환경 체크</h2>
    <p>서버 환경이 Mublo Framework 요구사항을 충족하는지 확인합니다.</p>

    <!-- PHP 버전 체크 -->
    <h3>PHP 버전</h3>
    <ul class="check-list">
        <li class="check-item <?= $checks['php_version']['status'] === 'OK' ? 'ok' : 'fail' ?>">
            <div class="icon"><?= $checks['php_version']['status'] === 'OK' ? '✓' : '✗' ?></div>
            <div class="info">
                <strong>PHP <?= $checks['php_version']['current'] ?></strong>
                <span><?= $checks['php_version']['message'] ?></span>
            </div>
        </li>
    </ul>

    <!-- 필수 확장 모듈 -->
    <h3>필수 확장 모듈</h3>
    <ul class="check-list">
        <?php foreach ($checks['extensions']['required'] as $ext => $info): ?>
        <li class="check-item <?= $info['status'] === 'OK' ? 'ok' : 'fail' ?>">
            <div class="icon"><?= $info['status'] === 'OK' ? '✓' : '✗' ?></div>
            <div class="info">
                <strong><?= $ext ?></strong>
                <span><?= $info['message'] ?></span>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- 권장 확장 모듈 -->
    <h3>권장 확장 모듈</h3>
    <ul class="check-list">
        <?php foreach ($checks['extensions']['recommended'] as $ext => $info): ?>
        <li class="check-item <?= $info['status'] === 'OK' ? 'ok' : 'warning' ?>">
            <div class="icon"><?= $info['status'] === 'OK' ? '✓' : '!' ?></div>
            <div class="info">
                <strong><?= $ext ?></strong>
                <span><?= $info['message'] ?></span>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- 디렉토리 권한 -->
    <h3>디렉토리 권한</h3>
    <ul class="check-list">
        <?php foreach ($checks['permissions'] as $label => $info): ?>
        <li class="check-item <?= $info['status'] === 'OK' ? 'ok' : 'fail' ?>">
            <div class="icon"><?= $info['status'] === 'OK' ? '✓' : '✗' ?></div>
            <div class="info">
                <strong><?= $label ?></strong>
                <span><?= $info['message'] ?> - <?= $info['path'] ?></span>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$canInstall): ?>
    <div class="alert alert-error" style="margin-top: 20px;">
        <strong>설치를 진행할 수 없습니다</strong><br>
        위에 표시된 필수 요구사항을 충족한 후 페이지를 새로고침하세요.
    </div>
    <?php endif; ?>

    <div class="button-group">
        <button class="btn btn-secondary" onclick="location.reload()">다시 체크</button>
        <button class="btn btn-primary" onclick="location.href='?step=2'" <?= !$canInstall ? 'disabled' : '' ?>>
            다음 단계 →
        </button>
    </div>
</div>
