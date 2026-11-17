<?php
if (!defined('ABSPATH')) { exit; }
$lost = wc_get_endpoint_url('lost-password', '', wc_get_page_permalink('myaccount'));
?>
<div class="dlprc">
    <div class="dlprc-hero"><h1>Revisa tu correo</h1><p>Te enviamos las instrucciones para crear tu nueva contraseña.</p></div>
    <div class="dlprc-form dlprc-sent">
        <div class="dlprc-ico" aria-hidden="true"><svg viewBox="0 0 64 64" fill="none" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><rect x="7" y="15" width="50" height="34" rx="4"/><path d="M7 20l25 19 25-19"/><circle cx="48" cy="48" r="10" fill="#111"/><path d="M43 48l4 4 7-8"/></svg></div>
        <p class="dlprc-txt">Si el correo pertenece a una cuenta, en unos minutos te llega un enlace para elegir una contraseña nueva. Si no lo ves, revisa la carpeta de spam o correo no deseado.</p>
        <a class="dlprc-go" href="<?php echo esc_url(dlp_fe_rc_login_url()); ?>">Volver a ingresar</a>
        <a class="dlprc-back" href="<?php echo esc_url($lost); ?>">Usar otro correo</a>
    </div>
</div>
