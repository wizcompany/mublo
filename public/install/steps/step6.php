<?php
/**
 * Step 6: 설치 완료
 */

// 모든 설정이 완료되었는지 확인
if (!isset($_SESSION['db_config']) || !isset($_SESSION['domain_data']) || !isset($_SESSION['admin_data']) || !isset($_SESSION['security_data'])) {
    header('Location: ?step=5');
    exit;
}

// 설치 완료 처리
if (!$installer->isInstalled()) {
    $installer->finishInstallation();
}

$dbConfig = $_SESSION['db_config'];
$adminData = $_SESSION['admin_data'];
$domainData = $_SESSION['domain_data'];
$securityData = $_SESSION['security_data'];
$migrationResult = $_SESSION['migration_result'] ?? ['files' => []];
?>

<div class="content">
    <h2>Step 6. 설치 완료</h2>
    <p>Mublo Framework 설치가 성공적으로 완료되었습니다.</p>

    <div class="alert alert-success">
        <strong>설치 성공!</strong><br>
        모든 설정이 완료되었습니다. 이제 사이트를 사용할 수 있습니다.
    </div>

    <h3>설치 요약</h3>

    <ul class="check-list">
        <li class="check-item ok">
            <div class="icon">V</div>
            <div class="info">
                <strong>데이터베이스 설정</strong>
                <span><?= htmlspecialchars($dbConfig['database']) ?> @ <?= htmlspecialchars($dbConfig['host']) ?></span>
            </div>
        </li>

        <li class="check-item ok">
            <div class="icon">V</div>
            <div class="info">
                <strong>테이블 생성</strong>
                <span><?= count($migrationResult['files']) ?>개 마이그레이션 파일 실행 완료</span>
            </div>
        </li>

        <li class="check-item ok">
            <div class="icon">V</div>
            <div class="info">
                <strong>도메인 설정</strong>
                <span><?= htmlspecialchars($domainData['domain_name']) ?> - <?= htmlspecialchars($domainData['site_title']) ?></span>
            </div>
        </li>

        <li class="check-item ok">
            <div class="icon">V</div>
            <div class="info">
                <strong>관리자 계정</strong>
                <span><?= htmlspecialchars($adminData['user_id']) ?></span>
            </div>
        </li>

        <li class="check-item ok">
            <div class="icon">V</div>
            <div class="info">
                <strong>보안 설정</strong>
                <span>암호화 키, CSRF 토큰, 해시 비용 설정 완료</span>
            </div>
        </li>

        <li class="check-item ok">
            <div class="icon">V</div>
            <div class="info">
                <strong>설정 파일 생성</strong>
                <span>config/database.php, config/app.php, config/security.php</span>
            </div>
        </li>

        <li class="check-item ok">
            <div class="icon">V</div>
            <div class="info">
                <strong>기본 패키지</strong>
                <span>게시판 패키지 설치됨 (공지사항, 자유게시판 생성)</span>
            </div>
        </li>

        <li class="check-item ok">
            <div class="icon">V</div>
            <div class="info">
                <strong>설치 완료</strong>
                <span>storage/installed.lock 생성됨</span>
            </div>
        </li>
    </ul>

    <h3>보안 조치 (필수)</h3>

    <div class="alert alert-warning">
        <strong>중요: 보안을 위해 다음 작업을 수행하세요</strong><br><br>

        <strong>1. 설치 디렉토리 삭제 (필수)</strong><br>
        설치가 완료되었으므로 <code>public/install</code> 디렉토리를 삭제하세요.<br>
        <pre>
# 윈도우 (명령 프롬프트)
rmdir /s /q public\install

# 리눅스/맥 (터미널)
rm -rf public/install</pre>

        <strong>2. 설정 파일 권한 변경 (권장)</strong><br>
        설정 파일을 읽기 전용으로 변경하여 보안을 강화하세요.<br>
        <pre>
# 리눅스/맥 (터미널)
chmod 444 config/database.php
chmod 444 config/app.php
chmod 444 config/security.php
chmod 444 storage/installed.lock</pre>
    </div>

    <h3>다음 단계</h3>

    <div class="alert alert-info">
        <strong>이제 무엇을 할 수 있나요?</strong><br><br>

        <strong>1. 관리자 페이지 접속</strong><br>
        - URL: <code><?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] ?? 'http') ?>://<?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/admin</code><br>
        - 아이디: <code><?= htmlspecialchars($adminData['user_id']) ?></code><br>
        - 비밀번호: 설정한 비밀번호<br><br>

        <strong>2. 게시판 사용</strong><br>
        - 공지사항(<code>/board/notice</code>), 자유게시판(<code>/board/free</code>) 기본 제공<br>
        - 관리자 페이지에서 게시판 추가/수정/삭제 가능<br><br>

        <strong>3. 사이트 설정</strong><br>
        - 메뉴 구성, 회원 등급 설정 등<br>
        - 플러그인 설치 및 패키지 추가<br>
        - 스킨 및 템플릿 커스터마이징<br><br>

        <strong>4. 문서 참고</strong><br>
        - 프레임워크 문서: <code>docs/</code> 디렉토리 참고<br>
        - 개발자 가이드, API 레퍼런스 등 제공
    </div>

    <div class="button-group">
        <a href="/" class="btn btn-secondary">메인 페이지</a>
        <a href="/admin" class="btn btn-success">관리자 페이지</a>
    </div>
</div>

<script>
// 세션 정리 (보안)
<?php
// 민감한 정보 제거
unset($_SESSION['db_config']['password']);
unset($_SESSION['admin_data']['password']);
unset($_SESSION['admin_data']['password_confirm']);
unset($_SESSION['security_data']);
?>
</script>
