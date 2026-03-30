<?php
/**
 * @package DynamicPostGridPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DPG_GraphQL {
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_action( 'graphql_register_types', array( $this, 'register_types' ) );
	}

	public function register_types(): void {
		if ( ! function_exists( 'register_graphql_field' ) ) {
			return;
		}

		if ( function_exists( 'register_graphql_object_type' ) ) {
			register_graphql_object_type(
				'DPGPostNode',
				array(
					'description' => 'A lightweight post node returned by DPG queries.',
					'fields'      => array(
						'databaseId' => array(
							'type'        => 'Int',
							'description' => 'WordPress post ID.',
						),
						'title'      => array(
							'type'        => 'String',
							'description' => 'Post title.',
						),
						'uri'        => array(
							'type'        => 'String',
							'description' => 'Permalink path (if available).',
						),
						'postType'   => array(
							'type'        => 'String',
							'description' => 'Post type slug.',
						),
						'excerpt'    => array(
							'type'        => 'String',
							'description' => 'Post excerpt (plain text).',
						),
						'featuredImageUrl' => array(
							'type'        => 'String',
							'description' => 'Featured image URL (if available).',
						),
						'featuredImageAlt' => array(
							'type'        => 'String',
							'description' => 'Featured image alt text (if available).',
						),
					),
				)
			);

			register_graphql_object_type(
				'DPGTerm',
				array(
					'description' => 'A taxonomy term for DPG filters.',
					'fields'      => array(
						'taxonomy' => array(
							'type'        => 'String',
							'description' => 'Taxonomy slug.',
						),
						'id'   => array(
							'type'        => 'Int',
							'description' => 'Term ID.',
						),
						'name' => array(
							'type'        => 'String',
							'description' => 'Term name.',
						),
						'uri'  => array(
							'type'        => 'String',
							'description' => 'Term archive path (if available).',
						),
					),
				)
			);

			register_graphql_object_type(
				'DPGTermPostsSection',
				array(
					'description' => 'Posts grouped under a specific taxonomy term.',
					'fields'      => array(
						'term'  => array(
							'type'        => 'DPGTerm',
							'description' => 'The taxonomy term.',
						),
						'posts' => array(
							'type'        => 'DPGPostsResult',
							'description' => 'Posts that match this term (plus any additional filters).',
						),
					),
				)
			);

			register_graphql_object_type(
				'DPGTaxonomyTerms',
				array(
					'description' => 'Terms grouped by taxonomy.',
					'fields'      => array(
						'taxonomy' => array(
							'type'        => 'String',
							'description' => 'Taxonomy slug.',
						),
						'label'    => array(
							'type'        => 'String',
							'description' => 'Taxonomy display label (localized).',
						),
						'terms'    => array(
							'type'        => array( 'list_of' => 'DPGTerm' ),
							'description' => 'Terms for the taxonomy.',
						),
					),
				)
			);

			register_graphql_object_type(
				'DPGPostsResult',
				array(
					'description' => 'DPG posts query response.',
					'fields'      => array(
						'nodes' => array(
							'type'        => array( 'list_of' => 'DPGPostNode' ),
							'description' => 'The returned nodes.',
						),
						'total' => array(
							'type'        => 'Int',
							'description' => 'Total results.',
						),
						'pages' => array(
							'type'        => 'Int',
							'description' => 'Total pages.',
						),
						'debug' => array(
							'type'        => 'DPGPostsDebug',
							'description' => 'Admin-only debug details for troubleshooting.',
						),
					),
				)
			);

			register_graphql_object_type(
				'DPGPostsDebug',
				array(
					'description' => 'Debug information for DPG posts queries (admin only).',
					'fields'      => array(
						'queryArgs' => array(
							'type'        => 'String',
							'description' => 'WP_Query args JSON.',
						),
						'resolvedPostTypes' => array(
							'type'        => array( 'list_of' => 'String' ),
							'description' => 'Resolved post type slugs used in WP_Query.',
						),
						'search' => array(
							'type'        => 'String',
							'description' => 'Search string used (if any).',
						),
						'sql'      => array(
							'type'        => 'String',
							'description' => 'Generated SQL (may be empty).',
						),
					),
				)
			);
		}

		if ( function_exists( 'register_graphql_input_type' ) ) {
			register_graphql_input_type(
				'DPGTaxTermsRequestInput',
				array(
					'description' => 'Request terms for a taxonomy (optionally limited to specific term IDs).',
					'fields'      => array(
						'taxonomy' => array(
							'type'        => 'String',
							'description' => 'Taxonomy slug.',
						),
						'termIds'  => array(
							'type'        => array( 'list_of' => 'Int' ),
							'description' => 'Optional list of term IDs to include (leave empty for all).',
						),
					),
				)
			);

			register_graphql_input_type(
				'DPGTaxFilterInput',
				array(
					'description' => 'Taxonomy filter input.',
					'fields'      => array(
						'taxonomy' => array(
							'type'        => 'String',
							'description' => 'Taxonomy slug.',
						),
						'termIds'  => array(
							'type'        => array( 'list_of' => 'Int' ),
							'description' => 'Selected term IDs.',
						),
					),
				)
			);
		}

		register_graphql_field(
			'RootQuery',
			'dpgPosts',
			array(
				'type'        => 'DPGPostsResult',
				'description' => 'Query posts for Dynamic Post Grid Pro.',
				'args'        => array(
					'postTypes'     => array(
						'type'        => array( 'list_of' => 'String' ),
						'description' => 'Post type slugs.',
					),
					'search'        => array(
						'type'        => 'String',
						'description' => 'Search query.',
					),
					'filters'       => array(
						'type'        => array( 'list_of' => 'DPGTaxFilterInput' ),
						'description' => 'Taxonomy filters.',
					),
					'page'          => array(
						'type'        => 'Int',
						'description' => 'Page number (1-based).',
					),
					'postsPerPage'  => array(
						'type'        => 'Int',
						'description' => 'Override posts per page.',
					),
					'debug'         => array(
						'type'        => 'Boolean',
						'description' => 'Return admin-only debug data.',
					),
				),
				'resolve'     => static function ( $root, array $args ) {
					$query      = DPG_Query_Builder::query( $args );
					$debug_data = null;
					if ( ! empty( $args['debug'] ) && current_user_can( 'manage_options' ) ) {
						// Best-effort: reflect what we asked WP_Query to do.
						$sanitized_args = $args;
						unset( $sanitized_args['debug'] );
						$debug_data = array(
							'queryArgs' => wp_json_encode( $sanitized_args ),
							'resolvedPostTypes' => isset( $query->query_vars['post_type'] ) ? (array) $query->query_vars['post_type'] : array(),
							'search'   => isset( $query->query_vars['s'] ) ? (string) $query->query_vars['s'] : '',
							'sql'      => isset( $query->request ) ? (string) $query->request : '',
						);
					}
					$nodes = array_map(
						static function ( WP_Post $p ) {
							$thumb_id = (int) get_post_thumbnail_id( $p );
							$thumb_url = $thumb_id ? get_the_post_thumbnail_url( $p, 'medium' ) : '';
							$thumb_alt = $thumb_id ? get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
							$charset   = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'charset' ) : 'UTF-8';
							$title_raw = (string) get_the_title( $p );
							$excerpt_raw = (string) wp_strip_all_tags( get_the_excerpt( $p ) );
							return array(
								'databaseId' => (int) $p->ID,
								// Decode HTML entities so French punctuation renders correctly in JS text nodes.
								'title'      => (string) html_entity_decode( wp_specialchars_decode( $title_raw, ENT_QUOTES ), ENT_QUOTES, $charset ),
								'uri'        => (string) wp_parse_url( get_permalink( $p ), PHP_URL_PATH ),
								'postType'   => (string) $p->post_type,
								'excerpt'    => (string) html_entity_decode( wp_specialchars_decode( $excerpt_raw, ENT_QUOTES ), ENT_QUOTES, $charset ),
								'featuredImageUrl' => (string) $thumb_url,
								'featuredImageAlt' => (string) $thumb_alt,
							);
						},
						(array) $query->posts
					);
					return array(
						'nodes' => $nodes,
						'total' => (int) $query->found_posts,
						'pages' => (int) $query->max_num_pages,
						'debug' => $debug_data,
					);
				},
			)
		);

		register_graphql_field(
			'RootQuery',
			'dpgTaxTerms',
			array(
				'type'        => array( 'list_of' => 'DPGTaxonomyTerms' ),
				'description' => 'Fetch terms for one or more taxonomies (for DPG filter UI).',
				'args'        => array(
					'requests'   => array(
						'type'        => array( 'list_of' => 'DPGTaxTermsRequestInput' ),
						'description' => 'Taxonomy term requests.',
					),
					'taxonomies' => array(
						'type'        => array( 'list_of' => 'String' ),
						'description' => 'Taxonomy slugs to fetch.',
					),
				),
				'resolve'     => static function ( $root, array $args ) {
					$requests = array();
					if ( isset( $args['requests'] ) && is_array( $args['requests'] ) ) {
						foreach ( $args['requests'] as $req ) {
							if ( ! is_array( $req ) ) {
								continue;
							}
							$taxonomy = isset( $req['taxonomy'] ) ? sanitize_key( (string) $req['taxonomy'] ) : '';
							if ( ! $taxonomy ) {
								continue;
							}
							$term_ids = isset( $req['termIds'] ) ? (array) $req['termIds'] : array();
							$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );
							$requests[] = array(
								'taxonomy' => $taxonomy,
								'termIds'  => $term_ids,
							);
						}
					}

					if ( ! $requests ) {
						$taxonomies = isset( $args['taxonomies'] ) ? (array) $args['taxonomies'] : array();
						$taxonomies = array_values( array_filter( array_map( 'sanitize_key', $taxonomies ) ) );
						foreach ( $taxonomies as $taxonomy ) {
							$requests[] = array(
								'taxonomy' => $taxonomy,
								'termIds'  => array(),
							);
						}
					}

					$out        = array();

					foreach ( $requests as $req ) {
						$taxonomy = (string) ( $req['taxonomy'] ?? '' );
						$term_ids = isset( $req['termIds'] ) ? (array) $req['termIds'] : array();
						if ( ! taxonomy_exists( $taxonomy ) ) {
							continue;
						}
						$args = array(
							'taxonomy'   => $taxonomy,
							'hide_empty' => false,
							'number'     => 200,
							'orderby'    => 'name',
							'order'      => 'ASC',
						);
						if ( $term_ids ) {
							$args['include'] = array_values( array_unique( array_map( 'intval', $term_ids ) ) );
							$args['number']  = 0; // allow full include list.
						}
						$terms = get_terms( $args );
						if ( is_wp_error( $terms ) ) {
							continue;
						}
						$out[] = array(
							'taxonomy' => $taxonomy,
							'label'    => (string) ( get_taxonomy( $taxonomy )->labels->singular_name ?? $taxonomy ),
							'terms'    => array_map(
								static function ( WP_Term $t ) {
									$link = get_term_link( $t );
									$uri  = is_wp_error( $link ) ? '' : (string) wp_parse_url( (string) $link, PHP_URL_PATH );
									return array(
										'id'   => (int) $t->term_id,
										'name' => (string) $t->name,
										'uri'  => $uri,
									);
								},
								$terms
							),
						);
					}

					return $out;
				},
			)
		);

		register_graphql_field(
			'RootQuery',
			'dpgTermSections',
			array(
				'type'        => array( 'list_of' => 'DPGTermPostsSection' ),
				'description' => 'Fetch posts grouped by term for a single taxonomy (used by the Categories layout).',
				'args'        => array(
					'taxonomy'     => array(
						'type'        => 'String',
						'description' => 'Taxonomy slug to group by.',
					),
					'termIds'      => array(
						'type'        => array( 'list_of' => 'Int' ),
						'description' => 'Optional list of term IDs to include (leave empty for all).',
					),
					'postTypes'    => array(
						'type'        => array( 'list_of' => 'String' ),
						'description' => 'Post type slugs.',
					),
					'search'       => array(
						'type'        => 'String',
						'description' => 'Search query.',
					),
					'filters'      => array(
						'type'        => array( 'list_of' => 'DPGTaxFilterInput' ),
						'description' => 'Additional taxonomy filters (combined with the grouped term using AND).',
					),
					'postsPerPage' => array(
						'type'        => 'Int',
						'description' => 'Maximum posts to return per term.',
					),
				),
				'resolve'     => static function ( $root, array $args ) {
					$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( (string) $args['taxonomy'] ) : '';
					if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
						return array();
					}

					$term_ids = isset( $args['termIds'] ) ? (array) $args['termIds'] : array();
					$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );
					$term_ids = array_values( array_unique( $term_ids ) );

					$terms_args = array(
						'taxonomy'   => $taxonomy,
						'hide_empty' => true,
						'number'     => 200,
						'orderby'    => 'name',
						'order'      => 'ASC',
					);
					if ( $term_ids ) {
						$terms_args['include']    = $term_ids;
						$terms_args['hide_empty'] = false;
						$terms_args['number']     = 0;
					}

					$terms = get_terms( $terms_args );
					if ( is_wp_error( $terms ) ) {
						return array();
					}

					$post_types = isset( $args['postTypes'] ) ? (array) $args['postTypes'] : array();
					$search     = array_key_exists( 'search', $args ) ? $args['search'] : null;
					$filters    = isset( $args['filters'] ) ? (array) $args['filters'] : array();
					$ppp        = isset( $args['postsPerPage'] ) ? (int) $args['postsPerPage'] : 0;

					$node_from_post = static function ( WP_Post $p ) {
						$thumb_id  = (int) get_post_thumbnail_id( $p );
						$thumb_url = $thumb_id ? get_the_post_thumbnail_url( $p, 'medium' ) : '';
						$thumb_alt = $thumb_id ? get_post_meta( $thumb_id, '_wp_attachment_image_alt', true ) : '';
						$charset   = function_exists( 'get_bloginfo' ) ? (string) get_bloginfo( 'charset' ) : 'UTF-8';
						$title_raw   = (string) get_the_title( $p );
						$excerpt_raw = (string) wp_strip_all_tags( get_the_excerpt( $p ) );
						return array(
							'databaseId'       => (int) $p->ID,
							'title'            => (string) html_entity_decode( wp_specialchars_decode( $title_raw, ENT_QUOTES ), ENT_QUOTES, $charset ),
							'uri'              => (string) wp_parse_url( get_permalink( $p ), PHP_URL_PATH ),
							'postType'         => (string) $p->post_type,
							'excerpt'          => (string) html_entity_decode( wp_specialchars_decode( $excerpt_raw, ENT_QUOTES ), ENT_QUOTES, $charset ),
							'featuredImageUrl' => (string) $thumb_url,
							'featuredImageAlt' => (string) $thumb_alt,
						);
					};

					$out = array();
					foreach ( (array) $terms as $t ) {
						if ( ! ( $t instanceof WP_Term ) ) {
							continue;
						}

						$q_args = array(
							'postTypes' => $post_types,
							'search'    => $search,
							'filters'   => array_merge(
								$filters,
								array(
									array(
										'taxonomy' => $taxonomy,
										'termIds'  => array( (int) $t->term_id ),
									),
								)
							),
							'page'      => 1,
						);
						if ( $ppp > 0 ) {
							$q_args['postsPerPage'] = $ppp;
						}

						$query = DPG_Query_Builder::query( $q_args );
						$nodes = array_map( $node_from_post, (array) $query->posts );

						$link = get_term_link( $t );
						$uri  = is_wp_error( $link ) ? '' : (string) wp_parse_url( (string) $link, PHP_URL_PATH );

						$out[] = array(
							'term'  => array(
								'taxonomy' => $taxonomy,
								'id'       => (int) $t->term_id,
								'name'     => (string) $t->name,
								'uri'      => $uri,
							),
							'posts' => array(
								'nodes' => $nodes,
								'total' => (int) $query->found_posts,
								'pages' => (int) $query->max_num_pages,
								'debug' => null,
							),
						);
					}

					return $out;
				},
			)
		);
	}
}
