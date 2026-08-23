<?php
/**
 * Greenspage Countdown - self-hosted update checker.
 *
 * Delivers normal WordPress update notifications for a plugin distributed via
 * GitHub Releases rather than wordpress.org. It talks ONLY to the public GitHub
 * REST API, ships no credentials, caches responses to respect rate limits, and
 * fails silently if GitHub is unreachable so it can never take a site down.
 *
 * Distribution model: each release tag (vX.Y.Z) carries one asset named
 * greenspage-countdown.zip, whose top-level folder is `greenspage-countdown`.
 *
 * @package Greenspage_Countdown
 */

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates the update check so nothing leaks into the global namespace.
 */
final class GPCD_Updater {

	/**
	 * @var string Plugin basename, e.g. greenspage-countdown/greenspage-countdown.php
	 */
	private $basename;

	/**
	 * @var string Plugin folder slug, e.g. greenspage-countdown
	 */
	private $slug;

	/**
	 * @var string GitHub "owner/repo".
	 */
	private $repo;

	/**
	 * @var string Installed version.
	 */
	private $version;

	/**
	 * @var string Expected release asset filename.
	 */
	private $asset = 'greenspage-countdown.zip';

	/**
	 * @param string $file    Main plugin file (__FILE__).
	 * @param string $repo    GitHub "owner/repo".
	 * @param string $version Installed version.
	 */
	public function __construct( $file, $repo, $version ) {
		$this->basename = plugin_basename( $file );
		$this->slug     = dirname( $this->basename );
		$this->repo     = $repo;
		$this->version  = $version;

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'inject_update' ) );
		add_filter( 'plugins_api', array( $this, 'details' ), 20, 3 );

		// Clear the cache when an update finishes, so the next check is fresh.
		add_action( 'upgrader_process_complete', array( $this, 'flush' ), 10, 0 );
	}

	/**
	 * Cache key for the release lookup.
	 *
	 * @return string
	 */
	private function cache_key() {
		return 'gpcd_release_' . md5( $this->repo );
	}

	/**
	 * Fetch the latest published release from GitHub, cached for 12 hours.
	 *
	 * @return array|false Release data, or false on failure.
	 */
	private function latest_release() {
		$cached = get_transient( $this->cache_key() );

		if ( false !== $cached ) {
			return is_array( $cached ) ? $cached : false;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . $this->repo . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'Greenspage-Countdown-Updater',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Cache the failure briefly so a down API doesn't hammer every load.
			set_transient( $this->cache_key(), 'none', HOUR_IN_SECONDS );
			return false;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		// Validate the payload before trusting any of it.
		if ( ! is_array( $data ) || empty( $data['tag_name'] ) || ! is_string( $data['tag_name'] ) ) {
			set_transient( $this->cache_key(), 'none', HOUR_IN_SECONDS );
			return false;
		}

		// The /releases/latest endpoint already excludes drafts and pre-releases;
		// re-check the flags anyway so a future endpoint change can never leak one.
		if ( ! empty( $data['draft'] ) || ! empty( $data['prerelease'] ) ) {
			set_transient( $this->cache_key(), 'none', 12 * HOUR_IN_SECONDS );
			return false;
		}

		$version = ltrim( $data['tag_name'], 'vV' );

		// Only accept a clean dotted numeric version (e.g. 1.1.1). Anything else
		// is ignored rather than fed to the update system.
		if ( ! preg_match( '/^[0-9]+(\.[0-9]+){0,3}$/', $version ) ) {
			set_transient( $this->cache_key(), 'none', HOUR_IN_SECONDS );
			return false;
		}

		$package = $this->find_asset( isset( $data['assets'] ) ? $data['assets'] : array() );

		$release = array(
			'version'   => $version,
			'body'      => isset( $data['body'] ) && is_string( $data['body'] ) ? $data['body'] : '',
			'published' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
			'html_url'  => isset( $data['html_url'] ) ? esc_url_raw( (string) $data['html_url'] ) : '',
			'package'   => $package,
		);

		set_transient( $this->cache_key(), $release, 12 * HOUR_IN_SECONDS );

		return $release;
	}

	/**
	 * Locate our named .zip asset on the release.
	 *
	 * @param array $assets Release assets.
	 * @return string Download URL, or empty string.
	 */
	private function find_asset( $assets ) {
		$expected_prefix = 'https://github.com/' . $this->repo . '/releases/download/';

		foreach ( (array) $assets as $asset ) {
			if ( ! isset( $asset['name'], $asset['browser_download_url'] ) ) {
				continue;
			}

			if ( $this->asset !== $asset['name'] ) {
				continue;
			}

			$url = (string) $asset['browser_download_url'];

			// The download URL must belong to THIS repository's releases on
			// github.com. This prevents a spoofed asset URL from ever becoming
			// the update package.
			if ( 0 === strpos( $url, $expected_prefix ) ) {
				return esc_url_raw( $url );
			}
		}

		return '';
	}

	/**
	 * Inject update data into the plugins update transient when a newer,
	 * properly-packaged release exists.
	 *
	 * @param object $transient Update transient.
	 * @return object
	 */
	public function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$release = $this->latest_release();

		// No release, no asset, or not actually newer -> leave WP alone.
		if ( empty( $release ) || empty( $release['package'] ) ) {
			return $transient;
		}

		if ( version_compare( $release['version'], $this->version, '<=' ) ) {
			return $transient;
		}

		$item = array(
			'slug'        => $this->slug,
			'plugin'      => $this->basename,
			'new_version' => $release['version'],
			'url'         => $release['html_url'],
			'package'     => $release['package'],
		);

		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$transient->response[ $this->basename ] = (object) $item;

		return $transient;
	}

	/**
	 * Populate the "View details" modal.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action API action.
	 * @param object             $args   Args.
	 * @return false|object
	 */
	public function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( empty( $args->slug ) || $args->slug !== $this->slug ) {
			return $result;
		}

		$release = $this->latest_release();

		if ( empty( $release ) ) {
			return $result;
		}

		$changelog = $release['body'] ? $this->markdown_to_html( $release['body'] ) : __( 'See the GitHub release notes.', 'greenspage-countdown' );

		return (object) array(
			'name'          => 'Greenspage Countdown',
			'slug'          => $this->slug,
			'version'       => $release['version'],
			'author'        => '<a href="https://greenspage.com/">Greenspage Web &amp; SEO</a>',
			'homepage'      => 'https://greenspage.com/plugins/countdown/',
			'download_link' => $release['package'],
			'trunk'         => $release['package'],
			'requires'      => '6.3',
			'requires_php'  => '7.4',
			'last_updated'  => $release['published'],
			'sections'      => array(
				'changelog' => $changelog,
			),
		);
	}

	/**
	 * Tiny, safe Markdown -> HTML for the changelog (headings, list items, code).
	 * Intentionally minimal; anything unrecognised is shown as escaped text.
	 *
	 * @param string $md Markdown.
	 * @return string
	 */
	private function markdown_to_html( $md ) {
		$out   = array();
		$lines = preg_split( '/\r\n|\n|\r/', $md );

		foreach ( $lines as $line ) {
			$line = rtrim( $line );

			if ( '' === $line ) {
				continue;
			}

			if ( preg_match( '/^#{1,6}\s+(.*)/', $line, $m ) ) {
				$out[] = '<h4>' . esc_html( $m[1] ) . '</h4>';
			} elseif ( preg_match( '/^[\*\-]\s+(.*)/', $line, $m ) ) {
				$out[] = '<li>' . esc_html( $m[1] ) . '</li>';
			} else {
				$out[] = '<p>' . esc_html( $line ) . '</p>';
			}
		}

		$html = implode( "\n", $out );

		// Wrap stray <li> runs in a <ul>.
		$html = preg_replace( '/(?:<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html );

		return $html;
	}

	/**
	 * Clear the cached release so the next check re-queries GitHub.
	 */
	public function flush() {
		delete_transient( $this->cache_key() );
	}
}
