<?php

defined('ABSPATH') || exit;

final class ELVD
{
    public const VERSION = '1.0.0';
    public const REST_NAMESPACE = 'elvd/v1';
    public const TEXT_DOMAIN = 'elearning-vd';
    public const PAGE_TEMPLATE = 'templates/page-elearning.php';
    public const APP_PAGE_TITLE = 'Elearning';
    public const APP_PAGE_SLUG = 'elearning';
    public const APP_SHORTCODE = '[elvd_app]';
    public const OPTION_GROUP = 'elvd_settings';
    public const OPTION_SCHOOL_NAME = 'elvd_school_name';
    public const OPTION_SCHOOL_LOGO_ID = 'elvd_school_logo_id';
    public const OPTION_ELEARNING_PAGE_ID = 'elvd_elearning_page_id';
    public const ADMIN_MENU_SLUG = 'elvd-settings';

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

    public static function app_route(): string
    {
        $page_id = absint(get_option(self::OPTION_ELEARNING_PAGE_ID, 0));

        if ($page_id > 0) {
            $permalink = get_permalink($page_id);

            if (false !== $permalink) {
                return $permalink;
            }
        }

        return home_url('/' . self::APP_PAGE_SLUG . '/');
    }
}
