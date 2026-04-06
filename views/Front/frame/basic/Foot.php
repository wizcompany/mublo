<?php
/**
 * Front Foot Partial (basic skin)
 *
 * JS 실행, </body></html>
 */
$customBody = trim($seoConfig['custom_body_script'] ?? '');
?>

<!-- AOS (Animate On Scroll) -->
<script src="/assets/lib/aos/2/dist/aos.js"></script>
<script>AOS.init();</script>

<?php if ($customBody): ?>
<?= $customBody ?>
<?php endif; ?>

<!-- MUBLO_JS -->
</body>
</html>
