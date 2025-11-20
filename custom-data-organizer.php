<?php
/**
 * Plugin Name:       Custom Data Organizer
 * Description:       Group selected custom post types under a single "Custom Data" admin menu.
 * Version:           1.2.0
 * Author:            SpecNet
 * Text Domain:       custom-data-organizer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CDO_PLUGIN_FILE', __FILE__ );
define( 'CDO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CDO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once CDO_PLUGIN_DIR . 'includes/class-cdo-admin-menu.php';

add_action( 'plugins_loaded', function() {
    new CDO_Admin_Menu();
} );