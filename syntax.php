<?php
/*
Plugin Name: Syntax
Description: Preserve and highlight syntax of code examples in the WordPress editor.
Version: 0.9.2
Author: modularwp
Author URI: https://modularwp.com/
License: GPLv2 or later
Text Domain: syntax_textdomain
*/

/**
 * Syntax plugin class
 *
 * @see https://github.com/dtbaker/wordpress-mce-view-and-shortcode-editor
 */
class MDLR_Syntax {

	/**
	 * Constructor function
	 *
	 * Runs when class is instantiated.
	 */
	function __construct() {
		add_action( 'admin_head',                array( $this, 'enqueue_admin' ) );
		add_action( 'add_meta_boxes',            array( $this, 'add_meta' ) );
		add_action( 'save_post',                 array( $this, 'save_meta' ) );
		add_filter( 'mce_external_plugins',      array( $this, 'tinymce_plugin') );
		add_action( 'default_hidden_meta_boxes', array( $this, 'hide_meta_by_default' ), 10, 2 );

		add_filter( 'mce_buttons',               array( $this, 'tinymce_button' ) );
	}

	/**
	 * Registers TinyMCE plugin
	 *
	 * @param array $plugin_array An array of regsitered TinyMCE plugins.
	 */
	public function tinymce_plugin( $plugin_array ){
		$plugin_array['mdlr_syntax_plugin'] = plugins_url( 'js/mce-plugin.js', __FILE__ );

		return $plugin_array;
	}

	/**
	 * Loads back end scripts
	 */
	public function enqueue_admin() {
		$current_screen = get_current_screen();

		// Is this a screen that requires any Syntax features?
		if ( isset( $current_screen->post_type ) && post_type_supports( $current_screen->post_type, 'editor' ) ) {

			$localize_prism = array(
				'codeText'     => __( 'Code', 'syntax_textdomain' ),
				'addCodeText'  => __( 'Add Code', 'syntax_textdomain' ),
			);

			wp_register_script( 'syntax-editor-js', plugins_url( 'js/editor.js', __FILE__ ), array( 'wp-util', 'jquery' ), false, true );
			wp_localize_script( 'syntax-editor-js', 'syntax', $localize_prism );
			wp_enqueue_script( 'syntax-editor-js' );
		}
	}

	/**
	 * Adds syntax button to array
	 *
	 * Adds syntax button to array of TinyMCE buttons.
	 *
	 * @param array $buttons Array of TinyMCE buttons.
	 */
	public function tinymce_button( $buttons ){
		$kitchen_sink = array_pop ( $buttons );
		array_push( $buttons, 'mdlr_syntax' );
		array_push( $buttons, $kitchen_sink );

		return $buttons;
	}

	/**
	 * Hides meta box by default
	 *
	 * The user can toggle the visibility of each meta box. This function
	 * ensures that the meta box is hidden by default.
	 *
	 * @param array $hidden An array of meta boxes that are hidden by default.
	 * @param $screen The current screen of the WP admin.
	 */
	public function hide_meta_by_default( $hidden, $screen ) {
		array_push( $hidden, 'mdlr_syntax_meta' );

		return $hidden;
	}

	/**
	 * Adds a meta box to all post types that support the WordPress editor
	 */
	public function add_meta() {
		$suported_post_types = get_post_types_by_support( 'editor' );
		add_meta_box(
			'mdlr_syntax_meta',
			__( 'Syntax', 'syntax_textdomain' ),
			array( $this, 'meta_callback' ),
			$suported_post_types
		);
	}


	/**
	 * Outputs the content of the meta box
	 *
	 * @param object $post The current post object.
	 */
	function meta_callback( $post ) {
		wp_nonce_field( basename( __FILE__ ), 'syntax_nonce' );
		$saved_meta = get_post_meta( $post->ID );
		?>
			<p>
				<label for="syntax-data"><?php _e( 'JSON representation of all the code in the post. This protects the code from formatting imposed by the editor.', 'syntax_textdomain' )?></label>
				<textarea name="syntax-data" id="syntax-storage" style="width: 100%; height: 200px;"><?php if ( isset ( $saved_meta['_syntax_data'] ) ) echo $saved_meta['_syntax_data'][0]; ?></textarea>
			</p>
		<?php
	}

	/**
	 * Saves the custom meta input
	 *
	 * @param int $post_id The current post ID.
	 */
	function save_meta( $post_id ) {

		// Checks save status
		$is_autosave = wp_is_post_autosave( $post_id );
		$is_revision = wp_is_post_revision( $post_id );
		$is_valid_nonce = ( isset( $_POST[ 'syntax_nonce' ] ) && wp_verify_nonce( $_POST[ 'syntax_nonce' ], basename( __FILE__ ) ) ) ? 'true' : 'false';

		// Exits script depending on save status
		if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
			return;
		}

		// Checks for input and sanitizes/saves if needed
		if( isset( $_POST[ 'syntax-data' ] ) ) {
			update_post_meta( $post_id, '_syntax_data', esc_html( $_POST[ 'syntax-data' ] ) );
		}
	}
}

if ( !class_exists( 'MDLR_Syntax_Pro' ) ) {
	new MDLR_Syntax;
}
