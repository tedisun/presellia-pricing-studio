<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PPS — GitHub Auto-Updater
 *
 * S'intègre au système de mises à jour WordPress pour vérifier les nouvelles
 * releases sur le dépôt public github.com/tedisun/presellia-pricing-studio.
 *
 * Fonctionnement :
 *  1. Toutes les 12 h WordPress vérifie les mises à jour des extensions.
 *  2. Cette classe intercepte cette vérification et appelle l'API GitHub Releases.
 *  3. Si un tag plus récent existe (ex. v1.1.0 > 1.0.0), WordPress affiche
 *     la notification dans Tableau de bord > Mises à jour.
 *  4. L'admin clique "Mettre à jour" — WordPress télécharge le ZIP depuis
 *     GitHub et installe automatiquement.
 *
 * Publier une nouvelle release :
 *  1. Incrémenter PPS_VERSION dans presellia-pricing-studio.php (ex. '1.1.0').
 *  2. Mettre à jour l'en-tête "Version:" du plugin (même fichier).
 *  3. Mettre à jour CHANGELOG.md.
 *  4. Pousser sur GitHub et créer une Release avec le tag v1.1.0.
 *     (Le GitHub Action génère automatiquement le ZIP propre.)
 *  5. Les sites WordPress reçoivent la mise à jour en moins de 12 h.
 */
class PPS_Updater {

	const GITHUB_USER       = 'tedisun';
	const GITHUB_REPO       = 'presellia-pricing-studio';
	const PLUGIN_SLUG       = 'presellia-pricing-studio';
	const PLUGIN_BASENAME   = 'presellia-pricing-studio/presellia-pricing-studio.php';
	const TRANSIENT_KEY     = 'pps_update_data';
	const CACHE_TTL         = 43200;

	private static ?self $instance = null;

	private function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
		add_filter( 'plugins_api',                           [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_source_selection',             [ $this, 'fix_source_dir' ], 10, 4 );
		add_action( 'upgrader_process_complete',             [ $this, 'clear_cache' ], 10, 2 );
	}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -------------------------------------------------------------------------
	// Vérification de mise à jour
	// -------------------------------------------------------------------------

	public function check_for_update( object $transient ): object {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$latest_version = $this->parse_version( $release->tag_name );

		if ( version_compare( $latest_version, PPS_VERSION, '>' ) ) {
			$transient->response[ self::PLUGIN_BASENAME ] = $this->build_update_object( $release, $latest_version );
		} else {
			$transient->no_update[ self::PLUGIN_BASENAME ] = $this->build_no_update_object( $latest_version );
		}

		return $transient;
	}

	public function plugin_info( $result, string $action, object $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! isset( $args->slug ) || $args->slug !== self::PLUGIN_SLUG ) {
			return $result;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $result;
		}

		$latest_version = $this->parse_version( $release->tag_name );

		$info               = new stdClass();
		$info->name         = 'Presellia Pricing Studio';
		$info->slug         = self::PLUGIN_SLUG;
		$info->version      = $latest_version;
		$info->author       = '<a href="https://presellia.com">Presellia</a>';
		$info->homepage     = 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO;
		$info->requires     = '6.0';
		$info->requires_php = '8.0';
		$info->last_updated = $release->published_at ?? '';
		$info->download_link = $this->get_download_url( $release );
		$info->sections     = [
			'description' => 'Éditeur de prix et analyse de rentabilité pour WooCommerce — coûts sourcing USD/CFA, marges client/revendeur, paliers dégressifs, analytics.',
			'changelog'   => $this->format_changelog( $release->body ?? '' ),
		];

		return $info;
	}

	public function fix_source_dir( string $source, string $remote_source, $upgrader, array $hook_extra = [] ): string {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::PLUGIN_BASENAME ) {
			return $source;
		}

		$expected = trailingslashit( $remote_source ) . self::PLUGIN_SLUG . '/';

		if ( $source === $expected ) {
			return $source;
		}

		if ( $wp_filesystem->move( $source, $expected ) ) {
			return $expected;
		}

		return $source;
	}

	public function clear_cache( WP_Upgrader $upgrader, array $hook_extra ): void {
		if (
			isset( $hook_extra['action'], $hook_extra['type'], $hook_extra['plugins'] ) &&
			'update' === $hook_extra['action'] &&
			'plugin' === $hook_extra['type'] &&
			in_array( self::PLUGIN_BASENAME, $hook_extra['plugins'], true )
		) {
			delete_transient( self::TRANSIENT_KEY );
		}
	}

	// -------------------------------------------------------------------------
	// API GitHub
	// -------------------------------------------------------------------------

	private function get_latest_release(): ?object {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( false !== $cached ) {
			return $cached ?: null;
		}

		$url      = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			self::GITHUB_USER,
			self::GITHUB_REPO
		);
		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'PPS-Updater/' . PPS_VERSION . ' (WordPress/' . get_bloginfo( 'version' ) . ')',
			],
		] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( self::TRANSIENT_KEY, false, 300 );
			return null;
		}

		$body    = wp_remote_retrieve_body( $response );
		$release = json_decode( $body );

		if ( empty( $release->tag_name ) ) {
			set_transient( self::TRANSIENT_KEY, false, 300 );
			return null;
		}

		set_transient( self::TRANSIENT_KEY, $release, self::CACHE_TTL );
		return $release;
	}

	private function get_download_url( object $release ): string {
		if ( ! empty( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( $asset->name === 'presellia-pricing-studio.zip' && ! empty( $asset->browser_download_url ) ) {
					return $asset->browser_download_url;
				}
			}
		}

		return sprintf(
			'https://github.com/%s/%s/archive/refs/tags/%s.zip',
			self::GITHUB_USER,
			self::GITHUB_REPO,
			$release->tag_name
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function parse_version( string $tag ): string {
		return ltrim( $tag, 'vV' );
	}

	private function build_update_object( object $release, string $version ): object {
		return (object) [
			'id'            => self::GITHUB_USER . '/' . self::GITHUB_REPO,
			'slug'          => self::PLUGIN_SLUG,
			'plugin'        => self::PLUGIN_BASENAME,
			'new_version'   => $version,
			'url'           => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
			'package'       => $this->get_download_url( $release ),
			'icons'         => [],
			'banners'       => [],
			'banners_rtl'   => [],
			'requires'      => '6.0',
			'tested'        => get_bloginfo( 'version' ),
			'requires_php'  => '8.0',
			'compatibility' => new stdClass(),
		];
	}

	private function build_no_update_object( string $version ): object {
		return (object) [
			'id'           => self::GITHUB_USER . '/' . self::GITHUB_REPO,
			'slug'         => self::PLUGIN_SLUG,
			'plugin'       => self::PLUGIN_BASENAME,
			'new_version'  => $version,
			'url'          => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
			'package'      => '',
			'icons'        => [],
			'banners'      => [],
			'requires'     => '6.0',
			'requires_php' => '8.0',
		];
	}

	private function format_changelog( string $body ): string {
		if ( empty( $body ) ) {
			return '<p>Voir les notes de version sur GitHub.</p>';
		}

		$body = esc_html( $body );
		$body = preg_replace( '/^### (.+)$/m', '<h4>$1</h4>', $body );
		$body = preg_replace( '/^## (.+)$/m',  '<h3>$1</h3>', $body );
		$body = preg_replace( '/^# (.+)$/m',   '<h2>$1</h2>', $body );
		$body = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $body );
		$body = preg_replace( '/^- (.+)$/m', '<li>$1</li>', $body );
		$body = preg_replace( '/(<li>.*<\/li>)/s', '<ul>$1</ul>', $body );
		$body = nl2br( $body );

		return $body;
	}

	public static function force_check(): void {
		delete_transient( self::TRANSIENT_KEY );
		delete_site_transient( 'update_plugins' );
		wp_update_plugins();
	}
}
