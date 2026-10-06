<?php
if (!defined('ABSPATH')) {
    exit;
}
$o = function ($k) { return DLP_FE_Registry::setting('paginas-extra', $k); };
get_header();
?>
<div id="main-content"><div class="dlpx-404box">
    <div class="dlpx-404n">404</div>
    <h1><?php echo esc_html($o('titulo_404')); ?></h1>
    <p><?php echo esc_html($o('texto_404')); ?></p>
    <a class="dlpx-btn" href="<?php echo esc_url(home_url('/')); ?>">Ir al menú</a>
    <?php $loc = get_page_by_path('restaurantes'); if ($loc) : ?><a class="dlpx-btn o" href="<?php echo esc_url(get_permalink($loc)); ?>">Ver ubicaciones</a><?php endif; ?>
</div></div>
<?php
get_footer();
