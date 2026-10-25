<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}
global $wpdb;
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wce_expiry_date' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_wce_expiry_action' ) );
wp_clear_scheduled_hook( 'wce_sweep' );
