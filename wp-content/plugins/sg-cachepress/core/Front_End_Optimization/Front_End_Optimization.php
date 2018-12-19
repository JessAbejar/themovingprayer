<?php
namespace SiteGround_Optimizer\Front_End_Optimization;

use SiteGround_Optimizer\Options\Options;
use SiteGround_Optimizer\Emojis_Removal\Emojis_Removal;
use SiteGround_Optimizer\Lazy_Load_Images\Lazy_Load_Images;
use SiteGround_Optimizer\Images_Optimizer\Images_Optimizer;
use SiteGround_Optimizer\Minifier\Minifier;
/**
 * SG Front_End_Optimization main plugin class
 */
class Front_End_Optimization {
	/**
	 * Create a {@link Supercacher} instance.
	 *
	 * @since 5.0.0
	 */
	public function __construct() {
		$this->run();
	}

	/**
	 * Run the frontend optimization.
	 *
	 * @since  5.0.0
	 */
	private function run() {
		// Remove query strings only if the option is emabled.
		if ( Options::is_enabled( 'siteground_optimizer_remove_query_strings' ) ) {
			// Filters for static style and script loaders.
			add_filter( 'style_loader_src', array( $this, 'remove_query_strings' ) );
			add_filter( 'script_loader_src', array( $this, 'remove_query_strings' ) );
		}

		// Disable emojis if the option is enabled.
		if ( Options::is_enabled( 'siteground_optimizer_disable_emojis' ) ) {
			new Emojis_Removal();
		}

		// Enabled lazy load images.
		if ( Options::is_enabled( 'siteground_optimizer_lazyload_images' ) ) {
			new Lazy_Load_Images();
		}

		// Enabled lazy load images.
		if ( Options::is_enabled( 'siteground_optimizer_optimize_images' ) ) {
			new Images_Optimizer();
		}

		new Minifier();
	}

	/**
	 * Remove query strings from static resources.
	 *
	 * @since  5.0.0
	 *
	 * @param  string $src The source URL of the enqueued style.
	 *
	 * @return string $src The modified src if there are query strings, the initial src otherwise.
	 */
	public static function remove_query_strings( $src ) {
		return remove_query_arg(
			array(
				'ver',
				'version',
				'v',
			),
			$src
		);
	}


}
