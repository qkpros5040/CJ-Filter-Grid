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
				'DPGPostsResult',
				array(
					'description' => 'DPG posts query response.',
					'fields'      => array(
						'nodes' => array(
							'type'        => array( 'list_of' => 'ContentNode' ),
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
					),
				)
			);
		}

		if ( function_exists( 'register_graphql_input_type' ) ) {
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
				),
				'resolve'     => static function ( $root, array $args ) {
					$query = DPG_Query_Builder::query( $args );
					return array(
						'nodes' => $query->posts,
						'total' => (int) $query->found_posts,
						'pages' => (int) $query->max_num_pages,
					);
				},
			)
		);
	}
}

