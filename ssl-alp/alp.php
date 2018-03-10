<?php

/*
Plugin Name:  Academic Labbook Plugin
Plugin URI:   https://alp.attackllama.com/
Description:  Turn WordPress into a collaborative academic notebook.
Version:      0.1.0
Author:       Sean Leavey
Author URI:   https://attackllama.com/
License:      GPL3
License URI:  https://www.gnu.org/licenses/gpl-3.0.en.html
*/

if ( ! defined( 'WPINC' ) ) {
    exit;
}

/**
 * Current plugin version.
 */

define( 'SSL_ALP_VERSION', '0.1.0' );

/**
 * Base plugin directory
 */
define( 'SSL_ALP_BASE_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSL_ALP_BASE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Default settings
 */

define( 'SSL_ALP_MATHJAX_VERSION', '2.7.2' );
define( 'SSL_ALP_DEFAULT_MATHJAX_URL', 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.2/MathJax.js?config=TeX-AMS_SVG' );
define( 'SSL_ALP_DOI_BASE_URL', 'https://doi.org/' );
define( 'SSL_ALP_ARXIV_BASE_URL', 'https://arxiv.org/abs/' );

/**
 * Code to run on plugin activation and deactivation.
 */

// import classes
require_once SSL_ALP_BASE_DIR . 'includes/class-activator.php';
require_once SSL_ALP_BASE_DIR . 'includes/class-deactivator.php';

// register hooks
register_activation_hook( __FILE__, array('SSL_ALP_Activator', 'activate'));
register_activation_hook( __FILE__, array('SSL_ALP_Deactivator', 'deactivate'));

/**
 * Add theme directory provided by this plugin
 */

register_theme_directory( SSL_ALP_BASE_DIR . 'themes' );

/**
 * Core plugin class used to define internationalisation, hooks, etc.
 */

require SSL_ALP_BASE_DIR . 'includes/class-alp.php';

/**
 * Execute plugin.
 */

function ssl_alp_run() {
	$plugin = new SSL_ALP();
	$plugin->run();
}

ssl_alp_run();
