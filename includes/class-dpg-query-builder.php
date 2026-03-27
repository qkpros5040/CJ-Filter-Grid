<?php
/**
 * @package DynamicPostGridPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DPG_Query_Builder {
	/**
	 * Build a WP_Query for the grid.
	 *
	 * @param array $args {
	 *   @type array  $postTypes
	 *   @type string $search
	 *   @type array  $filters
	 *   @type int    $page
	 *   @type int    $postsPerPage
	 * }
	 */
	public static function query( array $args ): WP_Query {
		$post_types_arg = $args['postTypes'] ?? array();
		$post_types_raw = is_array( $post_types_arg ) ? $post_types_arg : array( (string) $post_types_arg );
		$post_types     = array();
		foreach ( $post_types_raw as $pt ) {
			$pt = (string) $pt;
			$pt = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $pt ) ) );
			foreach ( $pt as $one ) {
				if ( $one ) {
					$post_types[] = $one;
				}
			}
		}
		$post_types = array_values( array_unique( $post_types ) );
		// Resolve common slug mismatches (underscores vs hyphens).
		$resolved_post_types = array();
		foreach ( $post_types as $pt ) {
			if ( post_type_exists( $pt ) ) {
				$resolved_post_types[] = $pt;
				continue;
			}
			$alt = str_replace( '_', '-', $pt );
			if ( $alt !== $pt && post_type_exists( $alt ) ) {
				$resolved_post_types[] = $alt;
				continue;
			}
			$alt = str_replace( '-', '_', $pt );
			if ( $alt !== $pt && post_type_exists( $alt ) ) {
				$resolved_post_types[] = $alt;
				continue;
			}
		}
		if ( $resolved_post_types ) {
			$post_types = array_values( array_unique( $resolved_post_types ) );
		}
		if ( ! $post_types ) {
			$settings   = DPG_Plugin::get_settings();
			$post_types = $settings['post_types'];
		}

		$search = '';
		if ( array_key_exists( 'search', $args ) && null !== $args['search'] ) {
			if ( is_scalar( $args['search'] ) || ( is_object( $args['search'] ) && method_exists( $args['search'], '__toString' ) ) ) {
				$search = sanitize_text_field( (string) $args['search'] );
			}
		}
		$page   = isset( $args['page'] ) ? (int) $args['page'] : 1;
		if ( $page < 1 ) {
			$page = 1;
		}

		$ppp = isset( $args['postsPerPage'] ) ? (int) $args['postsPerPage'] : 0;
		if ( $ppp < 1 ) {
			$settings = DPG_Plugin::get_settings();
			$ppp      = (int) $settings['posts_per_page'];
		}

		$query_args = array(
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'paged'                  => $page,
			'posts_per_page'         => $ppp,
			'ignore_sticky_posts'    => true,
			'no_found_rows'          => false,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
			// Ensure external query filters can't break GraphQL results (e.g. forcing WHERE 1=2).
			'suppress_filters'       => true,
		);
		if ( '' !== $search ) {
			$query_args['s']              = $search;
			$query_args['search_columns'] = array( 'post_title', 'post_excerpt', 'post_content' );
		}

		$filters = isset( $args['filters'] ) ? (array) $args['filters'] : array();
		$tax_q   = array();
		foreach ( $filters as $filter ) {
			if ( ! is_array( $filter ) ) {
				continue;
			}
			$taxonomy = isset( $filter['taxonomy'] ) ? sanitize_key( (string) $filter['taxonomy'] ) : '';
			$term_ids = isset( $filter['termIds'] ) ? (array) $filter['termIds'] : array();
			$term_ids = array_values( array_filter( array_map( 'intval', $term_ids ) ) );
			if ( ! $taxonomy || ! $term_ids ) {
				continue;
			}
			$tax_q[] = array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => $term_ids,
				'operator' => 'IN',
			);
		}
		if ( $tax_q ) {
			$query_args['tax_query'] = array_merge( array( 'relation' => 'AND' ), $tax_q );
		}

		$query_args = (array) apply_filters( 'dpg_query_args', $query_args, $args );
		return new WP_Query( $query_args );
	}
}
