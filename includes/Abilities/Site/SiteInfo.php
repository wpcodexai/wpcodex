<?php
/**
 * Ability: allyworker/site-info
 *
 * @package AllyWorker
 * @since   1.0.0
 */

declare( strict_types=1 );

namespace AllyWorker\Abilities\Site;

use AllyWorker\Abilities\AbstractAbility;

/**
 * Class SiteInfo
 *
 * @since 1.0.0
 */
class SiteInfo extends AbstractAbility {
	public function get_category(): string {
		return 'allyworker-site';
	}
	/** {@inheritDoc} */
	public function get_name(): string {
		return 'allyworker/site-info';
	}

	/** {@inheritDoc} */
	public function get_label(): string {
		return __( 'Site Info', 'allyworker' );
	}

	/** {@inheritDoc} */
	public function get_description(): string {
		return __(
			'Return a full snapshot of this WordPress installation: WP version, PHP version, active theme, active plugins, site URLs, database table prefix, and key constants.',
			'allyworker'
		);
	}

	/** {@inheritDoc} */
	public function get_input_schema(): array {
		return [
			'type'       => 'object',
			'properties' => [],
			'required'   => [],
		];
	}

	/** {@inheritDoc} */
	public function get_output_schema(): array {
		return [
			'type'        => 'string',
			'description' => 'JSON-encoded site snapshot object.',
		];
	}

	/** {@inheritDoc} */
	public function get_annotations(): array {
		return [ 'readonly' => true, 'destructive' => false, 'idempotent' => true ];
	}

	/** {@inheritDoc} */
	public function execute( array $input ): string {
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
	}
}
