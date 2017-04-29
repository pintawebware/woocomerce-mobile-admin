<?php
/*
Plugin Name: Woocommerce_Pinta
Plugin URI: https://github.com/Hrishko/pintawpplugin
Description: This is my first plugin for WOOCOMMERCE MOBILE ADMIN on Wordpress
Version: 1.0
Author: Grishko Victoria
Author https://github.com/Hrishko/pintawpplugin
*/


/**
 * Check if WooCommerce is active
 */

if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

//    if (!class_exists('PintaClass')) {
    register_activation_hook(__FILE__, 'woocommerce_pinta_activation');
    register_deactivation_hook(__FILE__, 'woocommerce_pinta_deactivation');
    // регистрируем действие при удалении
    register_uninstall_hook(__FILE__, 'woocommerce_pinta_uninstall');

    define('WOOCOMMERCE_PINTA_DIR', plugin_dir_path(__FILE__));


    include_once(WOOCOMMERCE_PINTA_DIR . 'includes/PintaClass.php');

    if ($_GET['route']) {
        $pinta = new PintaClass();
    }

//    } // if (!class///
} else {
    add_action('admin_notices', 'connector_admin_notices');
}


if (!function_exists('connector_admin_notices')) {
    function connector_admin_notices()
    {
        echo '<div id="notice" class="error"><p>';
        echo '<b> WOOCOMMERCE PINTA </b> add-on requires <a href="http://www.storeapps.org/woocommerce/"> WooCommerce </a> plugin. Please install and activate it.';
        echo '</p></div>', "\n";

    }
}
