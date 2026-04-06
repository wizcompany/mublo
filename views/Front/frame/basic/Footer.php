<?php
/**
 * Front Footer (basic frame skin)
 *
 * 섹션 순서:
 * 1. footer-nav   — 푸터 메뉴 (이용약관, 개인정보처리방침 등)
 * 2. footer-head  — 왼쪽: 로고 + SNS + 고객센터 / 오른쪽: 회사 정보
 * 3. footer-bar   — 카피라이트
 *
 * @var array $siteConfig    사이트 설정 (site_title 등)
 * @var array $companyConfig 회사 정보 (name, owner, tel, email, business_number, tongsin_number, zipcode, address, address_detail)
 * @var array $seoConfig     SEO/SNS 설정 (sns_channels 등)
 * @var array $footerMenus   푸터 메뉴 목록 [{label, url, target}]
 * @var array $siteImages    사이트 이미지 URLs (logo_pc 등)
 * @var array $csInfo        고객센터 정보 (tel, time, email) — 패키지가 오버라이드 가능
 */

use Mublo\Helper\Sns\SnsHelper;

$year     = date('Y');
$siteName = htmlspecialchars($siteConfig['site_title'] ?? '');
$logoUrl  = $siteImages['logo_pc'] ?? '';

$activeChannels = SnsHelper::filterActiveChannels($seoConfig['sns_channels'] ?? []);

$addressParts = array_filter([
    !empty($companyConfig['zipcode']) ? '(' . $companyConfig['zipcode'] . ')' : '',
    $companyConfig['address'] ?? '',
    $companyConfig['address_detail'] ?? '',
]);
$fullAddress = implode(' ', $addressParts);

$csTel   = $csInfo['tel'] ?? $companyConfig['tel'] ?? '';
$csTime  = $csInfo['time'] ?? '';
$csEmail = $csInfo['email'] ?? $companyConfig['email'] ?? '';

$hasCs = !empty($csTel) || !empty($csTime);

$hasInfo = !empty($companyConfig['owner'])
    || !empty($companyConfig['business_number'])
    || !empty($companyConfig['tongsin_number'])
    || !empty($fullAddress)
    || !empty($csEmail);

$hasBrand = $logoUrl || $siteName;

// 푸터 메뉴 visibility 필터링
$isLogin = !empty($currentMember ?? null);
$footerMenus = array_filter($footerMenus ?? [], function ($item) use ($isLogin) {
    $vis = $item['visibility'] ?? 'all';
    if ($vis === 'guest') return !$isLogin;
    if ($vis === 'member') return $isLogin;
    return true;
});
?>
<footer class="mublo-footer">
    <div class="mublo-container">

        <?php if (!empty($footerMenus)): ?>
        <nav class="footer-nav" aria-label="푸터 메뉴">
            <?php foreach ($footerMenus as $menu): ?>
            <a href="<?= htmlspecialchars($menu['url'] ?? '#') ?>"
               <?= !empty($menu['target']) && $menu['target'] === '_blank' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <?= htmlspecialchars($menu['label'] ?? '') ?>
            </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <div class="footer-body">
            <div class="footer-left">
                <?php if ($hasBrand): ?>
                <div class="footer-brand">
                    <?php if ($logoUrl): ?>
                    <a href="/" class="footer-logo">
                        <img src="<?= htmlspecialchars($logoUrl) ?>" alt="<?= $siteName ?>">
                    </a>
                    <?php elseif ($siteName): ?>
                    <a href="/" class="footer-logo-text"><?= $siteName ?></a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if ($hasInfo): ?>
                <div class="footer-info">
                    <?php if (!empty($companyConfig['name'])): ?>
                    <span class="footer-info-company"><?= htmlspecialchars($companyConfig['name']) ?></span>
                    <?php endif; ?>
                    <div class="footer-info-line">
                        <?php if (!empty($companyConfig['owner'])): ?>
                        <span><em>대표</em> <?= htmlspecialchars($companyConfig['owner']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($companyConfig['business_number'])): ?>
                        <span><em>사업자등록번호</em> <?= htmlspecialchars($companyConfig['business_number']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($companyConfig['tongsin_number'])): ?>
                        <span><em>통신판매업</em> <?= htmlspecialchars($companyConfig['tongsin_number']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($fullAddress)): ?>
                    <div class="footer-info-line">
                        <span><em>주소</em> <?= htmlspecialchars($fullAddress) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="footer-info-line">
                        <?php if (!empty($csEmail)): ?>
                        <span><em>이메일</em>
                            <a href="mailto:<?= htmlspecialchars($csEmail) ?>">
                                <?= htmlspecialchars($csEmail) ?>
                            </a>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($csTel)): ?>
                        <span><em>전화</em>
                            <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9\-]/', '', $csTel)) ?>">
                                <?= htmlspecialchars($csTel) ?>
                            </a>
                        </span>
                        <?php endif; ?>
                        <?php if (!empty($companyConfig['fax'])): ?>
                        <span><em>팩스</em> <?= htmlspecialchars($companyConfig['fax']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="footer-right">
                <?php if (!empty($activeChannels)): ?>
                <div class="footer-sns">
                    <?php foreach ($activeChannels as $ch):
                        $type  = $ch['type'] ?? '';
                        $url   = $ch['url'] ?? '';
                        if (!$type || !$url) continue;
                        $svg   = SnsHelper::getSvg($type);
                        $label = SnsHelper::getLabel($type);
                        $color = SnsHelper::getColor($type);
                    ?>
                    <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener noreferrer"
                       class="footer-sns-btn" aria-label="<?= htmlspecialchars($label) ?>"
                       style="--sns-color:<?= htmlspecialchars($color) ?>">
                        <?= $svg ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if ($hasCs): ?>
                <div class="footer-cs">
                    <?php if (!empty($csTel)): ?>
                    <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9\-]/', '', $csTel)) ?>" class="footer-cs-tel">
                        <?= htmlspecialchars($csTel) ?>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($csTime)): ?>
                    <div class="footer-cs-time"><?= nl2br(htmlspecialchars($csTime)) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($csInfo['ict_mark'])): ?>
                <div class="footer-ict-mark"><?= $csInfo['ict_mark'] ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer-bar">
            <p class="footer-copy">&copy; <?= $year ?><?= $siteName ? ' ' . $siteName . '.' : '' ?> All Rights Reserved.</p>
        </div>

    </div>
</footer>
