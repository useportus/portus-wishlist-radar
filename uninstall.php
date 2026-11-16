<?php
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;
wp_clear_scheduled_hook( 'portus_wr_process_alerts' );
wp_clear_scheduled_hook( 'pwr_process_alerts' ); // Remove a pre-1.1.4 schedule if present.
// Keep customer data unless the site owner explicitly opts into deletion.
if ( !( defined( 'PORTUS_WR_DELETE_DATA' ) && PORTUS_WR_DELETE_DATA ) && !( defined( 'PWR_DELETE_DATA' ) && PWR_DELETE_DATA ) ) { return; }
global $wpdb;
$portus_wr_storage = get_option( 'portus_wr_legacy_storage', 0 ) ? 'pwr_' : 'portus_wr_';
$portus_wr_table = $wpdb->prefix . $portus_wr_storage . 'wishlist';
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $portus_wr_table ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $portus_wr_storage . 'collections' ) );
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $portus_wr_storage . 'events' ) );
foreach ( array( 'portus_wr_page_id', 'portus_wr_db_version', 'portus_wr_alerts_enabled', 'portus_wr_cursor', 'portus_wr_worker_lock' ) as $portus_wr_option ) { delete_option( $portus_wr_option ); }
foreach ( array( 'portus_wr_color', 'portus_wr_button_shape', 'portus_wr_button_format', 'portus_wr_button_position', 'portus_wr_email_subject', 'portus_wr_email_intro', 'portus_wr_email_footer', 'portus_wr_email_from_name', 'portus_wr_email_from_address', 'portus_wr_email_html' ) as $portus_wr_option ) { delete_option( $portus_wr_option ); }
foreach ( array( 'page_id', 'db_version', 'alerts_enabled', 'cursor', 'worker_lock', 'color', 'button_shape', 'button_format', 'button_position', 'email_subject', 'email_intro', 'email_footer', 'email_from_name', 'email_from_address', 'email_html' ) as $portus_wr_suffix ) { delete_option( 'pwr_' . $portus_wr_suffix ); }
delete_option( 'portus_wr_legacy_storage' );
delete_option( 'portus_wr_prefix_migrated' );
