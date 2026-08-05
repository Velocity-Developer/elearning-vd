<?php

defined('ABSPATH') || exit;

final class ELVD
{
    public const VERSION = ELVD_PLUGIN_VERSION;
    public const REST_NAMESPACE = 'elvd/v1';
    public const TEXT_DOMAIN = 'elearning-vd';
    public const PAGE_TEMPLATE = 'templates/page-elearning.php';
    public const APP_PAGE_QUERY_VAR = 'elvd_app_page';
    public const APP_PAGE_TITLE = 'Elearning';
    public const APP_PAGE_SLUG = 'elearning';
    public const APP_SHORTCODE = '[elvd_app]';
    public const OPTION_GROUP = 'elvd_settings';
    public const OPTION_GROUP_SISWA_PROFILE = 'elvd_siswa_profile_settings';
    public const OPTION_SCHOOL_NAME = 'elvd_school_name';
    public const OPTION_SCHOOL_LOGO_ID = 'elvd_school_logo_id';
    public const OPTION_ELEARNING_PAGE_ID = 'elvd_elearning_page_id';
    public const OPTION_SISWA_PROFILE_FIELDS = 'elvd_siswa_profile_fields';
    public const ADMIN_MENU_SLUG = 'elvd-dashboard';
    public const SETTINGS_MENU_SLUG = 'elvd-settings';

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

    public static function app_route_path(): string
    {
        $page_id = absint(get_option(self::OPTION_ELEARNING_PAGE_ID, 0));

        if ($page_id > 0) {
            $page_uri = get_page_uri($page_id);

            if (is_string($page_uri) && '' !== $page_uri) {
                return trim($page_uri, '/');
            }
        }

        return self::APP_PAGE_SLUG;
    }
}
