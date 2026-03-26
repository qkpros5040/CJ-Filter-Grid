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

	public function handle_save(): void {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_POST['dpg_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dpg_settings_nonce'] ) ), 'dpg_save_settings' ) ) {
			return;
		}

		$post_types = array();
		if ( isset( $_POST['dpg_post_types'] ) && is_array( $_POST['dpg_post_types'] ) ) {
			$post_types = array_values( array_filter( array_map( 'sanitize_key', wp_unslash( $_POST['dpg_post_types'] ) ) ) );
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
				$taxonomy_config[ sanitize_key( $taxonomy ) ] = (int) (bool) $enabled;
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

		wp_safe_redirect( add_query_arg( array( 'page' => 'dpg-settings', 'updated' => '1' ), admin_url( 'options-general.php' ) ) );
		exit;
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = DPG_Plugin::get_settings();

		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );

		?>
		<div class="wrap">
			<h1><?php echo esc_html( 'Dynamic Grid Settings' ); ?></h1>

			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'Settings saved.' ); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'dpg_save_settings', 'dpg_settings_nonce' ); ?>

				<h2><?php echo esc_html( 'Post Types' ); ?></h2>
				<p><?php echo esc_html( 'Select which post types appear in the grid.' ); ?></p>
				<ul>
					<?php foreach ( $post_types as $slug => $obj ) : ?>
						<li>
							<label>
								<input type="checkbox" name="dpg_post_types[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $settings['post_types'], true ) ); ?> />
								<?php echo esc_html( $obj->labels->singular_name ?? $slug ); ?>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>

				<h2><?php echo esc_html( 'Taxonomy Filters' ); ?></h2>
				<p><?php echo esc_html( 'Enable/disable taxonomy filter panels. (1 = show, 0 = hide)' ); ?></p>
				<table class="widefat striped" style="max-width: 760px;">
					<thead>
						<tr>
							<th><?php echo esc_html( 'Taxonomy' ); ?></th>
							<th><?php echo esc_html( 'Show' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $taxonomies as $tax_slug => $tax_obj ) : ?>
							<tr>
								<td><?php echo esc_html( $tax_obj->labels->singular_name ?? $tax_slug ); ?> <code><?php echo esc_html( $tax_slug ); ?></code></td>
								<td>
									<input type="checkbox" name="dpg_taxonomy_config[<?php echo esc_attr( $tax_slug ); ?>]" value="1" <?php checked( ! empty( $settings['taxonomy_config'][ $tax_slug ] ) ); ?> />
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<h2><?php echo esc_html( 'Pagination' ); ?></h2>
				<p>
					<label>
						<?php echo esc_html( 'Posts per page' ); ?>
						<input type="number" min="1" step="1" name="dpg_posts_per_page" value="<?php echo esc_attr( (string) $settings['posts_per_page'] ); ?>" />
					</label>
				</p>

				<p><button type="submit" class="button button-primary"><?php echo esc_html( 'Save Settings' ); ?></button></p>
			</form>
		</div>
		<?php
	}
}

