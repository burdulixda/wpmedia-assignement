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
		add_action( 'admin_notices', [ $this, 'display_admin_notices' ] );
		add_action( 'init', [ $this, 'schedule_cron_if_not_exists' ] );
		add_shortcode( 'display_sitemap', [ $this, 'display_sitemap_shortcode' ] );
	}

	/**
	 * Custom logic for logging errors in non-production environments.
	 *
	 * @param  mixed $message Error message.
	 * @return void
	 */
	public function custom_log( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// Ignoring PHPCS because we're only displaying errors if explicitly required by WP_DEBUG constant.
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $message );
		}
	}


	/**
	 * Display_admin_notices.
	 *
	 * @return void
	 */
	public function display_admin_notices() {
		$error = get_option( 'crawler_error' );
		if ( ! empty( $error ) ) {
			echo '<div class="notice notice-error is-dismissible">';
			echo '<p>' . esc_html( $error ) . '</p>';
			echo '</div>';

			// Clear the error once displayed.
			delete_option( 'crawler_error' );
		}
	}

	/**
	 * Schedule the cron event if it doesn't already exist.
	 *
	 * @return void
	 */
	public function schedule_cron_if_not_exists() {
		if ( ! wp_next_scheduled( 'run_crawl_hook' ) ) {
			wp_schedule_event( time(), 'hourly', 'run_crawl_hook' );
		}
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
		// Ignoring PHPCS because nonces are escaped by default.
		// phpcs:ignore
		echo '<form method="post">' . $nonce_field . '<input type="submit" name="crawl" value="' . esc_attr__( 'Start Crawl', 'rocket' ) . '"></form>';

		// Verify the nonce before proceeding.
		if ( isset( $_POST['crawl'] ) && check_admin_referer( 'crawl_action', 'crawl_nonce' ) ) {
			$this->run_crawl();

			// Unschedule existing cron jobs.
			$timestamp = wp_next_scheduled( 'run_crawl_hook' );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'run_crawl_hook' );
			}

			// Schedule a new cron job.
			if ( ! wp_next_scheduled( 'run_crawl_hook' ) ) {
				wp_schedule_event( time(), 'hourly', 'run_crawl_hook' );
			}
		}

		// Retrieve links from options table.
		$links = get_option( 'crawler_links', [] );

		// Display the links.
		foreach ( $links as $link ) {
			echo '<a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a><br>';
		}
	}

	/**
	 * Get proper web root location for Bedrock and traditional WordPress environments.
	 *
	 * @return string A path to web root.
	 */
	private function get_web_root() {
		// Determine if it's a Bedrock environment.
		if ( defined( 'WP_ENV' ) ) {
			// Bedrock setup.
			return dirname( ABSPATH );
		} else {
			// Traditional WordPress setup.
			return ABSPATH;
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

		if ( ! WP_Filesystem() ) {
			update_option( 'crawler_error', 'Failed to initialize WP Filesystem' );
			return;
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
			$sitemap_content .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a></li>';
		}
		$sitemap_content .= '</ul>';

		// Get proper web root.
		$web_root = $this->get_web_root();

		// Write to sitemap.html.
		$sitemap_path = trailingslashit( $web_root ) . 'sitemap.html';
		$wp_filesystem->put_contents( $sitemap_path, $sitemap_content );

		$response = wp_remote_get( $homepage );

		if ( is_wp_error( $response ) ) {
			$this->custom_log( $response->get_error_message() );

			update_option( 'crawler_error', 'Failed to fetch the homepage' );
			return;
		}

		$homepage_content = wp_remote_retrieve_body( $response );
		$homepage_path    = trailingslashit( $web_root ) . 'homepage.html';

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

		if ( ! $loaded ) {
			foreach ( $errors as $error ) {
				$this->custom_log( 'DOMDocument parsing error: ' . $error->message );
			}

			// Save the error message to display later.
			update_option( 'crawler_error', 'Failed to parse the homepage content' );
			return;
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

	/**
	 * Display the link to the sitemap.html file via [dispaly_shortcode] shortcode on the front-end.
	 *
	 * @param array $atts $An associative array of attributes.
	 * @return string A link to the sitemap.html file if it exists, otherwise a not available message.
	 */
	public function display_sitemap_shortcode( $atts ) {
		global $wp_filesystem;

		// Initialize the WP filesystem.
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		// Get proper web root.
		$web_root = $this->get_web_root();

		// Choose the correct path for sitemap.html.
		$sitemap_path = trailingslashit( $web_root ) . 'sitemap.html';

		if ( $wp_filesystem->exists( $sitemap_path ) ) {
			$sitemap_url = home_url( 'sitemap.html' );
			return '<a href="' . esc_url( $sitemap_url ) . '">View Sitemap</a>';
		} else {
			return 'Sitemap not available.';
		}
	}

}

$rocket_wp_media_assignement = new RocketWPMediaAssignement();
