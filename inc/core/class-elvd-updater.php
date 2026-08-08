<?php

defined('ABSPATH') || exit;

final class ELVD_Updater
{
    private const GITHUB_REPO = 'Velocity-Developer/elearning-vd';
    private const API_URL = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
    private const OPTION_KEY = 'elvd_updater_last_check';
    private const CACHE_DURATION = 12 * HOUR_IN_SECONDS;

    public static function register(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'inject_update']);
        add_filter('plugins_api', [__CLASS__, 'plugin_info'], 10, 3);
    }

    public static function inject_update(object $transient): object
    {
        if (!property_exists($transient, 'response') || !is_array($transient->response)) {
            return $transient;
        }

        $remote = self::get_remote_version();

        if (is_wp_error($remote)) {
            return $transient;
        }

        $remote_version = $remote['version'];

        if (version_compare($remote_version, ELVD_VERSION, '<=')) {
            return $transient;
        }

        $transient->response[plugin_basename(ELVD_PLUGIN_FILE)] = (object) [
            'new_version'      => $remote_version,
            'url'              => $remote['homepage'],
            'package'          => $remote['download_url'],
            'slug'             => 'elearning-vd',
            'requires'         => '6.0',
            'requires_php'     => '7.4',
            'tested'           => get_bloginfo('version'),
            'compatibility'    => '',
            'upgrade_notice'   => '',
            'name'             => 'Elearning VD',
        ];

        return $transient;
    }

    public static function plugin_info(object $result, string $action, object $args): object
    {
        if ('plugin_information' !== $action) {
            return $result;
        }

        if (!isset($args->slug) || 'elearning-vd' !== $args->slug) {
            return $result;
        }

        $remote = self::get_remote_version();

        if (is_wp_error($remote)) {
            return $result;
        }

        return (object) [
            'name'            => 'Elearning VD',
            'slug'            => 'elearning-vd',
            'version'         => $remote['version'],
            'author'          => 'Velocity Developer',
            'author_homepage' => 'https://velocitydeveloper.com/',
            'homepage'        => $remote['homepage'],
            'download_url'    => $remote['download_url'],
            'short_description' => $remote['description'],
            'sections'        => [
                'description' => $remote['body'],
                'changelog'   => $remote['body'],
            ],
            'download_link'   => $remote['download_url'],
            'requires'        => '6.0',
            'requires_php'    => '7.4',
            'tested'          => get_bloginfo('version'),
            'last_updated'    => $remote['date'],
            'active_installs' => '',
            'banners'         => [],
        ];
    }

    private static function get_remote_version(): array|WP_Error
    {
        $cached = get_transient(self::OPTION_KEY);

        if (is_array($cached)) {
            return $cached;
        }

        $response = wp_remote_get(self::API_URL, [
            'timeout'    => 15,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
            'headers'    => [
                'Accept' => 'application/vnd.github.v3+json',
            ],
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if (200 !== $code) {
            return new WP_Error('github_api_error', sprintf('GitHub API error: %d', $code));
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!is_array($body) || !isset($body['tag_name'])) {
            return new WP_Error('github_parse_error', 'Invalid GitHub release response');
        }

        $version = ltrim($body['tag_name'], 'v');

        $download_url = '';
        $description  = '';

        if (!empty($body['assets']) && is_array($body['assets'])) {
            foreach ($body['assets'] as $asset) {
                if (isset($asset['name']) && preg_match('/\.zip$/', $asset['name'])) {
                    $download_url = $asset['browser_download_url'];
                    break;
                }
            }
        }

        if (!empty($body['body'])) {
            $description = wp_kses_post($body['body']);
        }

        $data = [
            'version'      => $version,
            'download_url' => $download_url,
            'homepage'     => 'https://github.com/' . self::GITHUB_REPO,
            'body'         => $description,
            'date'         => $body['published_at'] ?? '',
        ];

        set_transient(self::OPTION_KEY, $data, self::CACHE_DURATION);

        return $data;
    }
}
