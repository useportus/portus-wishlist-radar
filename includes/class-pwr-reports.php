<?php
defined( 'ABSPATH' ) || exit;

final class PORTUS_WR_Reports {
 const COOKIE = 'portus_wr_attribution';
 public static function table() { return PORTUS_WR_Compatibility::table( 'events' ); }
 public static function install() {
  global $wpdb; $table = self::table(); $charset = $wpdb->get_charset_collate();
  dbDelta( "CREATE TABLE $table (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   event_type varchar(20) NOT NULL,
   product_id bigint(20) unsigned NOT NULL DEFAULT 0,
   wishlist_id bigint(20) unsigned NOT NULL DEFAULT 0,
   owner varchar(64) NOT NULL DEFAULT '',
   user_id bigint(20) unsigned NOT NULL DEFAULT 0,
   order_id bigint(20) unsigned NOT NULL DEFAULT 0,
   conversion_order bigint(20) unsigned DEFAULT NULL,
   amount decimal(26,8) NOT NULL DEFAULT 0,
   currency varchar(8) NOT NULL DEFAULT '',
   token_seed varchar(64) NOT NULL DEFAULT '',
   clicked_at datetime DEFAULT NULL,
   created_at datetime NOT NULL,
   PRIMARY KEY  (id),
   KEY period_type (created_at,event_type),
   KEY owner (owner),
   KEY order_id (order_id),
   KEY wishlist_id (wishlist_id),
   UNIQUE KEY conversion_order (conversion_order)
  ) $charset;" );
 }
 public static function boot() {
  add_filter( 'wp_privacy_personal_data_exporters', static function ( $items ) { $items['portus-wishlist-radar-history'] = array( 'exporter_friendly_name' => 'Histórico Portus Wishlist', 'callback' => array( __CLASS__, 'export_data' ) ); return $items; } );
  add_filter( 'wp_privacy_personal_data_erasers', static function ( $items ) { $items['portus-wishlist-radar-history'] = array( 'eraser_friendly_name' => 'Histórico e listas Portus', 'callback' => array( __CLASS__, 'erase_data' ) ); return $items; } );
  add_action( PORTUS_WR_Plugin::CRON, array( __CLASS__, 'cleanup' ), 20 );
 }
 public static function merge( $guest, $owner, $user_id ) { global $wpdb; $wpdb->update( self::table(), array( 'owner' => $owner, 'user_id' => $user_id ), array( 'owner' => $guest ) ); }
 public static function event( $type, $product_id, $wishlist_id = 0 ) {
  global $wpdb; $table = self::table();
  if ( in_array( $type, array( 'sent', 'failed' ), true ) && $wishlist_id ) {
   $pending = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE wishlist_id=%d AND event_type='pending' ORDER BY id DESC LIMIT 1", $table, $wishlist_id ) );
   if ( $pending ) { $wpdb->update( $table, array( 'event_type' => $type ), array( 'id' => $pending ) ); return (int) $pending; }
  }
  $wpdb->insert( $table, array( 'event_type' => $type, 'product_id' => $product_id, 'wishlist_id' => $wishlist_id, 'owner' => PORTUS_WR_Plugin::owner(), 'user_id' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) );
  return (int) $wpdb->insert_id;
 }
 public static function cleanup() {
  global $wpdb;
  $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT 500', self::table(), gmdate( 'Y-m-d H:i:s', time() - YEAR_IN_SECONDS ) ) );
  $collections = PORTUS_WR_Features::table(); $wish = PORTUS_WR_Plugin::table();
  // Remove expired empty guest collections, including sharing links, after 180 days.
  $old = $wpdb->get_results( $wpdb->prepare( "SELECT id,owner FROM %i WHERE user_id=0 AND created_at < %s LIMIT 100", $collections, gmdate( 'Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS ) ) );
  foreach ( $old as $row ) { if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE owner=%s LIMIT 1", $wish, $row->owner ) ) ) { $wpdb->delete( $collections, array( 'id' => $row->id ) ); } }
 }
 public static function owners( $email ) {
  global $wpdb; $user = get_user_by( 'email', $email );
  $owners = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT owner FROM %i WHERE email=%s AND confirmed=1', PORTUS_WR_Plugin::table(), $email ) );
  if ( $user ) { $owners[] = hash_hmac( 'sha256', 'user:' . $user->ID, wp_salt( 'auth' ) ); }
  return array_unique( $owners );
 }
 public static function export_data( $email, $page = 1 ) {
  global $wpdb; $data = array(); $done = true;
  foreach ( self::owners( $email ) as $owner ) {
   $rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE owner=%s ORDER BY id LIMIT 100 OFFSET %d', self::table(), $owner, ( max( 1, $page ) - 1 ) * 100 ) );
   if ( count( $rows ) === 100 ) { $done = false; }
   foreach ( $rows as $row ) { $data[] = array( 'group_id' => 'portus-wishlist-radar-history', 'group_label' => 'Histórico de favoritos e avisos', 'item_id' => 'portus-wishlist-radar-history-' . $row->id, 'data' => array( array( 'name' => 'Evento', 'value' => $row->event_type ), array( 'name' => 'Produto', 'value' => $row->product_id ), array( 'name' => 'Data UTC', 'value' => $row->created_at ), array( 'name' => 'Pedido associado', 'value' => $row->order_id ) ) ); }
   if ( 1 === (int) $page ) {
    $lists = $wpdb->get_results( $wpdb->prepare( 'SELECT id,name,share_seed FROM %i WHERE owner=%s', PORTUS_WR_Features::table(), $owner ) );
    foreach ( $lists as $list ) { $data[] = array( 'group_id' => 'portus-wishlist-radar-collections', 'group_label' => 'Listas personalizadas', 'item_id' => 'portus-wishlist-radar-collection-' . $list->id, 'data' => array( array( 'name' => 'Nome', 'value' => $list->name ), array( 'name' => 'Compartilhada', 'value' => $list->share_seed ? 'Sim' : 'Não' ) ) ); }
   }
  }
  return array( 'data' => $data, 'done' => $done );
 }
 public static function erase_data( $email, $page = 1 ) {
  global $wpdb; $removed = false;
  foreach ( self::owners( $email ) as $owner ) {
   $removed = (bool) $wpdb->delete( self::table(), array( 'owner' => $owner ) ) || $removed;
   $removed = (bool) $wpdb->delete( PORTUS_WR_Features::table(), array( 'owner' => $owner ) ) || $removed;
  }
  return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
 }
}
