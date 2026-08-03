<?php

defined('ABSPATH') || exit;

echo ElearningVD\Profiles\Siswa::render_current_user_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
