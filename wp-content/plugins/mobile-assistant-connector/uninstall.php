<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

if( ! defined( 'WP_UNINSTALL_PLUGIN' ) )
    exit();

uninstall_mobassistantconnector();

function uninstall_mobassistantconnector() {
    global $wpdb;

    $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}mobileassistant_push_settings`");
    $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}mobileassistant_devices`");
    $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}mobileassistant_session_keys`");
    $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}mobileassistant_failed_login`");
    $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}mobileassistant_users`");
    $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}mobileassistant_accounts`");
    $wpdb->delete($wpdb->options, array('option_name' => 'mobassistantconnector'));
}