<?php
/**
 * GP Language Pack Client.
 *
 * PSR-4 autoload-compatible client integration class for premium themes and plugins.
 *
 * @package GP_Language_Pack/Client
 * @version 1.0.0
 */

namespace HelgaTheViking\GPLanguagePack;

if ( ! class_exists( __NAMESPACE__ . '\\Client' ) ) {

	/**
	 * Client class to inject translation updates.
	 */
	class Client {

		/**
		 * @var string Custom GlotPress server URL.
		 */
		private string $server_url;

		/**
		 * @var string GlotPress project path/slug.
		 */
		private string $project_slug;

		/**
		 * @var string Text domain of the plugin/theme.
		 */
		private string $textdomain;

		/**
		 * @var string Type of software: 'plugin' or 'theme'.
		 */
		private string $type;

		/**
		 * Constructor.
		 *
		 * @param string $server_url   E.g., "https://my-glotpress-site.com"
		 * @param string $project_slug E.g., "my-plugin"
		 * @param string $textdomain   E.g., "my-plugin"
		 * @param string $type         Either "plugin" or "theme". Default "plugin".
		 */
		public function __construct( string $server_url, string $project_slug, string $textdomain, string $type = 'plugin' ) {
			$this->server_url   = rtrim( $server_url, '/' );
			$this->project_slug = trim( $project_slug, '/' );
			$this->textdomain   = sanitize_key( $textdomain );
			$this->type         = in_array( $type, array( 'plugin', 'theme' ), true ) ? $type : 'plugin';

			if ( 'plugin' === $this->type ) {
				add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_translation_updates' ) );
			} else {
				add_filter( 'pre_set_site_transient_update_themes', array( $this, 'inject_translation_updates' ) );
			}
		}

		/**
		 * Filters the update transient to inject custom language updates.
		 *
		 * @param object $transient
		 * @return object
		 */
		public function inject_translation_updates( $transient ) {
			if ( ! is_object( $transient ) ) {
				return $transient;
			}

			// Fetch available translations from our server.
			$remote_translations = $this->get_remote_translations();
			if ( empty( $remote_translations ) ) {
				return $transient;
			}

			// Get all installed locales on the client site.
			$installed_locales = array_unique( array_merge(
				array( get_locale() ),
				get_available_languages()
			) );

			if ( ! isset( $transient->translations ) || ! is_array( $transient->translations ) ) {
				$transient->translations = array();
			}

			$lang_dir = ( 'plugin' === $this->type ) ? WP_LANG_DIR . '/plugins/' : WP_LANG_DIR . '/themes/';

			foreach ( $remote_translations as $locale => $data ) {
				// Only proceed if the locale is active/installed on the site.
				if ( ! in_array( $locale, $installed_locales, true ) ) {
					continue;
				}

				if ( empty( $data['package'] ) || empty( $data['updated'] ) ) {
					continue;
				}

				$mo_file = $lang_dir . $this->textdomain . '-' . $locale . '.mo';
				$needs_update = false;

				if ( ! file_exists( $mo_file ) ) {
					$needs_update = true;
				} else {
					$local_mtime  = filemtime( $mo_file );
					$remote_mtime = strtotime( $data['updated'] );
					if ( $remote_mtime && $remote_mtime > $local_mtime ) {
						$needs_update = true;
					}
				}

				if ( $needs_update ) {
					// Build WordPress core translation update format.
					$update_item = array(
						'type'          => $this->type,
						'slug'          => $this->textdomain,
						'language'      => $locale,
						'version'       => $data['version'] ?? gmdate( 'YmdHis', strtotime( $data['updated'] ) ),
						'updated'       => $data['updated'],
						'package'       => $data['package'],
						'autotranslate' => true,
					);

					// Avoid duplicating the update item in the list.
					$found = false;
					foreach ( $transient->translations as $index => $existing_item ) {
						if ( 
							isset( $existing_item['type'], $existing_item['slug'], $existing_item['language'] ) &&
							$existing_item['type'] === $this->type &&
							$existing_item['slug'] === $this->textdomain &&
							$existing_item['language'] === $locale
						) {
							// Replace with our newer/custom package.
							$transient->translations[ $index ] = $update_item;
							$found = true;
							break;
						}
					}

					if ( ! $found ) {
						$transient->translations[] = $update_item;
					}
				}
			}

			return $transient;
		}

		/**
		 * Queries the custom GlotPress translation API and caches the response.
		 *
		 * @return array
		 */
		private function get_remote_translations(): array {
			$transient_key = 'gp_lp_cache_' . md5( $this->server_url . $this->project_slug );
			$cached = get_transient( $transient_key );

			if ( false !== $cached && is_array( $cached ) ) {
				return $cached;
			}

			$api_url = sprintf(
				'%s/wp-json/gp-language-pack-server/v1/api/%s',
				$this->server_url,
				urlencode( $this->project_slug )
			);

			$response = wp_safe_remote_get( $api_url, array( 'timeout' => 10 ) );

			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
				return array();
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! is_array( $data ) ) {
				$data = array();
			}

			// Cache for 12 hours (same interval as WordPress core update checks).
			set_transient( $transient_key, $data, 12 * HOUR_IN_SECONDS );

			return $data;
		}
	}
}
