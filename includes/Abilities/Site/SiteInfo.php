<?php
/**
 * Ability: wpcodex/site-info
 *
 * @package WPCodex\Abilities
 */

declare( strict_types=1 );

namespace WPCodex\Abilities\Site;

use WPCodex\Utils\Helpers;

class SiteInfo {
	public function __construct() {
        add_action( 'wpcodex/register_abilities', [ $this, 'init' ] );
    }
	public function init(): void {
		wp_register_ability( 'wpcodex/site-info', [
			'label'       => __( 'Site Info', 'wpcodex' ),
			'description' => __(
				'Return a full snapshot of this WordPress installation: WP version, PHP version, active theme, active plugins, site URLs, database table prefix, and key constants.',
				'wpcodex'
			),
			'category'    => 'wpcodex',

			'input_schema' => [
				'type'       => 'object',
				'properties' => [],
				'required'   => [],
			],

			'output_schema' => [
				'type'        => 'string',
				'description' => 'JSON-encoded site snapshot object.',
			],

			'execute_callback' => static function ( array $args ): string {
				global $wpdb;

				$theme   = wp_get_theme();
				$plugins = get_plugins();
				$active  = (array) get_option( 'active_plugins', [] );

				$active_plugins = [];
				foreach ( $active as $plugin_file ) {
					if ( isset( $plugins[ $plugin_file ] ) ) {
						$active_plugins[] = [
							'file'    => $plugin_file,
							'name'    => $plugins[ $plugin_file ]['Name'],
							'version' => $plugins[ $plugin_file ]['Version'],
						];
					}
				}

				$info = [
					'site_url'            => get_site_url(),
					'home_url'            => get_home_url(),
					'wp_version'          => get_bloginfo( 'version' ),
					'php_version'         => PHP_VERSION,
					'mysql_version'       => $wpdb->db_version(),
					'charset'             => get_bloginfo( 'charset' ),
					'language'            => get_bloginfo( 'language' ),
					'active_theme'        => [
						'name'       => $theme->get( 'Name' ),
						'version'    => $theme->get( 'Version' ),
						'stylesheet' => $theme->get_stylesheet(),
						'template'   => $theme->get_template(),
					],
					'active_plugins'      => $active_plugins,
					'permalink_structure' => get_option( 'permalink_structure' ),
					'uploads_dir'         => wp_upload_dir()['basedir'],
					'table_prefix'        => $wpdb->prefix,
					'multisite'           => is_multisite(),
					'debug'               => defined( 'WP_DEBUG' ) && WP_DEBUG,
					'abspath'             => ABSPATH,
				];

				return wp_json_encode( $info, JSON_PRETTY_PRINT ) ?: '{}';
			},

			'permission_callback' => [ Helpers::class, 'ability_permission' ],

			'meta' => [
				'annotations' => [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ],
				'mcp'         => [ 'public' => true, 'type' => 'tool' ],
			],
		] );
	}
}
