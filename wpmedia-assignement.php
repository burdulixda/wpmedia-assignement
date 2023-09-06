<?php
/**
 * Plugin Name:       WPMedia Assignement
 * Plugin URI:        https://github.com/burdulixda/wpmedia-assignement
 * Description:       Let's search and rank the hyperlinks
 * Version:           1.0
 * Requires at least: 5.0
 * Requires PHP:      7.2
 * Author:            Giorgi Burduli
 * Author URI:        https://github.com/burdulixda
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       rocket
 * Domain Path:       /languages
 */
class RocketWPMediaAssignement {

	/**
	 * Class constructor that initializes actions.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'crawler_menu' ] );
		add_action( 'run_crawl_hook', [ $this, 'run_crawl' ] );
	}

	/**
	 * Registers the crawler menu in the WordPress admin.
	 *
	 * @return void
	 */
	public function crawler_menu() {
		add_menu_page( __( 'Website Crawler', 'rocket' ), __( 'Website Crawler', 'rocket' ), 'manage_options', 'website_crawler', [ $this, 'crawler_page' ] );
	}

	/**
	 * Displays the crawler admin page and handles form submissions.
	 *
	 * @return void
	 */
	public function crawler_page() {
		// Nonce field.
		$nonce_field = wp_nonce_field( 'crawl_action', 'crawl_nonce', true, false );

		echo '<h2>' . esc_html__( 'Website Crawler', 'rocket' ) . '</h2>';
		// phpcs:ignore
		echo '<form method="post">' . $nonce_field . '<input type="submit" name="crawl" value="' . esc_attr__( 'Start Crawl', 'rocket' ) . '"></form>';

		// Verify the nonce before proceeding.
		if ( isset( $_POST['crawl'] ) && check_admin_referer( 'crawl_action', 'crawl_nonce' ) ) {
			$this->run_crawl();
			if ( ! wp_next_scheduled( 'run_crawl_hook' ) ) {
				wp_schedule_event( time(), 'hourly', 'run_crawl_hook' );
			}
		}

		// Retrieve links from options table.
		$links = get_option( 'crawler_links', [] );

		// Display the links.
		foreach ( $links as $link ) {
			echo esc_url( $link ) . '<br>';
		}
	}


	/**
	 * Executes the website crawl and saves the links.
	 *
	 * @return void
	 */
	public function run_crawl() {
		global $wp_filesystem;

		// Initialize the WP filesystem.
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		delete_option( 'crawler_links' );

		// Remove existing sitemap.html if it exists.
		if ( $wp_filesystem->exists( 'sitemap.html' ) ) {
			$wp_filesystem->delete( 'sitemap.html' );
		}

		$homepage   = get_home_url();
		$hyperlinks = $this->crawl_page( $homepage );

		// Save the links in the options table.
		update_option( 'crawler_links', $hyperlinks );

		$sitemap_content = '<ul>';
		foreach ( $hyperlinks as $link ) {
			$sitemap_content .= '<li>' . $link . '</li>';
		}
		$sitemap_content .= '</ul>';

		// Write to sitemap.html.
		$sitemap_path = ABSPATH . 'sitemap.html';
		$wp_filesystem->put_contents( $sitemap_path, $sitemap_content );

		$response = wp_remote_get( $homepage );

		if ( is_wp_error( $response ) ) {
			return;
		}

		$homepage_content = wp_remote_retrieve_body( $response );
		$homepage_path    = ABSPATH . 'homepage.html';

		// Write to homepage.html.
		$wp_filesystem->put_contents( $homepage_path, $homepage_content );
	}


	/**
	 * Crawl the given URL and return internal links.
	 *
	 * @param string $url The URL to crawl.
	 * @return array $internal_links An array of internal links.
	 */
	public function crawl_page( $url ) {
		$internal_links = [];

		// Fetch page content.
		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$page_content = wp_remote_retrieve_body( $response );

		$dom = new DOMDocument();

		// Suppress warnings only during loadHTML but capture them for logging.
		libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML( $page_content );
		libxml_clear_errors();

		// To avoid displaying errors in production.
		if ( ! $loaded ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore
				error_log( 'DOMDocument could not parse the HTML content from: ' . $url );
			}
			return [];
		}

		$tags = $dom->getElementsByTagName( 'a' );
		foreach ( $tags as $tag ) {
			$link = $tag->getAttribute( 'href' );

			if ( strpos( $link, get_home_url() ) !== false || strpos( $link, '/' ) === 0 ) {
				$internal_links[] = $link;
			}
		}

		return $internal_links;
	}
}

$rocket_wp_media_assignement = new RocketWPMediaAssignement();
