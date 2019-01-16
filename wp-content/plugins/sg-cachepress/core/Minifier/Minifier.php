<?php
namespace SiteGround_Optimizer\Minifier;

use SiteGround_Optimizer\Helper\Helper;
use SiteGround_Optimizer\Options\Options;
use SiteGround_Optimizer\Front_End_Optimization\Front_End_Optimization;
use SiteGround_Optimizer\Supercacher\Supercacher;
/**
 * SG Minifier main plugin class
 */
class Minifier {
	/**
	 * WordPress filesystem.
	 *
	 * @since 5.0.0
	 *
	 * @var object|null WordPress filesystem.
	 */
	private $wp_filesystem = null;

	/**
	 * The dir where the minified styles and scripts will be saved.
	 *
	 * @since 5.0.0
	 *
	 * @var string|null Path to assets dir.
	 */
	private $assets_dir = null;

	/**
	 * Javascript files that should be ignored.
	 *
	 * @since 5.0.0
	 *
	 * @var array Array of all js files that should be ignored.
	 */
	private $js_ignore_list = array(
		'/wp-includes/js/jquery/jquery.js',
	);

	/**
	 * Script handles that should be loaded async.
	 *
	 * @since 5.0.0
	 *
	 * @var array Array of script handles that should be loaded async.
	 */
	private $async_scripts = array(
		'siteground-optimizer-lazy-load-images-js',
		'siteground-optimizer-lazy-load-images-responsive-js',
	);

	/**
	 * Stylesheet files that should be ignored.
	 *
	 * @since 5.0.0
	 *
	 * @var array Array of all css files that should be ignored.
	 */
	private $css_ignore_list = array();

	/**
	 * The constructor.
	 *
	 * @since 5.0.0
	 */
	public function __construct() {
		// Bail if it's admin page.
		if ( is_admin() ) {
			return;
		}
		// Setup wp filesystem.
		if ( null === $this->wp_filesystem ) {
			$this->wp_filesystem = Helper::setup_wp_filesystem();
		}

		// Set the assets dir path.
		$this->set_assets_directory_path();

		if ( Options::is_enabled( 'siteground_optimizer_optimize_html' ) ) {
			// Add the hooks that we will use t ominify the html.
			add_action( 'init', array( $this, 'start_html_minifier_buffer' ) );
			add_action( 'shutdown', array( $this, 'end_html_minifier_buffer' ) );
		}

		if ( Options::is_enabled( 'siteground_optimizer_optimize_javascript' ) ) {
			// Minify the js files.
			add_action( 'wp_print_scripts', array( $this, 'minify_scripts' ), PHP_INT_MAX );
			add_action( 'wp_print_footer_scripts', array( $this, 'minify_scripts' ), 9.999999 );
		}

		if ( Options::is_enabled( 'siteground_optimizer_optimize_css' ) ) {
			// Minify the css files.
			add_action( 'wp_print_styles', array( $this, 'minify_styles' ), PHP_INT_MAX );
			add_action( 'wp_print_footer_scripts', array( $this, 'minify_styles' ), 9.999999 );
		}

		// Add async attr to all scripts.
		add_filter( 'script_loader_tag', array( $this, 'add_async_attribute' ), 10, 2 );
	}

	/**
	 * Load all scripts async.
	 * This function adds async attr to all scripts.
	 *
	 * @since 5.0.0
	 *
	 * @param string $tag    The <script> tag for the enqueued script.
	 * @param string $handle The script's registered handle.
	 */
	public function add_async_attribute( $tag, $handle ) {
		// Bail if this is not our scrupt.
		if ( ! in_array( $handle, $this->async_scripts ) ) {
			return $tag;
		}

		return str_replace( ' src', ' async="async" src', $tag );
	}
	/**
	 * Set the assets directory.
	 *
	 * @since  5.0.0
	 */
	private function set_assets_directory_path() {
		// Bail if the assets dir has been set.
		if ( null !== $this->assets_dir ) {
			return;
		}

		// Get the uploads dir.
		$upload_dir = wp_upload_dir();

		// Build the assets dir name.
		$directory = $upload_dir['basedir'] . '/siteground-optimizer-assets';

		// Check if directory exists and try to create it if not.
		$is_directory_created = ! is_dir( $directory ) ? $this->create_directory( $directory ) : true;

		// Set the assets dir.
		if ( $is_directory_created ) {
			$this->assets_dir = $directory;
		}
	}

	/**
	 * Create directory.
	 *
	 * @since  5.0.0
	 *
	 * @param  string $directory The new directory path.
	 *
	 * @return bool              True is the directory is created.
	 *                           False on failure.
	 */
	private function create_directory( $directory ) {
		// Create the directory and return the result.
		$is_directory_created = wp_mkdir_p( $directory );

		// Bail if cannot create temp dir.
		if ( false === $is_directory_created ) {
			// translators: `$directory` is the name of directory that should be created.
			error_log( sprintf( 'Cannot create directory: %s.', $directory ) );
		}

		return $is_directory_created;
	}

	/**
	 * Minify scripts included in footer and header.
	 *
	 * @since  5.0.0
	 */
	public function minify_scripts() {
		global $wp_scripts;

		// Bail if the scripts object is empty.
		if ( ! is_object( $wp_scripts ) || null === $this->assets_dir ) {
			return;
		}

		$scripts = wp_clone( $wp_scripts );
		$scripts->all_deps( $scripts->queue );

		// Get groups of handles.
		foreach ( $scripts->to_do as $handle ) {
			// Skip scripts.
			if (
				stripos( $wp_scripts->registered[ $handle ]->src, '.min.js' ) !== false || // If the file is minified already.
				false === $wp_scripts->registered[ $handle ]->src || // If the source is empty.
				in_array( $wp_scripts->registered[ $handle ]->src, $this->js_ignore_list ) || // If the file is ignored.
				strpos( Helper::get_home_url(), parse_url( $wp_scripts->registered[ $handle ]->src, PHP_URL_HOST ) ) === false // Skip all external sources.
			) {
				continue;
			}

			$original_filepath = $this->get_original_filepath( $wp_scripts->registered[ $handle ]->src );

			// Build the minified version filename.
			$filename = $this->assets_dir . '/' . $handle . '.min.js';

			// Check for original file modifications and create the minified copy.
			$is_minified_file_ok = $this->check_and_create_file( $filename, $original_filepath );

			// Check that everythign with minified file is ok.
			if ( $is_minified_file_ok ) {
				// Replace the script src with the minified version.
				$wp_scripts->registered[ $handle ]->src = str_replace( ABSPATH, Helper::get_home_url(), $filename );
			}
		}
	}

	/**
	 * Get the original filepath by file handle.
	 *
	 * @since  5.0.0
	 *
	 * @param  string $original File handle.
	 *
	 * @return string           Original filepath.
	 */
	public function get_original_filepath( $original ) {
		$home_url = Helper::get_home_url();
		// Get the home_url from database. Some plugins like qtranslate for example,
		// modify the home_url, which result to wrong replacement with ABSPATH for resources loaded via link.
		// Very ugly way to handle resources without protocol.
		$result = parse_url( $home_url );

		$replace = $result['scheme'] . '://';

		$new = preg_replace( '~^https?:\/\/|^\/\/~', $replace, $original );

		// Get the filepath to original file.
		if ( strpos( $new, $home_url ) !== false ) {
			$original_filepath = str_replace( $home_url, ABSPATH, $new );
		} else {
			$original_filepath = untrailingslashit( ABSPATH ) . $new;
		}

		return $original_filepath;
	}

	/**
	 * Check if the original file is modified and create minified version.
	 * It will create minified version if the new file doesn't exists.
	 *
	 * @since  5.0.0
	 *
	 * @param  string $new_file_path     The new filename.
	 * @param  string $original_filepath The original file.
	 *
	 * @return bool             True if the file is created, false on failure.
	 */
	private function check_and_create_file( $new_file_path, $original_filepath ) {
		// First remove the query strings.
		$original_filepath = Front_End_Optimization::remove_query_strings( preg_replace( '/\?.*/', '', $original_filepath ) );
		$new_file_path     = Front_End_Optimization::remove_query_strings( preg_replace( '/\?.*/', '', $new_file_path ) );

		// Gets file modification time.
		$original_file_timestamp = @filemtime( $original_filepath );
		$minified_file_timestamp = @filemtime( $new_file_path );

		// Compare the original and new file timestamps.
		// This check will fail if the minified file doens't exists
		// and it will be created in the code below.
		if ( $original_file_timestamp === $minified_file_timestamp ) {
			return true;
		}

		// The minified file doens't exists or the original file has been modified.
		// Minify the file then.
		exec(
			sprintf(
				'minify %s --output=%s',
				$original_filepath,
				$new_file_path
			),
			$output,
			$status
		);

		// Return false if the minification fails.
		if ( 1 === intval( $status ) || ! file_exists( $new_file_path ) ) {
			return false;
		}

		// Set the minified file last modification file equla to original file.
		$this->wp_filesystem->touch( $new_file_path, $original_file_timestamp );

		// Flush the cache for our new resource.
		$new_file_url = str_replace( untrailingslashit( ABSPATH ), get_option( 'home' ), dirname( $new_file_path ) );
		Supercacher::get_instance()->purge_cache_request( $new_file_url );

		return true;

	}

	/**
	 * Minify styles included in header and footer
	 *
	 * @since  5.0.0
	 */
	public function minify_styles() {
		global $wp_styles;

		// Bail if the scripts object is empty.
		if ( ! is_object( $wp_styles ) || null === $this->assets_dir ) {
			return;
		}

		$scripts = wp_clone( $wp_styles );
		$scripts->all_deps( $scripts->queue );

		// Get groups of handles.
		foreach ( $scripts->to_do as $handle ) {
			// Skip scripts.
			if (
				stripos( $wp_styles->registered[ $handle ]->src, '.min.css' ) !== false || // If the file is minified already.
				false === $wp_styles->registered[ $handle ]->src || // If the source is empty.
				strpos( Helper::get_home_url(), parse_url( $wp_styles->registered[ $handle ]->src, PHP_URL_HOST ) ) === false // Skip all external sources.
			) {
				continue;
			}

			$original_filepath = $this->get_original_filepath( $wp_styles->registered[ $handle ]->src );

			// Build the minified version filename.
			$filename = dirname( $original_filepath ) . '/' . $handle . '.min.css';

			// Check for original file modifications and create the minified copy.
			$is_minified_file_ok = $this->check_and_create_file( $filename, $original_filepath );

			// Check that everythign with minified file is ok.
			if ( $is_minified_file_ok ) {
				// Replace the script src with the minified version.
				$wp_styles->registered[ $handle ]->src = str_replace( ABSPATH, Helper::get_home_url(), $filename );
			}
		}
	}

	/**
	 * Minify the html output.
	 *
	 * @since  5.0.0
	 *
	 * @param  string $buffer The page content.
	 *
	 * @return string         Minified content.
	 */
	public function minify_html( $buffer ) {
		$content = Minify_Html::minify( $buffer );
		return $content;
	}


	/**
	 * Start buffer.
	 *
	 * @since  5.0.0
	 */
	public function start_html_minifier_buffer() {
		ob_start( array( $this, 'minify_html' ) );
	}

	/**
	 * End the buffer.
	 *
	 * @since  5.0.0
	 */
	public function end_html_minifier_buffer() {
		if ( ob_get_length() ) {
			ob_end_flush();
		}
	}
}
