<?php
/*
Plugin Name: Woocommerce mobile admin API
Plugin URI:
Description: This plugin creates a custom API for working with the WooCommerce application.
It allows you to keep your online business under control wherever you are. All you need is just to have on hand your android or ios mobile phone and Internet connection.
Version: 2.0.0
Author: Pinta WebWare
Author URI: https://github.com/pintawebware
*/

/**
 * Check if WooCommerce is active
 */

if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ||
    in_array('woocommerce-3.3.0/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ) {

    

    register_activation_hook(__FILE__, 'woocommerce_pinta_activation');
    register_deactivation_hook(__FILE__, 'woocommerce_pinta_deactivation');
    // регистрируем действие при удалении
    register_uninstall_hook(__FILE__, 'woocommerce_pinta_uninstall');

    define('WOOCOMMERCE_PINTA_DIR', plugin_dir_path(__FILE__));

    if ( file_exists(dirname(WOOCOMMERCE_PINTA_DIR) . '/woocommerce/woocommerce.php' ) ) {
        include_once ( dirname(WOOCOMMERCE_PINTA_DIR) . '/woocommerce/woocommerce.php');
    } elseif ( file_exists(dirname(WOOCOMMERCE_PINTA_DIR) . '/woocommerce-3.3.0/woocommerce.php' ) ) {
        include_once ( dirname(WOOCOMMERCE_PINTA_DIR) . '/woocommerce-3.3.0/woocommerce.php');
    }

    include_once(WOOCOMMERCE_PINTA_DIR . 'includes/WMA_PintaClass.php');

    if ($_GET['route']) {
        $pinta = new WMA_PintaClass();
    }

} else {
    add_action('admin_notices', 'connector_admin_notices');
}

if (!function_exists('connector_admin_notices')) {
    function connector_admin_notices()
    {
        echo '<div id="notice" class="error"><p>';
        echo '<b> WOOCOMMERCE PINTA </b> add-on requires WooCommerce plugin. Please install and activate it.';
        echo '</p></div>', "\n";

    }
}
