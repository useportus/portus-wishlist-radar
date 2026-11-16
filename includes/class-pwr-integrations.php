<?php
defined( 'ABSPATH' ) || exit;

final class PORTUS_WR_Integrations {
 const ENDPOINT = 'portus-favoritos';

 public static function endpoint() { add_rewrite_endpoint( self::ENDPOINT, EP_ROOT | EP_PAGES ); }

 public static function boot() {
  add_action( 'init', array( __CLASS__, 'endpoint' ), 5 );
  add_filter( 'woocommerce_get_query_vars', static function ( $vars ) { $vars[self::ENDPOINT] = self::ENDPOINT; return $vars; } );
  add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ) );
  add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', static function () { echo PORTUS_WR_Plugin::sanitize_html( PORTUS_WR_Plugin::shortcode() ); } ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitize_html applies the fixed KSES allowlist at this final output boundary.
  add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', static function () { return 'Favoritos'; } );
  add_action( 'wp_footer', array( __CLASS__, 'button_template' ), 10 );
  add_shortcode( 'portus_wishlist_button', array( __CLASS__, 'shortcode_button' ) );
  add_filter( 'body_class', static function ( $classes ) {
   $post = get_post();
   if ( ( $post && has_shortcode( $post->post_content, 'portus_wishlist' ) ) || is_wc_endpoint_url( self::ENDPOINT ) ) { $classes[] = 'pwr-wishlist-page'; }
   return $classes;
  } );
 }

 public static function account_menu( $items ) {
  $result = array();
  foreach ( $items as $key => $label ) {
   if ( 'customer-logout' === $key ) { $result[self::ENDPOINT] = 'Favoritos'; }
   $result[$key] = $label;
  }
  if ( ! isset( $result[self::ENDPOINT] ) ) { $result[self::ENDPOINT] = 'Favoritos'; }
  return $result;
 }

 public static function button_template() {
  echo '<template id="pwr-button-template"><div class="pwr-product-actions pwr-auto-actions"><button type="button" class="pwr-save" aria-pressed="false">' . wp_kses( PORTUS_WR_Plugin::icon(), PORTUS_WR_Plugin::svg_allowed() ) . '<span class="pwr-save-label">Salvar na lista</span></button></div></template>';
 }

 public static function shortcode_button( $attributes ) {
  $attributes = shortcode_atts( array( 'id' => 0 ), $attributes, 'portus_wishlist_button' );
  $previous = $GLOBALS['product'] ?? null;
  $p = $attributes['id'] ? wc_get_product( absint( $attributes['id'] ) ) : $previous;
  if ( ! $p ) { $p = wc_get_product( get_the_ID() ); }
  if ( ! PORTUS_WR_Plugin::public_product( $p ) ) { return ''; }
  ob_start();
  PORTUS_WR_Plugin::button( $p );
  return PORTUS_WR_Plugin::sanitize_html( ob_get_clean() );
 }

 public static function resolve() {
  if ( ! wp_verify_nonce( PORTUS_WR_Plugin::post_string( 'nonce' ), 'portus_wr_' . PORTUS_WR_Plugin::owner() ) ) { wp_send_json_error( array( 'message' => 'Sua sessão expirou. Recarregue a página.' ), 403 ); }
  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Decode the structured JSON first; IDs, cart keys and URLs are individually validated below without corrupting percent-encoded URLs.
  $items = isset( $_POST['items'] ) && is_string( $_POST['items'] ) ? json_decode( wp_unslash( $_POST['items'] ), true ) : null;
  if ( ! is_array( $items ) || count( $items ) > 100 ) { wp_send_json_error( array( 'message' => 'Lista de produtos inválida.' ), 400 ); }
  $result = array();
  foreach ( $items as $key => $item ) {
   if ( ! is_array( $item ) ) { continue; }
   $id = absint( $item['id'] ?? 0 );
   // Resolve the exact variation from the current visitor's cart, never from another session or a parent-only ID.
   if ( isset( $item['cart_key'] ) ) {
    if ( ! is_string( $item['cart_key'] ) || ! preg_match( '/^[a-f0-9]{32}$/', $item['cart_key'] ) ) { continue; }
    if ( null === WC()->cart ) { wc_load_cart(); }
    $cart_item = WC()->cart->get_cart_item( $item['cart_key'] );
    if ( ! $cart_item ) { continue; }
    $id = ! empty( $cart_item['variation_id'] ) ? absint( $cart_item['variation_id'] ) : absint( $cart_item['product_id'] );
   }
   if ( ! $id && ! empty( $item['url'] ) && is_string( $item['url'] ) ) {
    $url = esc_url_raw( $item['url'] );
    if ( wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ) {
     $id = url_to_postid( $url );
     if ( ! $id ) {
      parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
      if ( ! empty( $query['product'] ) && is_string( $query['product'] ) ) {
       $post = get_page_by_path( sanitize_title( $query['product'] ), OBJECT, 'product' );
       if ( $post ) { $id = $post->ID; }
      }
     }
    }
   }
   $p = wc_get_product( $id );
   if ( ! PORTUS_WR_Plugin::public_product( $p ) || ! $p->is_type( array( 'simple', 'variation', 'external', 'variable' ) ) ) { continue; }
   $result[] = array( 'key' => (string) $key, 'id' => $p->get_id(), 'variable' => $p->is_type( 'variable' ), 'url' => $p->get_permalink() );
  }
  wp_send_json_success( array( 'products' => $result ) );
 }
}
