<?php
/**
 * @package DynamicPostGridPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DPG_Plugin {
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public static function activate(): void {
		$defaults = array(
			'post_types'      => array( 'post' ),
			'taxonomy_config' => array(),
			'posts_per_page'  => 9,
		);

		if ( false === get_option( 'dpg_settings' ) ) {
			add_option( 'dpg_settings', $defaults );
		} else {
			$existing = (array) get_option( 'dpg_settings', array() );
			update_option( 'dpg_settings', array_merge( $defaults, $existing ) );
		}
	}

	public function init(): void {
		DPG_Admin::instance()->init();
		DPG_GraphQL::instance()->init();

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_graphql_notice' ) );
		add_shortcode( 'dpg_grid', array( $this, 'render_shortcode' ) );
	}

	public function maybe_show_graphql_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( function_exists( 'register_graphql_field' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'settings_page_dpg-settings' !== (string) $screen->id ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' .
			esc_html( 'Dynamic Post Grid Pro works best with WPGraphQL. Install/activate WPGraphQL to enable the /graphql endpoint and the dpgPosts query.' ) .
			'</p></div>';
	}

	public static function get_settings(): array {
		$settings = (array) get_option( 'dpg_settings', array() );

		$post_types = isset( $settings['post_types'] ) ? (array) $settings['post_types'] : array( 'post' );
		$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );

		$taxonomy_config = isset( $settings['taxonomy_config'] ) ? (array) $settings['taxonomy_config'] : array();
		$taxonomy_config = array_map(
			static function ( $v ) {
				return (int) (bool) $v;
			},
			$taxonomy_config
		);

		$posts_per_page = isset( $settings['posts_per_page'] ) ? (int) $settings['posts_per_page'] : 9;
		if ( $posts_per_page < 1 ) {
			$posts_per_page = 9;
		}

		return array(
			'post_types'      => $post_types,
			'taxonomy_config' => $taxonomy_config,
			'posts_per_page'  => $posts_per_page,
		);
	}

	public function register_assets(): void {
		$script_path = DPG_PATH . 'build/index.js';
		$asset_path  = DPG_PATH . 'build/index.asset.php';
		$style_path  = file_exists( DPG_PATH . 'build/style-index.css' )
			? DPG_PATH . 'build/style-index.css'
			: ( file_exists( DPG_PATH . 'build/index.css' ) ? DPG_PATH . 'build/index.css' : '' );

		$deps    = array( 'wp-element' );
		$version = DPG_VERSION;
		if ( file_exists( $asset_path ) ) {
			$asset = require $asset_path;
			if ( is_array( $asset ) ) {
				$deps    = isset( $asset['dependencies'] ) ? (array) $asset['dependencies'] : $deps;
				$version = isset( $asset['version'] ) ? (string) $asset['version'] : $version;
			}
		} elseif ( file_exists( $script_path ) ) {
			$version = (string) filemtime( $script_path );
		}

		wp_register_script(
			'dpg-frontend',
			DPG_URL . 'build/index.js',
			$deps,
			$version,
			true
		);

		if ( $style_path && file_exists( $style_path ) ) {
			$style_url = ( false !== strpos( $style_path, 'style-index.css' ) ) ? DPG_URL . 'build/style-index.css' : DPG_URL . 'build/index.css';
			wp_register_style(
				'dpg-frontend',
				$style_url,
				array(),
				(string) filemtime( $style_path )
			);
		}

		wp_localize_script(
			'dpg-frontend',
			'DPG_CONFIG',
			array(
				'graphqlUrl' => home_url( '/graphql' ),
			)
		);
	}

	public function render_shortcode( $atts ): string {
		wp_enqueue_script( 'dpg-frontend' );
		wp_enqueue_style( 'dpg-frontend' );

		$settings = self::get_settings();
		$atts     = shortcode_atts(
			array(
				'post_types'     => implode( ',', $settings['post_types'] ),
				'posts_per_page' => (string) $settings['posts_per_page'],
			),
			(array) $atts,
			'dpg_grid'
		);

		$post_types = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) $atts['post_types'] ) ) ) );
		$ppp        = (int) $atts['posts_per_page'];
		if ( $ppp < 1 ) {
			$ppp = (int) $settings['posts_per_page'];
		}

		$data = array(
			'postTypes'     => $post_types,
			'postsPerPage'  => $ppp,
			'taxonomyConfig'=> $settings['taxonomy_config'],
		);

		return sprintf(
			'<div class="dpg-root" data-dpg="%s"></div>',
			esc_attr( wp_json_encode( $data ) )
		);
	}
}
