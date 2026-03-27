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
		load_plugin_textdomain( 'dynamic-post-grid-pro', false, dirname( plugin_basename( DPG_FILE ) ) . '/languages' );

		DPG_Admin::instance()->init();
		DPG_GraphQL::instance()->init();

		// Prevent caching layers from serving stale GraphQL results (search/pagination must be dynamic).
		add_action( 'send_headers', array( $this, 'maybe_disable_graphql_caching' ), 0 );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_graphql_notice' ) );
		add_shortcode( 'dpg_grid', array( $this, 'render_shortcode' ) );
	}

	public function maybe_disable_graphql_caching(): void {
		$is_graphql = false;
		if ( function_exists( 'graphql_is_graphql_http_request' ) ) {
			$is_graphql = (bool) graphql_is_graphql_http_request();
		} elseif ( isset( $_SERVER['REQUEST_URI'] ) ) {
			$is_graphql = ( false !== strpos( (string) $_SERVER['REQUEST_URI'], '/graphql' ) );
		}

		if ( ! $is_graphql ) {
			return;
		}

		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Pragma: no-cache' );
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
		$post_types = array_values(
			array_filter(
				$post_types,
				static function ( $pt ) {
					return post_type_exists( (string) $pt );
				}
			)
		);

		$taxonomy_config = isset( $settings['taxonomy_config'] ) ? (array) $settings['taxonomy_config'] : array();
		$taxonomy_config = array_reduce(
			array_keys( $taxonomy_config ),
			static function ( array $carry, $tax ) use ( $taxonomy_config ) {
				$tax = sanitize_key( (string) $tax );
				if ( $tax && taxonomy_exists( $tax ) ) {
					$carry[ $tax ] = (int) (bool) ( $taxonomy_config[ $tax ] ?? 0 );
				}
				return $carry;
			},
			array()
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

	public static function get_grids(): array {
		$raw = (array) get_option( 'dpg_grids', array() );
		$out = array();

		foreach ( $raw as $key => $grid ) {
			$key = sanitize_key( (string) $key );
			if ( ! $key || ! is_array( $grid ) ) {
				continue;
			}

			$label = isset( $grid['label'] ) ? sanitize_text_field( (string) $grid['label'] ) : $key;

			$post_types = isset( $grid['post_types'] ) ? (array) $grid['post_types'] : array();
			$post_types = array_values( array_filter( array_map( 'sanitize_key', $post_types ) ) );
			if ( ! $post_types ) {
				$post_types = array( 'post' );
			}

			$taxonomies = isset( $grid['taxonomies'] ) ? (array) $grid['taxonomies'] : array();
			$taxonomies = array_values( array_filter( array_map( 'sanitize_key', $taxonomies ) ) );
			$taxonomies = array_values(
				array_filter(
					array_values( array_unique( $taxonomies ) ),
					static function ( $tax ) {
						return taxonomy_exists( (string) $tax );
					}
				)
			);

			$term_ids_by_taxonomy = array();
			if ( isset( $grid['term_ids'] ) && is_array( $grid['term_ids'] ) ) {
				foreach ( $grid['term_ids'] as $taxonomy => $ids ) {
					$taxonomy = sanitize_key( (string) $taxonomy );
					if ( ! $taxonomy || ! in_array( $taxonomy, $taxonomies, true ) ) {
						continue;
					}
					$ids = is_array( $ids ) ? $ids : array( $ids );
					$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
					$ids = array_values( array_unique( $ids ) );
					if ( $ids ) {
						$term_ids_by_taxonomy[ $taxonomy ] = $ids;
					}
				}
			}

			$ppp = isset( $grid['posts_per_page'] ) ? (int) $grid['posts_per_page'] : 9;
			if ( $ppp < 1 ) {
				$ppp = 9;
			}

			$layout = isset( $grid['layout'] ) ? sanitize_key( (string) $grid['layout'] ) : 'card';
			if ( ! in_array( $layout, array( 'card', 'logo', 'overlay' ), true ) ) {
				$layout = 'card';
			}

			$pagination = isset( $grid['pagination'] ) ? sanitize_key( (string) $grid['pagination'] ) : 'buttons';
			if ( ! in_array( $pagination, array( 'buttons', 'numeric' ), true ) ) {
				$pagination = 'buttons';
			}

			$taxonomy_titles = array();
			if ( isset( $grid['taxonomy_titles'] ) && is_array( $grid['taxonomy_titles'] ) ) {
				foreach ( $grid['taxonomy_titles'] as $tax => $title ) {
					$tax = sanitize_key( (string) $tax );
					if ( ! $tax || ! in_array( $tax, $taxonomies, true ) ) {
						continue;
					}
					$title = sanitize_text_field( (string) $title );
					if ( '' !== $title ) {
						$taxonomy_titles[ $tax ] = $title;
					}
				}
			}

			$out[ $key ] = array(
				'label'          => $label,
				'post_types'     => $post_types,
				'taxonomies'     => $taxonomies,
				'term_ids'       => $term_ids_by_taxonomy,
				'posts_per_page' => $ppp,
				'layout'         => $layout,
				'taxonomy_titles'=> $taxonomy_titles,
				'pagination'     => $pagination,
			);
		}

		return $out;
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
		}
		// Always bust caches when built assets change.
		if ( file_exists( $script_path ) ) {
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
		$grids    = self::get_grids();
		$raw_atts = is_array( $atts ) ? $atts : array();
		$atts     = shortcode_atts(
			array(
				'grid'           => '',
				'post_type'      => '',
				'post_types'     => implode( ',', $settings['post_types'] ),
				'taxonomies'     => '',
				'layout'         => '',
				'pagination'     => '',
				'posts_per_page' => (string) $settings['posts_per_page'],
			),
			$raw_atts,
			'dpg_grid'
		);

		$grid_key = sanitize_key( (string) $atts['grid'] );
		$preset   = ( $grid_key && isset( $grids[ $grid_key ] ) ) ? (array) $grids[ $grid_key ] : null;

		$post_types = $preset ? (array) $preset['post_types'] : $settings['post_types'];
		$taxonomies = $preset ? (array) $preset['taxonomies'] : array();
		$term_ids   = $preset ? (array) ( $preset['term_ids'] ?? array() ) : array();
		$ppp        = $preset ? (int) $preset['posts_per_page'] : (int) $settings['posts_per_page'];
		$layout     = $preset ? (string) ( $preset['layout'] ?? 'card' ) : 'card';
		$tax_titles = $preset ? (array) ( $preset['taxonomy_titles'] ?? array() ) : array();
		$pagination = $preset ? (string) ( $preset['pagination'] ?? 'buttons' ) : 'buttons';

		// Explicit shortcode attrs override preset/defaults.
		$post_type = sanitize_key( (string) $atts['post_type'] );
		if ( array_key_exists( 'post_type', $raw_atts ) && $post_type ) {
			$post_types = array( $post_type );
		} elseif ( array_key_exists( 'post_types', $raw_atts ) ) {
			$parsed = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) $atts['post_types'] ) ) ) );
			if ( $parsed ) {
				$post_types = $parsed;
			}
		}

		if ( array_key_exists( 'taxonomies', $raw_atts ) ) {
			$taxonomies = array_values( array_filter( array_map( 'sanitize_key', explode( ',', (string) $atts['taxonomies'] ) ) ) );
		} elseif ( ! $preset ) {
			// Back-compat default when no preset and no per-grid taxonomies provided.
			$taxonomies = array_keys(
				array_filter(
					$settings['taxonomy_config'],
					static function ( $v ) {
						return (bool) $v;
					}
				)
			);
		}

		$ppp_override = (int) $atts['posts_per_page'];
		if ( array_key_exists( 'posts_per_page', $raw_atts ) && $ppp_override > 0 ) {
			$ppp = $ppp_override;
		}

		if ( array_key_exists( 'layout', $raw_atts ) ) {
			$layout_override = sanitize_key( (string) $atts['layout'] );
			if ( in_array( $layout_override, array( 'card', 'logo', 'overlay' ), true ) ) {
				$layout = $layout_override;
			}
		}

		if ( array_key_exists( 'pagination', $raw_atts ) ) {
			$pagination_override = sanitize_key( (string) $atts['pagination'] );
			if ( in_array( $pagination_override, array( 'buttons', 'numeric' ), true ) ) {
				$pagination = $pagination_override;
			}
		}

		$data = array(
			'postTypes'     => $post_types,
			'postsPerPage'  => $ppp,
			'taxonomies'    => $taxonomies,
			'termIds'       => $term_ids,
			'grid'          => $grid_key,
			'layout'        => $layout,
			'taxonomyTitles'=> $tax_titles,
			'pagination'    => $pagination,
		);

		return sprintf(
			'<div class="dpg-root" data-dpg="%s"></div>',
			esc_attr( wp_json_encode( $data ) )
		);
	}
}
