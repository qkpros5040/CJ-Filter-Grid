<?php
/**
 * @package DynamicPostGridPro
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DPG_Admin {
	private static $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu(): void {
		add_options_page(
			'Dynamic Grid',
			'Dynamic Grid',
			'manage_options',
			'dpg-settings',
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'settings_page_dpg-settings' !== $hook_suffix ) {
			return;
		}

		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_style(
			'dpg-admin',
			DPG_URL . 'assets/admin.css',
			array(),
			(string) filemtime( DPG_PATH . 'assets/admin.css' )
		);
		wp_enqueue_script(
			'dpg-admin',
			DPG_URL . 'assets/admin.js',
			array(),
			(string) filemtime( DPG_PATH . 'assets/admin.js' ),
			true
		);
	}

	public function handle_save(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['dpg_action'] ) ) {
			return;
		}

		$action = sanitize_key( (string) wp_unslash( $_POST['dpg_action'] ) );
		if ( ! in_array( $action, array( 'save', 'seed_terms' ), true ) ) {
			return;
		}

		if ( ! isset( $_POST['dpg_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dpg_settings_nonce'] ) ), 'dpg_save_settings' ) ) {
			return;
		}

		if ( 'seed_terms' === $action ) {
			DPG_Plugin::seed_article_taxonomy_terms( false, true );
			wp_safe_redirect( add_query_arg( array( 'page' => 'dpg-settings', 'seeded' => '1' ), admin_url( 'options-general.php' ) ) );
			exit;
		}

		$post_types = array();
		if ( isset( $_POST['dpg_post_types'] ) && is_array( $_POST['dpg_post_types'] ) ) {
			$post_types = array_values( array_filter( array_map( 'sanitize_key', wp_unslash( $_POST['dpg_post_types'] ) ) ) );
			$post_types = array_values(
				array_filter(
					$post_types,
					static function ( $pt ) {
						return post_type_exists( (string) $pt );
					}
				)
			);
		}

		$ppp = 9;
		if ( isset( $_POST['dpg_posts_per_page'] ) ) {
			$ppp = (int) sanitize_text_field( wp_unslash( $_POST['dpg_posts_per_page'] ) );
		}
		if ( $ppp < 1 ) {
			$ppp = 9;
		}

		$taxonomy_config = array();
		if ( isset( $_POST['dpg_taxonomy_config'] ) && is_array( $_POST['dpg_taxonomy_config'] ) ) {
			foreach ( wp_unslash( $_POST['dpg_taxonomy_config'] ) as $taxonomy => $enabled ) {
				$taxonomy = sanitize_key( $taxonomy );
				if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}
				$taxonomy_config[ $taxonomy ] = (int) (bool) $enabled;
			}
		}

		update_option(
			'dpg_settings',
			array(
				'post_types'      => $post_types ? $post_types : array( 'post' ),
				'taxonomy_config' => $taxonomy_config,
				'posts_per_page'  => $ppp,
			)
		);

				$grids = array();
		if ( isset( $_POST['dpg_grids'] ) && is_array( $_POST['dpg_grids'] ) ) {
			foreach ( wp_unslash( $_POST['dpg_grids'] ) as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$key = isset( $row['key'] ) ? sanitize_key( (string) $row['key'] ) : '';
				if ( ! $key ) {
					continue;
				}
				$label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : $key;

				$row_post_types = array();
				if ( isset( $row['post_types'] ) && is_array( $row['post_types'] ) ) {
					$row_post_types = array_values( array_filter( array_map( 'sanitize_key', (array) $row['post_types'] ) ) );
				}
				$row_post_types = array_values( array_unique( $row_post_types ) );
				if ( ! $row_post_types ) {
					$row_post_types = array( 'post' );
				}

				$row_taxonomies = array();
				if ( isset( $row['taxonomies'] ) && is_array( $row['taxonomies'] ) ) {
					$row_taxonomies = array_values( array_filter( array_map( 'sanitize_key', (array) $row['taxonomies'] ) ) );
				}
				$row_taxonomies = array_values( array_unique( $row_taxonomies ) );

				$row_term_ids = array();
				if ( isset( $row['terms'] ) && is_array( $row['terms'] ) ) {
					foreach ( $row['terms'] as $taxonomy => $ids ) {
						$taxonomy = sanitize_key( (string) $taxonomy );
						if ( ! $taxonomy || ! in_array( $taxonomy, $row_taxonomies, true ) ) {
							continue;
						}
						$ids = is_array( $ids ) ? $ids : array( $ids );
						$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
						$ids = array_values( array_unique( $ids ) );
						if ( $ids ) {
							$row_term_ids[ $taxonomy ] = $ids;
						}
					}
				}

				$row_ppp = isset( $row['posts_per_page'] ) ? (int) $row['posts_per_page'] : 9;
				if ( $row_ppp < 1 ) {
					$row_ppp = 9;
				}

				$row_taxonomy_titles = array();
				if ( isset( $row['taxonomy_titles'] ) && is_array( $row['taxonomy_titles'] ) ) {
					foreach ( $row['taxonomy_titles'] as $taxonomy => $title ) {
						$taxonomy = sanitize_key( (string) $taxonomy );
						if ( ! $taxonomy || ! in_array( $taxonomy, $row_taxonomies, true ) ) {
							continue;
						}
						$title = sanitize_text_field( (string) $title );
						if ( '' !== $title ) {
							$row_taxonomy_titles[ $taxonomy ] = $title;
						}
					}
				}

				$row_filter_mode = isset( $row['filter_mode'] ) ? sanitize_key( (string) $row['filter_mode'] ) : 'ajax';
				if ( ! in_array( $row_filter_mode, array( 'ajax', 'links' ), true ) ) {
					$row_filter_mode = 'ajax';
				}

				$row_archive_sync = 1;
				if ( isset( $row['archive_sync'] ) ) {
					$row_archive_sync = (int) (bool) $row['archive_sync'];
				}

				$row_layout = isset( $row['layout'] ) ? sanitize_key( (string) $row['layout'] ) : 'card';
				if ( ! in_array( $row_layout, array( 'card', 'logo', 'overlay', 'blog', 'categories' ), true ) ) {
					$row_layout = 'card';
				}

				$row_pagination = isset( $row['pagination'] ) ? sanitize_key( (string) $row['pagination'] ) : 'buttons';
				if ( ! in_array( $row_pagination, array( 'buttons', 'numeric' ), true ) ) {
					$row_pagination = 'buttons';
				}

				$row_ad_shortcode = '';
				if ( isset( $row['ad_shortcode'] ) ) {
					$row_ad_shortcode = sanitize_text_field( (string) $row['ad_shortcode'] );
				}

				$row_ad_image_id = 0;
				if ( isset( $row['ad_image_id'] ) ) {
					$row_ad_image_id = (int) $row['ad_image_id'];
				}
				$row_ad_link = '';
				if ( isset( $row['ad_link'] ) ) {
					$row_ad_link = esc_url_raw( (string) $row['ad_link'] );
				}
				$row_ad_sticky_offset = 18;
				if ( isset( $row['ad_sticky_offset'] ) ) {
					$row_ad_sticky_offset = (int) $row['ad_sticky_offset'];
				}
				if ( $row_ad_sticky_offset < 0 ) {
					$row_ad_sticky_offset = 0;
				}

				$grids[ $key ] = array(
					'label'          => $label,
					'post_types'     => $row_post_types,
					'taxonomies'     => $row_taxonomies,
					'term_ids'       => $row_term_ids,
					'taxonomy_titles'=> $row_taxonomy_titles,
					'filter_mode'    => $row_filter_mode,
					'archive_sync'   => $row_archive_sync,
					'posts_per_page' => $row_ppp,
					'layout'         => $row_layout,
					'pagination'     => $row_pagination,
					'ad_shortcode'   => $row_ad_shortcode,
					'ad_image_id'    => $row_ad_image_id,
					'ad_link'        => $row_ad_link,
					'ad_sticky_offset' => $row_ad_sticky_offset,
				);
			}
		}
		update_option( 'dpg_grids', $grids );

		wp_safe_redirect( add_query_arg( array( 'page' => 'dpg-settings', 'updated' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = DPG_Plugin::get_settings();
		$grids    = DPG_Plugin::get_grids();

		// Use show_ui so CPTs/taxonomies managed in wp-admin show up here, even if not public.
		$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );
		$taxonomies = get_taxonomies( array( 'show_ui' => true ), 'objects' );
		$taxonomy_terms = array();
		foreach ( $taxonomies as $tax_slug => $tax_obj ) {
			$terms = get_terms(
				array(
					'taxonomy'   => $tax_slug,
					'hide_empty' => false,
					'number'     => 200,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);
			if ( is_wp_error( $terms ) ) {
				continue;
			}
			$taxonomy_terms[ $tax_slug ] = $terms;
		}

		?>
		<div class="wrap dpg-admin">
			<h1><?php echo esc_html( 'Dynamic Grid Settings' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'Settings saved.' ); ?></p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['seeded'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'Default taxonomy terms seeded.' ); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<input type="hidden" name="dpg_action" value="save" />
				<?php wp_nonce_field( 'dpg_save_settings', 'dpg_settings_nonce' ); ?>

				<div class="dpg-admin__grid">
					<div class="dpg-card">
						<h2><?php echo esc_html( 'Defaults' ); ?></h2>
						<p class="dpg-help"><?php echo esc_html( 'These defaults apply when a shortcode does not specify a preset or overrides.' ); ?></p>

						<h3 style="margin: 14px 0 8px;"><?php echo esc_html( 'Post Types' ); ?></h3>
						<div class="dpg-posttypes">
							<?php foreach ( $post_types as $slug => $obj ) : ?>
								<label>
									<input type="checkbox" name="dpg_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $settings['post_types'], true ) ); ?> />
									<span class="dpg-posttypes__name"><?php echo esc_html( $obj->labels->singular_name ?? $slug ); ?></span>
									<code class="dpg-posttypes__slug"><?php echo esc_html( $slug ); ?></code>
								</label>
							<?php endforeach; ?>
						</div>

						<h3 style="margin: 16px 0 8px;"><?php echo esc_html( 'Taxonomy Filters' ); ?></h3>
						<p class="dpg-help"><?php echo esc_html( 'Choose which taxonomies can appear as filter panels by default.' ); ?></p>
						<table class="widefat striped dpg-wide">
							<thead>
								<tr>
									<th><?php echo esc_html( 'Taxonomy' ); ?></th>
									<th style="width:120px;"><?php echo esc_html( 'Enabled' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $taxonomies as $tax_slug => $tax_obj ) : ?>
									<tr>
										<td>
											<strong><?php echo esc_html( $tax_obj->labels->singular_name ?? $tax_slug ); ?></strong>
											<div style="color:#646970;"><code><?php echo esc_html( $tax_slug ); ?></code></div>
										</td>
										<td>
											<label>
												<input type="checkbox" name="dpg_taxonomy_config[<?php echo esc_attr( $tax_slug ); ?>]" value="1" <?php checked( ! empty( $settings['taxonomy_config'][ $tax_slug ] ) ); ?> />
												<?php echo esc_html( 'Show' ); ?>
											</label>
										</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<h3 style="margin: 16px 0 8px;"><?php echo esc_html( 'Pagination' ); ?></h3>
						<p>
							<label>
								<?php echo esc_html( 'Posts per page' ); ?>
								<input type="number" min="1" step="1" name="dpg_posts_per_page" value="<?php echo esc_attr( (string) $settings['posts_per_page'] ); ?>" style="width: 110px; margin-left: 8px;" />
							</label>
						</p>

						<div class="dpg-actions">
							<button type="submit" class="button button-primary"><?php echo esc_html( 'Save Settings' ); ?></button>
							<button type="submit" class="button" name="dpg_action" value="seed_terms"><?php echo esc_html( 'Seed default taxonomy terms' ); ?></button>
						</div>
						<p class="dpg-help" style="margin-top:10px;">
							<?php echo esc_html( 'Adds the default terms for the built-in Article taxonomies (won’t create duplicates).' ); ?>
						</p>
					</div>

					<div class="dpg-card">
						<h2><?php echo esc_html( 'Grid Presets (Shortcodes)' ); ?></h2>
						<p class="dpg-help"><?php echo esc_html( 'Create multiple named configurations and use them via the shortcode.' ); ?></p>

						<div class="dpg-table-wrap">
						<table class="widefat striped dpg-grids-table" id="dpg-grids">
					<thead>
						<tr>
							<th><?php echo esc_html( 'Key' ); ?></th>
							<th><?php echo esc_html( 'Label' ); ?></th>
							<th><?php echo esc_html( 'Post Types' ); ?></th>
							<th><?php echo esc_html( 'Taxonomies' ); ?></th>
							<th><?php echo esc_html( 'Layout' ); ?></th>
							<th><?php echo esc_html( 'Pagination' ); ?></th>
							<th><?php echo esc_html( 'Posts/Page' ); ?></th>
							<th><?php echo esc_html( 'Shortcode' ); ?></th>
							<th><?php echo esc_html( 'Remove' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$rows = $grids ? $grids : array(
							'' => array(
								'label'          => '',
								'post_types'     => array( 'post' ),
								'taxonomies'     => array(),
								'term_ids'       => array(),
								'posts_per_page' => 9,
							),
						);
						$i = 0;
						foreach ( $rows as $key => $grid ) :
							$grid_label      = $grid['label'] ?? $key;
							$grid_post_types = (array) ( $grid['post_types'] ?? array( 'post' ) );
							$grid_taxonomies = (array) ( $grid['taxonomies'] ?? array() );
							$grid_term_ids   = (array) ( $grid['term_ids'] ?? array() );
							$grid_tax_titles = (array) ( $grid['taxonomy_titles'] ?? array() );
							$grid_filter_mode = (string) ( $grid['filter_mode'] ?? 'ajax' );
							$grid_archive_sync = (int) ( $grid['archive_sync'] ?? 1 );
							$grid_ppp        = (int) ( $grid['posts_per_page'] ?? 9 );
							$grid_layout     = (string) ( $grid['layout'] ?? 'card' );
							$grid_pagination = (string) ( $grid['pagination'] ?? 'buttons' );
							$grid_ad_shortcode = (string) ( $grid['ad_shortcode'] ?? '' );
							$grid_ad_image_id = (int) ( $grid['ad_image_id'] ?? 0 );
							$grid_ad_link = (string) ( $grid['ad_link'] ?? '' );
							$grid_ad_sticky_offset = (int) ( $grid['ad_sticky_offset'] ?? 18 );
							$grid_ad_image_url = $grid_ad_image_id ? (string) wp_get_attachment_image_url( $grid_ad_image_id, 'medium' ) : '';
							$shortcode_key   = $key ? (string) $key : 'your_key';
							?>
							<tr class="dpg-grid-row" data-grid-index="<?php echo esc_attr( (string) $i ); ?>">
								<td>
									<input type="text" class="dpg-grid-key" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][key]" value="<?php echo esc_attr( (string) $key ); ?>" placeholder="e.g. products" />
								</td>
								<td>
									<input type="text" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][label]" value="<?php echo esc_attr( (string) $grid_label ); ?>" placeholder="e.g. Products Grid" />
								</td>
								<td>
									<select class="dpg-ms" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][post_types][]" multiple size="4" data-placeholder="<?php echo esc_attr( 'Select post types' ); ?>" style="display:none;">
										<?php foreach ( $post_types as $slug => $obj ) : ?>
											<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( in_array( $slug, $grid_post_types, true ) ); ?>>
												<?php echo esc_html( $obj->labels->singular_name ?? $slug ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<div class="dpg-ms-ui" data-for="post_types"></div>
								</td>
								<td>
									<select class="dpg-ms" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][taxonomies][]" data-role="taxonomies" multiple size="4" data-placeholder="<?php echo esc_attr( 'Select taxonomies' ); ?>" style="display:none;">
										<?php foreach ( $taxonomies as $tax_slug => $tax_obj ) : ?>
											<option value="<?php echo esc_attr( $tax_slug ); ?>" <?php selected( in_array( $tax_slug, $grid_taxonomies, true ) ); ?>>
												<?php echo esc_html( ( $tax_obj->labels->singular_name ?? $tax_slug ) . ' (' . $tax_slug . ')' ); ?>
											</option>
										<?php endforeach; ?>
									</select>
									<div class="dpg-ms-ui" data-for="taxonomies"></div>
									<p style="margin:10px 0 0;">
										<label style="display:flex; gap:8px; align-items:center; margin:0;">
											<span style="color:#646970; font-size:12px;"><?php echo esc_html( 'Filter mode' ); ?></span>
											<select name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][filter_mode]" style="max-width: 190px;">
												<option value="ajax" <?php selected( 'ajax' === $grid_filter_mode ); ?>><?php echo esc_html( 'AJAX (checkboxes)' ); ?></option>
												<option value="links" <?php selected( 'links' === $grid_filter_mode ); ?>><?php echo esc_html( 'Links (term pages)' ); ?></option>
											</select>
										</label>
									</p>
									<p style="margin:8px 0 0;">
										<label style="display:flex; gap:8px; align-items:center; margin:0;">
											<input type="hidden" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][archive_sync]" value="0" />
											<input type="checkbox" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][archive_sync]" value="1" <?php checked( 1 === (int) $grid_archive_sync ); ?> />
											<span style="color:#646970; font-size:12px;"><?php echo esc_html( 'Auto-select current term on term pages' ); ?></span>
										</label>
									</p>
									<details class="dpg-tax-details">
										<summary><?php echo esc_html( 'Configure terms & titles' ); ?></summary>
										<div class="dpg-tax-terms">
											<?php foreach ( $taxonomies as $tax_slug => $tax_obj ) : ?>
												<?php
												$terms = isset( $taxonomy_terms[ $tax_slug ] ) ? (array) $taxonomy_terms[ $tax_slug ] : array();
												$selected_ids = isset( $grid_term_ids[ $tax_slug ] ) ? (array) $grid_term_ids[ $tax_slug ] : array();
												?>
												<div class="dpg-tax-terms__group" data-taxonomy="<?php echo esc_attr( $tax_slug ); ?>" style="display:none;">
													<div class="dpg-tax-terms__heading">
														<?php echo esc_html( ( $tax_obj->labels->singular_name ?? $tax_slug ) . ' terms' ); ?>
													</div>
													<div class="dpg-tax-title dpg-tax-title--wrap" style="display:none;">
														<label>
															<span class="dpg-tax-title__label"><?php echo esc_html( 'Custom filter title (optional)' ); ?></span>
															<input type="text"
																name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][taxonomy_titles][<?php echo esc_attr( $tax_slug ); ?>]"
																value="<?php echo esc_attr( (string) ( $grid_tax_titles[ $tax_slug ] ?? '' ) ); ?>"
																placeholder="<?php echo esc_attr( (string) ( $tax_obj->labels->singular_name ?? $tax_slug ) ); ?>" />
														</label>
													</div>
													<select class="dpg-ms" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][terms][<?php echo esc_attr( $tax_slug ); ?>][]" multiple size="6" data-placeholder="<?php echo esc_attr( 'Select terms' ); ?>" style="display:none;">
														<?php foreach ( $terms as $t ) : ?>
															<option value="<?php echo esc_attr( (string) $t->term_id ); ?>" <?php selected( in_array( (int) $t->term_id, array_map( 'intval', $selected_ids ), true ) ); ?>>
																<?php echo esc_html( $t->name . ' (#' . (string) $t->term_id . ')' ); ?>
															</option>
														<?php endforeach; ?>
													</select>
													<div class="dpg-ms-ui" data-for="terms"></div>
													<div class="dpg-help">
														<?php echo esc_html( 'Leave empty to show all terms for this taxonomy.' ); ?>
													</div>
												</div>
											<?php endforeach; ?>
										</div>
									</details>
								</td>
								<td>
									<select name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][layout]" style="min-width: 120px;">
										<option value="card" <?php selected( 'card' === $grid_layout ); ?>><?php echo esc_html( 'Card' ); ?></option>
										<option value="logo" <?php selected( 'logo' === $grid_layout ); ?>><?php echo esc_html( 'Logo' ); ?></option>
										<option value="overlay" <?php selected( 'overlay' === $grid_layout ); ?>><?php echo esc_html( 'Overlay' ); ?></option>
										<option value="blog" <?php selected( 'blog' === $grid_layout ); ?>><?php echo esc_html( 'Blog' ); ?></option>
										<option value="categories" <?php selected( 'categories' === $grid_layout ); ?>><?php echo esc_html( 'Categories' ); ?></option>
									</select>
									<div style="margin-top:8px;">
										<div style="display:grid; gap:8px;">
											<span style="color:#646970; font-size:12px;"><?php echo esc_html( 'Sidebar ad (Blog/Categories layout)' ); ?></span>
											<input type="hidden" class="dpg-ad-image-id" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][ad_image_id]" value="<?php echo esc_attr( (string) $grid_ad_image_id ); ?>" />
											<div class="dpg-ad-preview" style="display:flex; gap:10px; align-items:center;">
												<?php if ( $grid_ad_image_url ) : ?>
													<img src="<?php echo esc_url( $grid_ad_image_url ); ?>" alt="" style="width:80px; height:auto; border-radius:8px; border:1px solid #e5e7eb;" />
												<?php else : ?>
													<div style="width:80px; height:60px; border-radius:8px; border:1px dashed #d1d5db; background:#fafafa;"></div>
												<?php endif; ?>
												<div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
													<button type="button" class="button dpg-ad-pick"><?php echo esc_html( 'Choose image' ); ?></button>
													<button type="button" class="button dpg-ad-remove" <?php disabled( ! $grid_ad_image_id ); ?>><?php echo esc_html( 'Remove' ); ?></button>
												</div>
											</div>
											<label style="display:grid; gap:6px; margin:0;">
												<span style="color:#646970; font-size:12px;"><?php echo esc_html( 'Ad link (optional)' ); ?></span>
												<input type="url" class="dpg-ad-link" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][ad_link]" value="<?php echo esc_attr( $grid_ad_link ); ?>" placeholder="https://…" />
											</label>
											<label style="display:grid; gap:6px; margin:0;">
												<span style="color:#646970; font-size:12px;"><?php echo esc_html( 'Sticky offset (px)' ); ?></span>
												<input type="number" min="0" step="1" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][ad_sticky_offset]" value="<?php echo esc_attr( (string) $grid_ad_sticky_offset ); ?>" style="max-width: 140px;" />
											</label>
											<input type="hidden" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][ad_shortcode]" value="<?php echo esc_attr( $grid_ad_shortcode ); ?>" />
										</div>
									</div>
								</td>
								<td>
									<select name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][pagination]" style="min-width: 140px;">
										<option value="buttons" <?php selected( 'buttons' === $grid_pagination ); ?>><?php echo esc_html( 'Buttons' ); ?></option>
										<option value="numeric" <?php selected( 'numeric' === $grid_pagination ); ?>><?php echo esc_html( 'Numeric' ); ?></option>
									</select>
								</td>
								<td>
									<input type="number" min="1" step="1" name="dpg_grids[<?php echo esc_attr( (string) $i ); ?>][posts_per_page]" value="<?php echo esc_attr( (string) $grid_ppp ); ?>" style="width: 90px;" />
								</td>
								<td><code class="dpg-shortcode"><?php echo esc_html( '[dpg_grid grid="' . $shortcode_key . '"]' ); ?></code></td>
								<td><button type="button" class="button dpg-remove-row"><?php echo esc_html( 'Remove' ); ?></button></td>
							</tr>
							<?php $i++; ?>
						<?php endforeach; ?>
					</tbody>
				</table>
						</div>

						<div class="dpg-actions">
							<button type="button" class="button" id="dpg-add-grid"><?php echo esc_html( 'Add Grid Preset' ); ?></button>
							<button type="submit" class="button button-primary"><?php echo esc_html( 'Save Settings' ); ?></button>
						</div>
						<p class="dpg-help" style="margin-top:10px;">
							<code>[dpg_grid grid="your_key"]</code>
							<?php echo esc_html( 'You can override per instance, e.g.' ); ?>
							<code>[dpg_grid grid="your_key" layout="overlay" posts_per_page="12"]</code>
						</p>
					</div>
				</div>
			</form>
		</div>
		<?php
	}
}
