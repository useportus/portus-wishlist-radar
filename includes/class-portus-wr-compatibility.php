<?php
defined( 'ABSPATH' ) || exit;

/** Read-only compatibility identifiers from releases <= 1.1.3; new names use PORTUS_WR/portus_wr. */
final class PORTUS_WR_Compatibility {
 public static function migrate() {
  if ( get_option( 'portus_wr_prefix_migrated' ) ) { return; }
  global $wpdb;
  // Retain existing physical tables and row IDs: previously sent email links refer to these IDs.
  $legacy_table = $wpdb->prefix . 'pwr_wishlist';
  $legacy_storage = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $legacy_table ) ) ) === $legacy_table;
  add_option( 'portus_wr_legacy_storage', $legacy_storage ? 1 : 0, '', false );
  foreach ( array( 'page_id', 'db_version', 'alerts_enabled', 'cursor', 'color', 'button_shape', 'button_format', 'button_position', 'email_subject', 'email_intro', 'email_footer', 'email_from_name', 'email_from_address', 'email_html', 'pro_license' ) as $suffix ) {
   $missing = new stdClass();
   if ( get_option( 'portus_wr_' . $suffix, $missing ) !== $missing ) { continue; }
   $value = get_option( 'pwr_' . $suffix, $missing );
   if ( $value !== $missing ) { add_option( 'portus_wr_' . $suffix, $value, '', false ); }
  }
  wp_clear_scheduled_hook( 'pwr_process_alerts' );
  add_option( 'portus_wr_prefix_migrated', 1, '', false );
 }

 public static function table( $suffix ) {
  global $wpdb;
  $namespace = get_option( 'portus_wr_legacy_storage', 0 ) ? 'pwr_' : 'portus_wr_';
  return $wpdb->prefix . $namespace . $suffix;
 }

 public static function visitor() {
  foreach ( array( 'portus_wr_visitor', 'pwr_visitor' ) as $name ) {
   $value = isset( $_COOKIE[$name] ) && is_string( $_COOKIE[$name] ) ? sanitize_text_field( wp_unslash( $_COOKIE[$name] ) ) : '';
   if ( preg_match( '/^[a-f0-9]{64}$/', $value ) ) { return $value; }
  }
  return '';
 }

 /** Only retrieves scalar query values; this method never authorizes or changes data. */
 public static function query( $name ) {
  if ( ! in_array( $name, array( 'portus_wr_action', 'portus_wr_cancel_id', 'portus_wr_share', 'portus_wr_visit', 'portus_wr_days', 'id', 'token', 'signature', 'key', 'sig' ), true ) ) { return ''; }
  $legacy = strpos( $name, 'portus_wr_' ) === 0 ? 'pwr_' . substr( $name, strlen( 'portus_wr_' ) ) : $name;
  $name = isset( $_GET[$name] ) ? $name : $legacy; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only query-key selection.
  if ( ! isset( $_GET[$name] ) || ! is_string( $_GET[$name] ) ) { return ''; } // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only validates scalar read-only query input.
  return sanitize_text_field( wp_unslash( $_GET[$name] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Token and POST nonce are checked before mutations by each caller.
 }
}
