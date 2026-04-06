<?php
/**
 * Block Shared Title Partial
 *
 * 모든 블록 스킨에서 공유하는 타이틀 영역
 *
 * 스킨에서 include $titlePartial; 로 사용합니다.
 * 커스텀 타이틀이 필요하면 views/Block/{type}/{skin}/title.php를 만들면
 * 자동으로 해당 파일이 우선 적용됩니다.
 *
 * @var array $titleConfig 타이틀 설정
 */
$hasTitleImage = !empty($titleConfig['pc_image']);
$hasTitleText = !empty($titleConfig['show']) && !empty($titleConfig['text']);

if ($hasTitleImage || $hasTitleText):
    $titlePosition = $titleConfig['position'] ?? 'left';

    // 제목 텍스트 스타일
    $titleTextStyles = [];
    if (!empty($titleConfig['color'])) $titleTextStyles[] = 'color: ' . $titleConfig['color'];
    if (!empty($titleConfig['size_pc'])) $titleTextStyles[] = '--title-size-pc: ' . (int)$titleConfig['size_pc'] . 'px';
    if (!empty($titleConfig['size_mo'])) $titleTextStyles[] = '--title-size-mo: ' . (int)$titleConfig['size_mo'] . 'px';

    // 문구 스타일
    $copytextStyles = [];
    if (!empty($titleConfig['copytext_color'])) $copytextStyles[] = 'color: ' . $titleConfig['copytext_color'];
    if (!empty($titleConfig['copytext_size_pc'])) $copytextStyles[] = '--copytext-size-pc: ' . (int)$titleConfig['copytext_size_pc'] . 'px';
    if (!empty($titleConfig['copytext_size_mo'])) $copytextStyles[] = '--copytext-size-mo: ' . (int)$titleConfig['copytext_size_mo'] . 'px';
?>
<div class="block-title block-title--<?= $titlePosition ?>">
    <?php if ($hasTitleImage):
        $pcImage = htmlspecialchars($titleConfig['pc_image']);
        $moImage = !empty($titleConfig['mo_image']) ? htmlspecialchars($titleConfig['mo_image']) : '';
        $titleAlt = htmlspecialchars($titleConfig['text'] ?? '');
    ?>
    <div class="block-title__image">
        <img src="<?= $pcImage ?>" alt="<?= $titleAlt ?>" class="block-title__img block-title__img--pc">
        <?php if ($moImage && $moImage !== $pcImage): ?>
        <img src="<?= $moImage ?>" alt="<?= $titleAlt ?>" class="block-title__img block-title__img--mo">
        <?php endif; ?>
    </div>
    <?php elseif ($hasTitleText): ?>
    <h3 class="block-title__text"<?= !empty($titleTextStyles) ? ' style="' . implode('; ', $titleTextStyles) . '"' : '' ?>><?= htmlspecialchars($titleConfig['text']) ?></h3>
    <?php endif; ?>
    <?php if (!empty($titleConfig['more_url'])): ?>
    <a href="<?= htmlspecialchars($titleConfig['more_url']) ?>" class="block-title__more">더보기</a>
    <?php endif; ?>
    <?php if (!empty($titleConfig['copytext'])): ?>
    <p class="block-title__copytext"<?= !empty($copytextStyles) ? ' style="' . implode('; ', $copytextStyles) . '"' : '' ?>><?= htmlspecialchars($titleConfig['copytext']) ?></p>
    <?php endif; ?>
</div>
<?php endif; ?>
