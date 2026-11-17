<?php
if (!defined('ABSPATH')) { exit; }
list($dlp_chip, $dlp_icon) = dlp_fe_mail_estado(isset($email) ? $email : null);
echo dlp_fe_mail_open(isset($email_heading) ? $email_heading : '', $dlp_chip, $dlp_icon); // phpcs:ignore WordPress.Security.EscapeOutput
