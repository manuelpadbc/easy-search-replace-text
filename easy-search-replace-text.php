<?php
/**
 * Plugin Name: Easy Search and Replace Text
 * Plugin URI: https://padbc.com/plugins/easy-search-replace-text
 * Description: Adds a simple and intuitive meta box in the post editor to search and replace text within content and titles, just like Google Docs. Works with posts, pages, and custom post types.
 * Version: 3.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: PADBC Dev Team
 * Author URI: https://padbc.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: easy-search-replace-text
 * Domain Path: /languages
 *
 * @package Easy_Search_Replace_Text
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'EASY_SEARCH_REPLACE_VERSION', '3.0.0' );
define( 'EASY_SEARCH_REPLACE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EASY_SEARCH_REPLACE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class.
 */
class Easy_Search_Replace_Text {

	/**
	 * Instance of this class.
	 *
	 * @var Easy_Search_Replace_Text
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Easy_Search_Replace_Text
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Load plugin textdomain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'easy-search-replace-text',
			false,
			dirname( plugin_basename( __FILE__ ) ) . '/languages'
		);
	}

	/**
	 * Add meta box to post editor screens.
	 */
	public function add_meta_box() {
		// Get all public post types
		$post_types = get_post_types( array( 'public' => true ), 'names' );

		// Add meta box to each post type
		foreach ( $post_types as $post_type ) {
			add_meta_box(
				'easy_search_replace_metabox',
				__( 'Easy Search and Replace Text', 'easy-search-replace-text' ),
				array( $this, 'render_meta_box' ),
				$post_type,
				'normal',
				'high'
			);
		}
	}

	/**
	 * Render the meta box content.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_meta_box( $post ) {
		// Check if user has permission to edit the post
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}

		// Add nonce for security
		wp_nonce_field( 'easy_search_replace_nonce_action', 'easy_search_replace_nonce' );
		?>
		<div class="easy-search-replace-wrapper">
			<p class="description">
				<?php
				echo esc_html__(
					'Use this tool to search and replace text in your post title and content. Similar to Google Docs Find & Replace feature.',
					'easy-search-replace-text'
				);
				?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="esr_search_text">
								<?php esc_html_e( 'Search for:', 'easy-search-replace-text' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="esr_search_text"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Enter text to find...', 'easy-search-replace-text' ); ?>"
								autocomplete="off"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="esr_replace_text">
								<?php esc_html_e( 'Replace with:', 'easy-search-replace-text' ); ?>
							</label>
						</th>
						<td>
							<input
								type="text"
								id="esr_replace_text"
								class="regular-text"
								placeholder="<?php esc_attr_e( 'Enter replacement text...', 'easy-search-replace-text' ); ?>"
								autocomplete="off"
							/>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="esr_case_sensitive">
								<?php esc_html_e( 'Options:', 'easy-search-replace-text' ); ?>
							</label>
						</th>
						<td>
							<fieldset>
								<label for="esr_case_sensitive">
									<input
										type="checkbox"
										id="esr_case_sensitive"
										name="esr_case_sensitive"
										value="1"
									/>
									<?php esc_html_e( 'Case sensitive', 'easy-search-replace-text' ); ?>
								</label>
								<br>
								<label for="esr_whole_words">
									<input
										type="checkbox"
										id="esr_whole_words"
										name="esr_whole_words"
										value="1"
									/>
									<?php esc_html_e( 'Match whole words only', 'easy-search-replace-text' ); ?>
								</label>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>
			<p class="submit">
				<button
					type="button"
					class="button button-primary button-large"
					id="esr_replace_btn"
					aria-label="<?php esc_attr_e( 'Replace text in title and content', 'easy-search-replace-text' ); ?>"
				>
					<span class="dashicons dashicons-search" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Replace in Title & Content', 'easy-search-replace-text' ); ?>
				</button>
				<span class="spinner" id="esr_spinner" style="float: none; margin: 4px 10px 0;"></span>
			</p>
			<div id="esr_result_message" style="display: none; margin-top: 10px;"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts and styles for the admin area.
	 *
	 * @param string $hook The current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on post editor screens
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		// Check if user can edit posts
		$post_id = isset( $_GET['post'] ) ? intval( $_GET['post'] ) : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Enqueue the script
		wp_enqueue_script(
			'easy-search-replace-script',
			EASY_SEARCH_REPLACE_PLUGIN_URL . 'assets/js/easy-search-replace.js',
			array( 'jquery', 'wp-data', 'wp-editor' ),
			EASY_SEARCH_REPLACE_VERSION,
			true
		);

		// Localize script for translations and AJAX
		wp_localize_script(
			'easy-search-replace-script',
			'esrData',
			array(
				'nonce'           => wp_create_nonce( 'easy_search_replace_nonce_action' ),
				'strings'         => array(
					'emptySearch'     => __( 'Please enter text to search for.', 'easy-search-replace-text' ),
					'success'         => __( 'Text replaced successfully!', 'easy-search-replace-text' ),
					'successDetails'  => __( 'Replaced %d occurrence(s) in title and content.', 'easy-search-replace-text' ),
					'noMatches'       => __( 'No matches found for the search text.', 'easy-search-replace-text' ),
					'error'           => __( 'An error occurred. Please try again.', 'easy-search-replace-text' ),
					'imageWarning'    => __( 'Note: Image URLs and HTML attributes are preserved.', 'easy-search-replace-text' ),
				),
			)
		);

		// Enqueue styles
		wp_enqueue_style(
			'easy-search-replace-style',
			EASY_SEARCH_REPLACE_PLUGIN_URL . 'assets/css/easy-search-replace.css',
			array(),
			EASY_SEARCH_REPLACE_VERSION
		);
	}
}

/**
 * Initialize the plugin.
 */
function easy_search_replace_text_init() {
	return Easy_Search_Replace_Text::get_instance();
}

// Start the plugin
add_action( 'init', 'easy_search_replace_text_init' );

