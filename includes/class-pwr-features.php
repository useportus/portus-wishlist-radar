<?php
defined( 'ABSPATH' ) || exit;

final class PORTUS_WR_Features {
 public static function table() { return PORTUS_WR_Compatibility::table( 'collections' ); }
 public static function install() {
  global $wpdb;
  $table = self::table(); $charset = $wpdb->get_charset_collate();
  dbDelta( "CREATE TABLE $table (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   owner varchar(64) NOT NULL,
   user_id bigint(20) unsigned NOT NULL DEFAULT 0,
   name varchar(80) NOT NULL,
   share_seed varchar(64) NOT NULL DEFAULT '',
   created_at datetime NOT NULL,
   PRIMARY KEY  (id),
   KEY owner (owner)
  ) $charset;" );
 }
 public static function boot() {
  add_action( 'wp_login', static function ( $login, $user ) { self::merge( $user->ID ); }, 10, 2 );
  add_action( 'set_logged_in_cookie', static function ( $cookie, $expire, $expiration, $user_id ) { self::merge( $user_id ); }, 10, 4 );
  add_filter( 'portus_wr_frontend_shortcode', array( __CLASS__, 'toolbar' ) );
  add_action( 'wp_enqueue_scripts', array( __CLASS__, 'appearance' ), 20 );
  add_action( 'admin_init', array( __CLASS__, 'settings' ) );
  foreach ( array( 'portus_wr_appearance_settings' ) as $group ) { add_filter( 'option_page_capability_' . $group, static function () { return 'manage_woocommerce'; } ); }


 }
 public static function appearance() {
  $value = get_option( 'portus_wr_color', '#090d3c' );
  $accent = is_string( $value ) ? sanitize_hex_color( $value ) : null;
  $accent = $accent ?: '#090d3c';
  wp_add_inline_style( 'portus-wishlist-radar', '.pwr-list,.pwr-product-actions{--pwr-ink:' . $accent . '}' );
  $shape = get_option( 'portus_wr_button_shape', 'rounded' );
  if ( 'pill' === $shape ) { wp_add_inline_style( 'portus-wishlist-radar', '.pwr-product-actions button.pwr-save{border-radius:40px}' ); }
  if ( 'square' === $shape ) { wp_add_inline_style( 'portus-wishlist-radar', '.pwr-product-actions button.pwr-save{border-radius:2px}' ); }
  if ( 'icon' === get_option( 'portus_wr_button_format', 'label' ) ) {
   wp_add_inline_style( 'portus-wishlist-radar', '.pwr-product-actions .pwr-save-label{position:absolute;width:1px;height:1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap}.pwr-product-actions button.pwr-save{width:44px;padding:10px}' );
  }
  $position = get_option( 'portus_wr_button_position', 'after' );
  $position = in_array( $position, array( 'before', 'after' ), true ) ? $position : 'after';
  wp_add_inline_script( 'portus-wishlist-radar-integrations', 'window.PortusWishlistPosition=' . wp_json_encode( $position, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
 }
 public static function settings() {
  foreach ( array( 'portus_wr_color', 'portus_wr_button_shape', 'portus_wr_button_format', 'portus_wr_button_position' ) as $name ) {
   register_setting( 'portus_wr_appearance_settings', $name, array( 'sanitize_callback' => static function ( $value ) use ( $name ) {
    if ( 'portus_wr_color' === $name ) { return is_string( $value ) ? ( sanitize_hex_color( $value ) ?: '#090d3c' ) : '#090d3c'; }
    $choices = array( 'portus_wr_button_shape' => array( 'rounded', 'pill', 'square' ), 'portus_wr_button_format' => array( 'label', 'icon' ), 'portus_wr_button_position' => array( 'after', 'before' ) );
    if ( isset( $choices[$name] ) ) { return is_string( $value ) && in_array( $value, $choices[$name], true ) ? $value : $choices[$name][0]; }
    return sanitize_text_field( $value );
   } ) );
  }
 }
 public static function merge( $user_id ) {
  global $wpdb;
  $cookie = PORTUS_WR_Compatibility::visitor();
  if ( ! is_string( $cookie ) || ! preg_match( '/^[a-f0-9]{64}$/', $cookie ) || ! $user_id ) { return; }
  $guest = hash_hmac( 'sha256', $cookie, wp_salt( 'auth' ) );
  $owner = hash_hmac( 'sha256', 'user:' . $user_id, wp_salt( 'auth' ) );
  $table = PORTUS_WR_Plugin::table();
  $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE owner=%s LIMIT 500", $table, $guest ) );
  foreach ( $rows as $row ) {
   $existing = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE owner=%s AND product_id=%d", $table, $owner, $row->product_id ) );
   if ( $existing ) {
    if ( (int) $existing->id === (int) $row->id ) { continue; }
    $collections = array_unique( array_merge( self::memberships( $existing ), self::memberships( $row ) ) );
    // Keep the guest ID when it owns the only email subscription, preserving emailed cancellation links.
    if ( ! $existing->email && $row->email ) {
     $wpdb->delete( $table, array( 'id' => $existing->id ) );
     $wpdb->update( $table, array( 'owner' => $owner, 'user_id' => $user_id, 'collections' => wp_json_encode( array_values( $collections ) ) ), array( 'id' => $row->id ) );
    } else {
     $wpdb->update( $table, array( 'collections' => wp_json_encode( array_values( $collections ) ) ), array( 'id' => $existing->id ) );
     $wpdb->delete( $table, array( 'id' => $row->id ) );
    }
   } else { $wpdb->update( $table, array( 'owner' => $owner, 'user_id' => $user_id ), array( 'id' => $row->id ) ); }
  }
  $wpdb->update( self::table(), array( 'owner' => $owner, 'user_id' => $user_id ), array( 'owner' => $guest ) );
  PORTUS_WR_Reports::merge( $guest, $owner, $user_id );
 }
 public static function memberships( $row ) { $ids = json_decode( $row->collections ?? '[]', true ); return is_array( $ids ) ? array_values( array_unique( array_map( 'absint', $ids ) ) ) : array(); }
 public static function snapshot( $owner, $rows ) {
  if ( ! $rows ) { return ''; }
  $token = bin2hex( random_bytes( 24 ) );
  $clean = array();
  foreach ( $rows as $row ) { $copy = clone $row; $copy->email = ''; $copy->token_hash = ''; $copy->consent_at = null; $copy->confirmed = 0; $copy->tracking_consent = 0; $copy->last_error = ''; $clean[] = $copy; }
  $generation = get_transient( 'portus_wr_undo_generation_' . $owner ) ?: 'initial';
  if ( 'initial' !== $generation ) { set_transient( 'portus_wr_undo_generation_' . $owner, $generation, 11 * MINUTE_IN_SECONDS ); }
  set_transient( 'portus_wr_undo_' . hash( 'sha256', $token ), array( 'owner' => $owner, 'generation' => $generation, 'rows' => $clean ), 10 * MINUTE_IN_SECONDS );
  return $token;
 }
 public static function toolbar( $html ) {
  if ( ! apply_filters( 'portus_wr_show_toolbar', true ) ) { return $html; }
  $toolbar = '<div class="pwr-toolbar"><label>Ordenar<select class="pwr-sort"><option value="newest">Mais recentes</option><option value="oldest">Mais antigos</option><option value="name">Nome</option><option value="price_asc">Menor preço</option><option value="price_desc">Maior preço</option></select></label><label class="pwr-select-all-label"><input type="checkbox" class="pwr-select-all"> Selecionar todos</label><button type="button" class="pwr-bulk-remove">Remover selecionados</button></div>';
  $toolbar = apply_filters( 'portus_wr_toolbar_content', $toolbar );
  return str_replace( '<div class="pwr-items"', $toolbar . '<div class="pwr-items"', $html );
 }
 private static function error( $message, $status = 400 ) { wp_send_json_error( array( 'message' => $message ), $status ); }
 public static function rows( $owner ) { global $wpdb; $table = PORTUS_WR_Plugin::table(); return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE owner=%s ORDER BY id DESC LIMIT 5000", $table, $owner ) ); }
 public static function render_list( $owner ) {
  global $wpdb;
  if ( ! wp_verify_nonce( PORTUS_WR_Plugin::post_string( 'nonce' ), 'portus_wr_' . $owner ) ) { self::error( 'Sua sessão expirou. Recarregue a página.', 403 ); }
  if ( ! empty( $_POST['share_id'] ) && ! PORTUS_WR_Plugin::has_pro() ) { self::error( 'Esta lista compartilhada não está disponível.', 404 ); }
  $context = apply_filters( 'portus_wr_list_context', array( 'rows' => self::rows( $owner ), 'readonly' => false, 'name' => '', 'collection_id' => 0 ), $owner );
  $rows = $context['rows']; $readonly = $context['readonly']; $name = $context['name']; $collection_id = $context['collection_id'];
  $sort = sanitize_key( PORTUS_WR_Plugin::post_string( 'sort' ) ) ?: 'newest';
  if ( in_array( $sort, array( 'name', 'price_asc', 'price_desc', 'oldest' ), true ) ) {
   usort( $rows, static function ( $a, $b ) use ( $sort ) {
    if ( 'oldest' === $sort ) { return $a->id <=> $b->id; }
    $pa = wc_get_product( $a->product_id ); $pb = wc_get_product( $b->product_id );
    if ( 'name' === $sort ) { return strnatcasecmp( $pa ? $pa->get_name() : '', $pb ? $pb->get_name() : '' ); }
    $difference = (float) ( $pa ? $pa->get_price() : 0 ) <=> (float) ( $pb ? $pb->get_price() : 0 );
    return 'price_desc' === $sort ? -$difference : $difference;
   } );
  }
  ob_start();
  if ( ! $rows ) { echo '<div class="pwr-empty"><span class="pwr-empty-icon">' . wp_kses( PORTUS_WR_Plugin::icon(), PORTUS_WR_Plugin::svg_allowed() ) . '</span><h2>' . ( $collection_id ? 'Esta lista ainda não tem produtos' : 'Sua próxima descoberta começa aqui' ) . '</h2><p>' . ( $collection_id && ! $readonly ? 'Selecione produtos em Todos os favoritos e adicione a esta lista.' : 'Salve os produtos que você gosta e encontre tudo aqui.' ) . '</p><a class="pwr-primary" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Explorar a loja ' . wp_kses( PORTUS_WR_Plugin::icon( 'arrow' ), PORTUS_WR_Plugin::svg_allowed() ) . '</a></div>'; }
  foreach ( $rows as $row ) { PORTUS_WR_Plugin::card( $row, $readonly ); }
  wp_send_json_success( array( 'html' => PORTUS_WR_Plugin::sanitize_html( ob_get_clean() ), 'count' => count( $rows ), 'readonly' => $readonly, 'name' => $name, 'contextual' => (bool) $collection_id, 'description' => $context['description'] ?? '' ) );
 }
 public static function dispatch( $action, $owner ) {
  global $wpdb;
  if ( ! wp_verify_nonce( PORTUS_WR_Plugin::post_string( 'nonce' ), 'portus_wr_' . $owner ) ) { self::error( 'Sua sessão expirou. Recarregue a página.', 403 ); }
  $table = PORTUS_WR_Plugin::table();
  do_action( 'portus_wr_feature_request', $action, $owner );
  if ( 'list' === $action ) { self::render_list( $owner ); }
  if ( 'restore' === $action ) {
   $token = sanitize_text_field( wp_unslash( $_POST['undo'] ?? '' ) );
   $key = 'portus_wr_undo_' . hash( 'sha256', $token ); $undo = get_transient( $key );
   if ( ! $undo || ! hash_equals( $undo['owner'], $owner ) || ( $undo['generation'] ?? 'initial' ) !== ( get_transient( 'portus_wr_undo_generation_' . $owner ) ?: 'initial' ) ) { self::error( 'Não foi possível desfazer. O prazo pode ter terminado ou os dados foram excluídos.' ); }
   foreach ( $undo['rows'] as $row ) {
    if ( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM %i WHERE owner=%s AND product_id=%d", $table, $owner, $row->product_id ) ) ) { continue; }
    $data = (array) $row; unset( $data['id'] );
    // Restoring a favorite must never restore a cancelled subscription or an emailed ID that no longer exists.
    foreach ( array( 'email', 'token_hash', 'last_error' ) as $field ) { $data[$field] = ''; }
    foreach ( array( 'confirmed', 'price_alert', 'stock_alert', 'retry_count', 'sent_count', 'tracking_consent' ) as $field ) { if ( isset( $data[$field] ) ) { $data[$field] = 0; } }
    $data['consent_at'] = null; $data['retry_after'] = null; $wpdb->insert( $table, $data );
   }
   delete_transient( $key ); wp_send_json_success( array( 'message' => 'Favoritos restaurados. Alertas cancelados não foram reativados.' ) );
  }
  if ( 'bulk_remove' === $action ) {
   $ids = array_slice( array_unique( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['ids'] ?? '' ) ) ) ) ) ), 0, 500 );
   $rows = array_values( array_filter( self::rows( $owner ), static function ( $row ) use ( $ids ) { return in_array( (int) $row->product_id, $ids, true ); } ) );
   $undo = self::snapshot( $owner, $rows );
   foreach ( $rows as $row ) { $wpdb->delete( $table, array( 'id' => $row->id, 'owner' => $owner ) ); }
   wp_send_json_success( array( 'message' => count( $rows ) . ' favoritos removidos.', 'undo' => $undo ) );
  }
  if ( in_array( $action, array( 'cart', 'cancel_alert' ), true ) ) {
   $id = absint( $_POST['product_id'] ?? 0 );
   $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE owner=%s AND product_id=%d", $table, $owner, $id ) );
   if ( ! $row ) { self::error( 'Este produto não está na sua lista.', 404 ); }
   if ( 'cancel_alert' === $action ) {
    self::cancel( $row->id ); wp_send_json_success( array( 'message' => 'Alertas cancelados. Seu favorito foi mantido.' ) );
   }
   $p = wc_get_product( $id );
   if ( ! PORTUS_WR_Plugin::public_product( $p ) || ! $p->is_type( array( 'simple', 'variation' ) ) || ! $p->is_purchasable() || ! $p->is_in_stock() ) { self::error( 'Abra o produto para conferir disponibilidade e opções.' ); }
   if ( $p->is_type( 'variation' ) && in_array( '', $p->get_variation_attributes(), true ) ) { self::error( 'Abra o produto e escolha todas as opções antes de comprar.' ); }
   if ( null === WC()->cart ) { wc_load_cart(); }
   $pid = $p->is_type( 'variation' ) ? $p->get_parent_id() : $id;
   $variation = $p->is_type( 'variation' ) ? $id : 0;
   $attributes = $variation ? $p->get_variation_attributes() : array();
   if ( ! apply_filters( 'woocommerce_add_to_cart_validation', true, $pid, 1, $variation, $attributes ) ) { self::error( 'Confira as opções na página do produto.' ); } // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Existing WooCommerce validation API, not a plugin-owned hook.
   try { $key = WC()->cart->add_to_cart( $pid, 1, $variation, $attributes ); } catch ( Exception $e ) { self::error( wp_strip_all_tags( $e->getMessage() ) ); }
   if ( ! $key ) { self::error( 'Não foi possível adicionar ao carrinho. Confira o produto.' ); }
   WC()->cart->calculate_totals(); WC()->cart->set_session();
   wp_send_json_success( array( 'message' => 'Produto adicionado ao carrinho.', 'cart_url' => wc_get_cart_url(), 'cart_count' => WC()->cart->get_cart_contents_count() ) );
  }
 }
 public static function cancel( $id ) {
  global $wpdb;
  $wpdb->update( PORTUS_WR_Plugin::table(), array( 'email' => '', 'confirmed' => 0, 'price_alert' => 0, 'stock_alert' => 0, 'token_hash' => '', 'consent_at' => null, 'tracking_consent' => 0, 'retry_count' => 0, 'retry_after' => null, 'last_error' => '' ), array( 'id' => $id ) );
 }
}
