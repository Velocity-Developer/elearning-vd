<?php

defined('ABSPATH') || exit;

final class ELVD
{
    public const VERSION = '1.0.0';
    public const REST_NAMESPACE = 'elvd/v1';
    public const TEXT_DOMAIN = 'elearning-vd';
    public const PAGE_TEMPLATE = 'templates/page-elearning.php';

    public static function plugin_file(): string
    {
        return ELVD_PLUGIN_FILE;
    }

    public static function plugin_dir(): string
    {
        return plugin_dir_path(self::plugin_file());
    }

    public static function plugin_url(): string
    {
        return plugin_dir_url(self::plugin_file());
    }

    public static function templates_dir(): string
    {
        return self::plugin_dir() . 'templates/';
    }
}
