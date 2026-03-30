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
		// Ensure taxonomies exist before flushing rewrite rules.
		if ( class_exists( __CLASS__ ) ) {
			self::register_article_taxonomies_static();
			self::seed_article_taxonomy_terms( true, true );
		}

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

		flush_rewrite_rules();
	}

	public function init(): void {
		load_plugin_textdomain( 'dynamic-post-grid-pro', false, dirname( plugin_basename( DPG_FILE ) ) . '/languages' );

		add_action( 'init', array( $this, 'register_article_taxonomies' ), 5 );

		DPG_Admin::instance()->init();
		DPG_GraphQL::instance()->init();

		// Prevent caching layers from serving stale GraphQL results (search/pagination must be dynamic).
		add_action( 'send_headers', array( $this, 'maybe_disable_graphql_caching' ), 0 );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'admin_notices', array( $this, 'maybe_show_graphql_notice' ) );
		add_shortcode( 'dpg_grid', array( $this, 'render_shortcode' ) );
	}

	/**
	 * Register site taxonomies used for filtering "Articles" (posts).
	 * These are hierarchical taxonomies (archives like categories, not tags).
	 */
	public function register_article_taxonomies(): void {
		self::register_article_taxonomies_static();
	}

	private static function register_article_taxonomies_static(): void {
		$taxonomies = array(
			'actualites_litteraires'       => array(
				'label' => 'Actualités littéraires',
				'slug'  => 'actualites-litteraires',
				'gql1'  => 'CjActualitesLitterairesCategory',
				'gqlN'  => 'CjActualitesLitterairesCategories',
			),
			'livres_selections_jeunesse'   => array(
				'label' => 'Livres et sélections jeunesse',
				'slug'  => 'livres-selections-jeunesse',
				'gql1'  => 'CjLivresSelectionsJeunesseCategory',
				'gqlN'  => 'CjLivresSelectionsJeunesseCategories',
			),
			'mediation_lecture'            => array(
				'label' => 'Médiation de la lecture',
				'slug'  => 'mediation-de-la-lecture',
				'gql1'  => 'CjMediationLectureCategory',
				'gqlN'  => 'CjMediationLectureCategories',
			),
			'entrevues_regards'            => array(
				'label' => 'Entrevues et regards',
				'slug'  => 'entrevues-et-regards',
				'gql1'  => 'CjEntrevuesRegardsCategory',
				'gqlN'  => 'CjEntrevuesRegardsCategories',
			),
			'par_age'                      => array(
				'label' => 'Par âge',
				'slug'  => 'par-age',
				'gql1'  => 'CjParAgeCategory',
				'gqlN'  => 'CjParAgeCategories',
			),
			'par_type_de_livre'            => array(
				'label' => 'Par type de livre',
				'slug'  => 'par-type-de-livre',
				'gql1'  => 'CjParTypeLivreCategory',
				'gqlN'  => 'CjParTypeLivreCategories',
			),
		);

		foreach ( $taxonomies as $taxonomy => $data ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( ! $taxonomy ) {
				continue;
			}

			$label = isset( $data['label'] ) ? (string) $data['label'] : $taxonomy;
			$slug  = isset( $data['slug'] ) ? (string) $data['slug'] : $taxonomy;
			$gql1  = isset( $data['gql1'] ) ? (string) $data['gql1'] : '';
			$gqlN  = isset( $data['gqlN'] ) ? (string) $data['gqlN'] : '';

			if ( taxonomy_exists( $taxonomy ) ) {
				// Make sure it's attached to posts even if created elsewhere.
				register_taxonomy_for_object_type( $taxonomy, 'post' );
				continue;
			}

			$labels = array(
				'name'              => $label,
				'singular_name'     => $label,
				'search_items'      => 'Rechercher',
				'all_items'         => 'Tous',
				'parent_item'       => 'Parent',
				'parent_item_colon' => 'Parent :',
				'edit_item'         => 'Modifier',
				'update_item'       => 'Mettre à jour',
				'add_new_item'      => 'Ajouter',
				'new_item_name'     => 'Nouveau',
				'menu_name'         => $label,
			);

			register_taxonomy(
				$taxonomy,
				array( 'post' ),
				array(
					'hierarchical'          => true,
					'labels'                => $labels,
					'public'                => true,
					'show_ui'               => true,
					'show_admin_column'     => true,
					'show_in_nav_menus'     => true,
					'show_tagcloud'         => false,
					'show_in_rest'          => true,
					'rewrite'               => array(
						'slug'         => sanitize_title( $slug ),
						'with_front'   => false,
						'hierarchical' => true,
					),
					'query_var'             => true,
					'show_in_graphql'       => true,
					'graphql_single_name'   => $gql1 ? $gql1 : 'Cj' . preg_replace( '/[^A-Za-z0-9]/', '', ucwords( str_replace( '_', ' ', $taxonomy ) ) ) . 'Category',
					'graphql_plural_name'   => $gqlN ? $gqlN : 'Cj' . preg_replace( '/[^A-Za-z0-9]/', '', ucwords( str_replace( '_', ' ', $taxonomy ) ) ) . 'Categories',
				)
			);
		}
	}

	/**
	 * Seed default terms for the article taxonomies.
	 *
	 * @param bool $only_if_empty When true, seeds only if a taxonomy has no terms yet.
	 * @param bool $mark_seeded   When true, sets the dpg_seeded_article_taxonomies option.
	 */
	public static function seed_article_taxonomy_terms( bool $only_if_empty = true, bool $mark_seeded = true ): void {
		if ( $only_if_empty && get_option( 'dpg_seeded_article_taxonomies', false ) ) {
			return;
		}

		$seed = array(
			'actualites_litteraires'     => array(
				'Actualités littéraires',
				'Prix littéraires',
				'Salons du livre',
				'Événements littéraires',
				'Nouvelles de l’organisme',
				'Nouvelles des membres',
			),
			'livres_selections_jeunesse' => array(
				'Livres et sélections jeunesse',
				'Sélections CJ',
				'Incontournables',
				'Top 5 des libraires',
				'Lectures de l’équipe CJ',
				'Sélections thématiques',
				'Réseaux littéraires',
			),
			'mediation_lecture'          => array(
				'Médiation de la lecture',
				'Activités',
				'Astuces de profs',
				'Pratiques inspirantes',
			),
			'entrevues_regards'          => array(
				'Entrevues et regards',
				'Entrevues',
				'Carte blanche (membres CJ)',
				'Rampe de lancement',
				'Francophonie à travers le pays',
			),
			'par_age'                    => array(
				'Par âge',
				'0–5 ans',
				'6–8 ans',
				'9–11 ans',
				'12–17 ans',
			),
			'par_type_de_livre'          => array(
				'Par type de livre',
				'Album',
				'Roman',
				'Bande dessinée',
				'Documentaire',
				'Poésie',
			),
		);

		foreach ( $seed as $taxonomy => $terms ) {
			$taxonomy = sanitize_key( (string) $taxonomy );
			if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			if ( $only_if_empty ) {
				$existing = get_terms(
					array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => false,
						'number'     => 1,
						'fields'     => 'ids',
					)
				);
				if ( ! is_wp_error( $existing ) && ! empty( $existing ) ) {
					continue;
				}
			}

			foreach ( (array) $terms as $term_name ) {
				$term_name = (string) $term_name;
				if ( '' === trim( $term_name ) ) {
					continue;
				}
				if ( term_exists( $term_name, $taxonomy ) ) {
					continue;
				}
				wp_insert_term(
					$term_name,
					$taxonomy,
					array(
						'slug' => sanitize_title( $term_name ),
					)
				);
			}
		}

		if ( $mark_seeded ) {
			update_option( 'dpg_seeded_article_taxonomies', 1, true );
		}
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
			if ( ! in_array( $layout, array( 'card', 'logo', 'overlay', 'blog', 'categories' ), true ) ) {
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
				'filter_mode'    => ( isset( $grid['filter_mode'] ) && in_array( (string) $grid['filter_mode'], array( 'ajax', 'links' ), true ) ) ? (string) $grid['filter_mode'] : 'ajax',
				'archive_sync'   => isset( $grid['archive_sync'] ) ? (int) (bool) $grid['archive_sync'] : 1,
				'ad_shortcode'   => isset( $grid['ad_shortcode'] ) ? sanitize_text_field( (string) $grid['ad_shortcode'] ) : '',
				'ad_image_id'    => isset( $grid['ad_image_id'] ) ? (int) $grid['ad_image_id'] : 0,
				'ad_link'        => isset( $grid['ad_link'] ) ? esc_url_raw( (string) $grid['ad_link'] ) : '',
				'ad_sticky_offset' => isset( $grid['ad_sticky_offset'] ) ? max( 0, (int) $grid['ad_sticky_offset'] ) : 18,
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
					'ad_term_id'     => '',
					'group_by'       => '',
					'pagination'     => '',
					'filter_mode'    => '',
					'archive_sync'   => '',
					'ad_shortcode'   => '',
				'ad_image_id'    => '',
				'ad_link'        => '',
				'ad_sticky_offset' => '',
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
		$filter_mode = $preset ? (string) ( $preset['filter_mode'] ?? 'ajax' ) : 'ajax';
		$archive_sync = $preset ? (int) ( $preset['archive_sync'] ?? 1 ) : 1;
		$ad_shortcode = $preset ? (string) ( $preset['ad_shortcode'] ?? '' ) : '';
		$ad_image_id  = $preset ? (int) ( $preset['ad_image_id'] ?? 0 ) : 0;
		$ad_link      = $preset ? (string) ( $preset['ad_link'] ?? '' ) : '';
		$ad_sticky_offset = $preset ? (int) ( $preset['ad_sticky_offset'] ?? 18 ) : 18;

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
			if ( in_array( $layout_override, array( 'card', 'logo', 'overlay', 'blog', 'categories' ), true ) ) {
				$layout = $layout_override;
			}
		}

		$ad_term_id = 0;
		if ( array_key_exists( 'ad_term_id', $raw_atts ) ) {
			$ad_term_id = (int) $atts['ad_term_id'];
			if ( $ad_term_id < 0 ) {
				$ad_term_id = 0;
			}
		}

		$group_by = '';
		if ( array_key_exists( 'group_by', $raw_atts ) ) {
			$group_by = sanitize_key( (string) $atts['group_by'] );
			if ( $group_by && ! taxonomy_exists( $group_by ) ) {
				$group_by = '';
			}
		}

		if ( array_key_exists( 'pagination', $raw_atts ) ) {
			$pagination_override = sanitize_key( (string) $atts['pagination'] );
			if ( in_array( $pagination_override, array( 'buttons', 'numeric' ), true ) ) {
				$pagination = $pagination_override;
			}
		}

		if ( array_key_exists( 'filter_mode', $raw_atts ) ) {
			$filter_mode_override = sanitize_key( (string) $atts['filter_mode'] );
			if ( in_array( $filter_mode_override, array( 'ajax', 'links' ), true ) ) {
				$filter_mode = $filter_mode_override;
			}
		}

		if ( array_key_exists( 'archive_sync', $raw_atts ) ) {
			$archive_sync = (int) (bool) (int) $atts['archive_sync'];
		}

		if ( array_key_exists( 'ad_shortcode', $raw_atts ) ) {
			$ad_shortcode = sanitize_text_field( (string) $atts['ad_shortcode'] );
		}
		if ( array_key_exists( 'ad_image_id', $raw_atts ) ) {
			$ad_image_id = (int) $atts['ad_image_id'];
		}
		if ( array_key_exists( 'ad_link', $raw_atts ) ) {
			$ad_link = esc_url_raw( (string) $raw_atts['ad_link'] );
		}
		if ( array_key_exists( 'ad_sticky_offset', $raw_atts ) ) {
			$ad_sticky_offset = max( 0, (int) $atts['ad_sticky_offset'] );
		}

		$context_term = null;
		if ( $archive_sync ) {
			$qo = function_exists( 'get_queried_object' ) ? get_queried_object() : null;
			if ( $qo instanceof WP_Term ) {
				$tax = (string) $qo->taxonomy;
				if ( $tax && in_array( $tax, $taxonomies, true ) ) {
					$context_term = array(
						'taxonomy' => $tax,
						'termId'   => (int) $qo->term_id,
					);
				}
			}
		}

		$archive_url = '';
		if ( ! empty( $post_types ) ) {
			$primary = (string) $post_types[0];
			if ( 'post' === $primary ) {
				$page_for_posts = (int) get_option( 'page_for_posts' );
				$archive_url    = $page_for_posts ? (string) get_permalink( $page_for_posts ) : (string) home_url( '/' );
			} else {
				$link = get_post_type_archive_link( $primary );
				$archive_url = $link ? (string) $link : (string) home_url( '/' );
			}
		}

		if ( isset( $raw_atts['archive_url'] ) ) {
			$archive_url = esc_url_raw( (string) $raw_atts['archive_url'] );
		}

		$ad_html = '';
		$ad_image_url = '';
		if ( $ad_image_id ) {
			$ad_image_url = (string) wp_get_attachment_image_url( $ad_image_id, 'large' );
		}
		$ad_link = $ad_link ? $ad_link : '';
		if ( ! $ad_image_url && $ad_shortcode ) {
			// Back-compat: still allow shortcode-based ads if no image is configured.
			$ad_html = (string) do_shortcode( $ad_shortcode );
		}

		$data = array(
			'postTypes'     => $post_types,
			'postsPerPage'  => $ppp,
			'taxonomies'    => $taxonomies,
			'termIds'       => $term_ids,
			'grid'          => $grid_key,
			'layout'        => $layout,
			'groupByTaxonomy' => $group_by,
			'taxonomyTitles'=> $tax_titles,
			'pagination'    => $pagination,
			'filterMode'    => $filter_mode,
			'contextTerm'   => $context_term,
			'archiveUrl'    => $archive_url,
			'adHtml'        => $ad_html,
			'adImageUrl'    => $ad_image_url,
			'adLink'        => $ad_link,
			'adTermId'      => $ad_term_id,
			'adStickyOffset'=> $ad_sticky_offset,
		);

		$style_attr = '';
		if ( $ad_sticky_offset >= 0 ) {
			$style_attr = sprintf( ' style="%s"', esc_attr( '--dpg-sticky-top:' . (string) (int) $ad_sticky_offset . 'px;' ) );
		}

		return sprintf(
			'<div class="dpg-root"%s data-dpg="%s"></div>',
			$style_attr,
			esc_attr( wp_json_encode( $data ) )
		);
	}
}
