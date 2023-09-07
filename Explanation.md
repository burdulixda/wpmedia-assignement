## Problem
We need to create a custom WordPress plugin that will crawl the homepage of a website, retrieve all internal links, and save these links into a sitemap. This sitemap should be accessible via the WordPress admin interface and also via a shortcode on the website's frontend for end-users.

## Technical Specifications
1. **Admin Interface**

   An admin page will be created where administrators can initiate the crawling process and view the generated sitemap.
2. **Cron Scheduling**

    A cron job will be scheduled to automatically run the crawl every hour.
3. **Shortcode**

    A shortcode [display_sitemap] will be created to display a link to the sitemap in the front-end.
4. **Error Handling**

    Errors during the crawling process will be logged, and an admin notice will be shown in the WordPress dashboard.

## Explanation
This project was intriguing as I'm still not 100% sure if it was easy or hard even after finishing it. Upon initial inspection of the technical assignment, the requirements seemed verbose and complex to implement. When I started writing the code, I ran into various issues that took me quite a lot of time to fix. The PHP and Composer versions on my Linux machine were not compatible with what the project's composer.json requires. I tried everything, including phpenv and cgr to install older versions of both PHP and Composer, and I finally managed to do it in a way that I can seamlessly switch between versions with no hassle.

When it comes to the technical task itself, at first I started writing everything in native PHP functions. I used curl and PHP FIlesystem functions to read and write data. PHPCS soon let me know that it was unacceptable and that what we needed was to use as many native WordPress functions as possible. I felt embarrassed that I didn't start with that approach in mind, but nevertheless, I rewrote most of the functionality in a WordPress-friendly way.

## How the Code Works
1. **Initialization**: The constructor `(__construct)` initializes WordPress hooks and the shortcode.
2. **Admin Interface**: `crawler_menu` and `crawler_page` methods are responsible for creating the admin interface.
3. **Crawling**: `run_crawl` and `crawl_page` are the core methods for crawling. They fetch the homepage content, extract internal links, and then save these into a sitemap.html file.
4. **Shortcode**: `display_sitemap_shortcode` is used to display a link to the sitemap.html on the front-end.
5. **Cron Scheduling**: `schedule_cron_if_not_exists` ensures that a cron job is scheduled to run the crawl automatically.
6. **Error Handling**: `custom_log` and `display_admin_notices` handle error logging and display respectively.

## Technical Decisions
- **WordPress Native Functions**: Initially started with native PHP functions like curl for HTTP requests, but switched to WordPress native functions for better compatibility and to follow best practices.
- **WP Cron**: Used WordPress cron instead of system cron for simplicity and better integration with WordPress.
- **DOMDocument**: Used PHP's DOMDocument library for parsing HTML as it's robust and allows for easy traversal of DOM elements.
- **Error Logging**: Used WordPress' update_option to store errors, which are then displayed as admin notices. Also used conditional logging based on WP_DEBUG.
