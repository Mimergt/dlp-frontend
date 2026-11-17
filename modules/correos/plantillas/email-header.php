<?php
if (!defined('ABSPATH')) { exit; }
echo dlp_fe_mail_open(isset($email_heading) ? $email_heading : ''); // phpcs:ignore WordPress.Security.EscapeOutput
