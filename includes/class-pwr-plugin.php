<?php
defined( 'ABSPATH' ) || exit;

final class PORTUS_WR_Plugin {
 const COOKIE = 'portus_wr_visitor';
 const CRON = 'portus_wr_process_alerts';

 public static function has_pro() { return (bool) apply_filters( 'portus_wr_pro_active', false ); }

 public static function table() {
  global $wpdb;
  return PORTUS_WR_Compatibility::table( 'wishlist' );
 }

 public static function activate() {
  PORTUS_WR_Compatibility::migrate();
  global $wpdb;
  require_once ABSPATH . 'wp-admin/includes/upgrade.php';
  $table = self::table();
  $charset = $wpdb->get_charset_collate();
  dbDelta( "CREATE TABLE $table (
   id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
   owner varchar(64) NOT NULL,
   user_id bigint(20) unsigned NOT NULL DEFAULT 0,
   product_id bigint(20) unsigned NOT NULL,
   email varchar(100) NOT NULL DEFAULT '',
   price_alert tinyint(1) NOT NULL DEFAULT 0,
   stock_alert tinyint(1) NOT NULL DEFAULT 0,
   confirmed tinyint(1) NOT NULL DEFAULT 0,
   token_hash varchar(64) NOT NULL DEFAULT '',
   baseline decimal(26,8) DEFAULT NULL,
   was_in_stock tinyint(1) NOT NULL DEFAULT 0,
   created_at datetime NOT NULL,
   consent_at datetime DEFAULT NULL,
   last_sent datetime DEFAULT NULL,
   sent_count bigint(20) unsigned NOT NULL DEFAULT 0,
   collections text DEFAULT NULL,
   retry_count smallint(5) unsigned NOT NULL DEFAULT 0,
   retry_after datetime DEFAULT NULL,
   last_error varchar(180) NOT NULL DEFAULT '',
   tracking_consent tinyint(1) NOT NULL DEFAULT 0,
   stop_after_purchase tinyint(1) NOT NULL DEFAULT 1,
   PRIMARY KEY  (id),
   UNIQUE KEY owner_product (owner,product_id),
   KEY alert_scan (confirmed,id),
   KEY product_id (product_id),
   KEY email (email)
  ) $charset;" );
  PORTUS_WR_Features::install();
  PORTUS_WR_Reports::install();
  update_option( 'portus_wr_db_version', PORTUS_WR_VERSION, false );
  PORTUS_WR_Integrations::endpoint();
  flush_rewrite_rules( false );
  if ( ! get_option( 'portus_wr_page_id' ) ) {
   $page = wp_insert_post( array( 'post_title' => 'Minha lista de desejos', 'post_name' => 'lista-de-desejos', 'post_content' => '[portus_wishlist]', 'post_status' => 'publish', 'post_type' => 'page' ) );
   if ( ! is_wp_error( $page ) && $page ) { update_option( 'portus_wr_page_id', $page, false ); }
  }
 }

 public static function deactivate() { wp_clear_scheduled_hook( self::CRON ); }

 public static function boot() {
  PORTUS_WR_Compatibility::migrate();
  if ( ! class_exists( 'WooCommerce' ) ) {
   add_action( 'admin_notices', static function () { echo '<div class="notice notice-warning"><p>Portus Wishlist Radar precisa do WooCommerce ativo.</p></div>'; } );
   return;
  }
  if ( get_option( 'portus_wr_db_version' ) !== PORTUS_WR_VERSION ) { add_action( 'init', array( __CLASS__, 'activate' ), 20 ); }
  PORTUS_WR_Integrations::boot();
  PORTUS_WR_Features::boot();
  PORTUS_WR_Reports::boot();
  add_filter( 'cron_schedules', static function ( $s ) { $s['portus_wr_five_minutes'] = array( 'interval' => 300, 'display' => 'Portus: cinco minutos' ); return $s; } );
  add_action( 'init', static function () {
   if ( ! wp_next_scheduled( self::CRON ) ) { wp_schedule_event( time() + 300, 'portus_wr_five_minutes', self::CRON ); }
  } );
  add_action( self::CRON, array( __CLASS__, 'process_alerts' ) );
  add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
  add_action( 'woocommerce_after_shop_loop_item', array( __CLASS__, 'button' ), 'before' === get_option( 'portus_wr_button_position', 'after' ) ? 5 : 15 );
  add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'button' ), 'before' === get_option( 'portus_wr_button_position', 'after' ) ? 25 : 35 );
  add_shortcode( 'portus_wishlist', array( __CLASS__, 'shortcode' ) );
  foreach ( array( 'session', 'state', 'resolve', 'list', 'save', 'toggle', 'remove', 'restore', 'bulk_remove', 'cart', 'cancel_alert' ) as $action ) {
   add_action( 'wp_ajax_portus_wr_' . $action, array( __CLASS__, 'ajax' ) );
   add_action( 'wp_ajax_nopriv_portus_wr_' . $action, array( __CLASS__, 'ajax' ) );
  }
  add_action( 'template_redirect', array( __CLASS__, 'email_action' ) );
  add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ) );
  add_action( 'admin_enqueue_scripts', static function ( $hook ) {
   if ( 'woocommerce_page_portus-wishlist-radar' === $hook ) {
    wp_enqueue_style( 'portus-wishlist-radar-admin', plugins_url( 'assets/admin.css', PORTUS_WR_FILE ), array(), PORTUS_WR_VERSION );
   }
  } );
  add_action( 'admin_init', array( __CLASS__, 'settings' ) );
  add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporter' ) );
  add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'eraser' ) );
  add_action( 'admin_init', static function () {
   if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
    wp_add_privacy_policy_content( 'Portus Wishlist Radar', '<p>A lista de desejos utiliza um cookie de identificação com duração de 180 dias. Para alertas opcionais, armazenamos e-mail, produtos, preferências, data de consentimento e histórico de envios. O endereço de e-mail precisa ser confirmado. Cada mensagem contém um link para cancelar. Listas de visitantes sem alertas confirmados são apagadas após 180 dias. Dados ficam no banco da loja e os e-mails são enviados pelo serviço de e-mail configurado no WordPress. Ao entrar na conta, os favoritos do visitante são mesclados. Listas personalizadas são privadas até o compartilhamento explícito; quem tem o link vê somente o nome da lista e os produtos. A medição opcional de cliques usa um cookie assinado por sete dias para associar itens de pedidos aos avisos. Histórico operacional é mantido por até um ano.</p>' );
   }
  } );
 }

 public static function owner() {
  if ( is_user_logged_in() ) { return hash_hmac( 'sha256', 'user:' . get_current_user_id(), wp_salt( 'auth' ) ); }
  $visitor = PORTUS_WR_Compatibility::visitor();
  if ( ! preg_match( '/^[a-f0-9]{64}$/', $visitor ) ) { $visitor = bin2hex( random_bytes( 32 ) ); }
  if ( ! isset( $_COOKIE[self::COOKIE] ) || $_COOKIE[self::COOKIE] !== $visitor ) {
   setcookie( self::COOKIE, $visitor, array( 'expires' => time() + 180 * DAY_IN_SECONDS, 'path' => '/', 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax' ) );
   $_COOKIE[self::COOKIE] = $visitor;
  }
  return hash_hmac( 'sha256', $visitor, wp_salt( 'auth' ) );
 }

 public static function assets() {
  wp_enqueue_style( 'portus-wishlist-radar', plugins_url( 'assets/wishlist.css', PORTUS_WR_FILE ), array(), PORTUS_WR_VERSION );
  wp_enqueue_script( 'portus-wishlist-radar', plugins_url( 'assets/wishlist.js', PORTUS_WR_FILE ), array(), PORTUS_WR_VERSION, true );
  wp_enqueue_script( 'portus-wishlist-radar-integrations', plugins_url( 'assets/integrations.js', PORTUS_WR_FILE ), array( 'portus-wishlist-radar' ), PORTUS_WR_VERSION, true );
  wp_localize_script( 'portus-wishlist-radar', 'PortusWishlist', array( 'ajax' => admin_url( 'admin-ajax.php' ), 'page' => get_permalink( get_option( 'portus_wr_page_id' ) ) ) );
 }

 public static function svg_allowed() {
  return array( 'svg' => array_fill_keys( array( 'class','viewbox','fill','stroke','stroke-width','stroke-linecap','stroke-linejoin','aria-hidden','focusable' ), true ), 'path' => array( 'd' => true ) );
 }
 public static function icon( $name = 'heart' ) {
  $paths = array(
   'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>',
   'bell' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
   'arrow' => '<path d="M5 12h14m-6-6 6 6-6 6"/>',
   'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
  );
  return '<svg class="pwr-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ( $paths[$name] ?? $paths['heart'] ) . '</svg>';
 }

 public static function button( $product = null ) {
  $product = $product ?: ( $GLOBALS['product'] ?? null );
  if ( ! $product || ! $product->is_visible() ) { return; }
  echo '<div class="pwr-product-actions">';
  if ( is_product() && (int) get_queried_object_id() === $product->get_id() && $product->is_type( 'variable' ) ) {
   $options = '';
   $count = 0;
   foreach ( $product->get_children() as $child_id ) {
    $variation = wc_get_product( $child_id );
    if ( ! self::public_product( $variation ) || $variation->is_in_stock() ) { continue; }
    $options .= '<option value="' . esc_attr( $child_id ) . '">' . esc_html( $variation->get_name() ) . '</option>';
    if ( ++$count >= 100 ) { break; }
   }
   if ( $options ) {
    echo '<label class="pwr-unavailable-label">Quer uma opção sem estoque?<select class="pwr-unavailable"><option value="">Selecione para salvar e acompanhar</option>' . wp_kses( $options, array( 'option' => array( 'value' => true, 'selected' => true, 'disabled' => true ) ) ) . '</select></label>';
   }
  }
  echo '<button type="button" class="pwr-save" aria-pressed="false" data-product="' . esc_attr( $product->get_id() ) . '" data-variable="' . ( $product->is_type( 'variable' ) ? '1' : '0' ) . '" data-url="' . esc_url( $product->get_permalink() ) . '">' . wp_kses( self::icon(), self::svg_allowed() ) . '<span class="pwr-save-label">Salvar na lista</span></button> <a class="pwr-view-list" href="' . esc_url( get_permalink( get_option( 'portus_wr_page_id' ) ) ) . '">Ver minha lista ' . wp_kses( self::icon( 'arrow' ), self::svg_allowed() ) . '</a></div>';
 }

 public static function shortcode() {
  if ( PORTUS_WR_Compatibility::query( 'portus_wr_share' ) && ! self::has_pro() ) { return '<p>Esta lista compartilhada não está disponível no momento.</p>'; }
  return self::sanitize_html( apply_filters( 'portus_wr_frontend_shortcode', '<section class="pwr-list" aria-label="Lista de desejos"><header class="pwr-list-header"><div><span class="pwr-eyebrow">UMA SELEÇÃO SUA</span><h2>Seus favoritos</h2><p>O que você gosta, guardado para a hora certa.</p></div><span class="pwr-count" aria-live="polite">Carregando…</span></header><div class="pwr-items" aria-live="polite" aria-busy="true"><p class="pwr-loading">Preparando seus favoritos…</p></div><p class="pwr-no-js">Ative o JavaScript para usar a lista de desejos.</p></section>' ) );
 }

 /** Final output boundary for filtered shortcode and AJAX markup, including add-on forms. */
 public static function sanitize_html( $html ) {
  $allowed = array_merge( wp_kses_allowed_html( 'post' ), self::svg_allowed() );
  foreach ( array(
   'form' => array( 'class', 'method', 'action' ),
   'input' => array( 'type', 'name', 'value', 'class', 'checked', 'disabled', 'required', 'maxlength', 'placeholder', 'autocomplete', 'aria-label' ),
   'select' => array( 'class', 'name', 'disabled', 'aria-label' ),
   'option' => array( 'value', 'selected', 'disabled' ),
   'button' => array( 'type', 'class', 'name', 'value', 'title', 'disabled', 'aria-label', 'aria-pressed', 'data-product', 'data-variable', 'data-url' ),
   'details' => array( 'class', 'open' ), 'summary' => array( 'class' ),
  ) as $tag => $attributes ) { $allowed[$tag] = array_fill_keys( $attributes, true ); }
  return is_string( $html ) ? wp_kses( $html, $allowed ) : '';
 }

 public static function post_string( $name ) {
  if ( ! isset( $_POST[$name] ) || ! is_string( $_POST[$name] ) ) { return ''; } // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Scalar input validation only; callers verify their nonce before mutations.
  return sanitize_text_field( wp_unslash( $_POST[$name] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Input sanitizer only, not a request handler or authorization decision.
 }

 public static function valid_nonce( $owner ) {
  return wp_verify_nonce( self::post_string( 'nonce' ), 'portus_wr_' . $owner );
 }

 private static function fail( $message, $status = 400 ) { wp_send_json_error( array( 'message' => $message ), $status ); }

 public static function request_method() {
  return isset( $_SERVER['REQUEST_METHOD'] ) && is_string( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_key( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
 }

 public static function ajax() {
  global $wpdb;
  nocache_headers();
  if ( 'POST' !== self::request_method() ) { self::fail( 'Método não permitido.', 405 ); }
  $origin = isset( $_SERVER['HTTP_ORIGIN'] ) && is_string( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
  $fetch_site = isset( $_SERVER['HTTP_SEC_FETCH_SITE'] ) && is_string( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ? sanitize_key( wp_unslash( $_SERVER['HTTP_SEC_FETCH_SITE'] ) ) : '';
  if ( 'cross-site' === $fetch_site || ( $origin && wp_parse_url( $origin, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) ) { self::fail( 'Origem da solicitação não autorizada.', 403 ); }
  $owner = self::owner();
  $action = str_replace( 'portus_wr_', '', sanitize_key( self::post_string( 'action' ) ) );
  if ( 'session' === $action ) {
   // Public same-origin bootstrap: returns an owner-bound nonce only; never reads or changes a wishlist.
   wp_send_json_success( array( 'nonce' => wp_create_nonce( 'portus_wr_' . $owner ) ) );
  }
  if ( ! wp_verify_nonce( self::post_string( 'nonce' ), 'portus_wr_' . $owner ) ) { self::fail( 'Sua sessão expirou. Recarregue a página.', 403 ); }
  $table = self::table();
  PORTUS_WR_Features::dispatch( $action, $owner );
  if ( 'resolve' === $action ) { PORTUS_WR_Integrations::resolve(); }
  if ( 'state' === $action ) {
   $ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT product_id FROM %i WHERE owner = %s LIMIT 5000", $table, $owner ) ) );
   $parents = array();
   foreach ( $ids as $product_id ) {
    $saved = wc_get_product( $product_id );
    if ( $saved && $saved->is_type( 'variation' ) ) { $parents[] = $saved->get_parent_id(); }
   }
   wp_send_json_success( array( 'ids' => $ids, 'parents' => array_values( array_unique( $parents ) ) ) );
  }
  $id = absint( $_POST['product_id'] ?? 0 );
  $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE owner = %s AND product_id = %d", $table, $owner, $id ) );
  if ( 'toggle' === $action ) { $action = $row ? 'remove' : 'save'; }
  if ( 'remove' === $action ) {
   $undo = PORTUS_WR_Features::snapshot( $owner, $row ? array( $row ) : array() );
   $wpdb->delete( $table, array( 'owner' => $owner, 'product_id' => $id ) );
   wp_send_json_success( array( 'message' => 'Produto removido e seus alertas cancelados.', 'undo' => $undo ) );
  }
  $product = wc_get_product( $id );
  if ( ! self::public_product( $product ) ) { self::fail( 'Produto indisponível.' ); }
  if ( $product->is_type( 'variable' ) ) { self::fail( 'Escolha o tamanho, a cor ou outra opção do produto antes de salvar.' ); }
  if ( ! $product->is_type( array( 'simple', 'variation', 'external' ) ) ) { self::fail( 'Abra um produto individual para salvar.' ); }
  if ( 'save' === $action ) {
   if ( ! $row ) {
    $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE owner = %s", $table, $owner ) );
    // Uniform abuse/performance bound for both editions; payment never increases it.
    if ( $count >= 500 ) { self::fail( 'Sua lista pode ter até 500 produtos.' ); }
    $inserted = $wpdb->insert( $table, array( 'owner' => $owner, 'user_id' => get_current_user_id(), 'product_id' => $id, 'baseline' => '' === $product->get_price() ? null : $product->get_price(), 'was_in_stock' => (int) $product->is_in_stock(), 'created_at' => current_time( 'mysql', true ) ) );
    if ( false === $inserted ) { self::fail( 'Não foi possível salvar. Tente novamente.' ); }
    PORTUS_WR_Reports::event( 'saved', $id );
   }
   wp_send_json_success( array( 'message' => 'Produto salvo na sua lista!' ) );
  }
  do_action( 'portus_wr_product_request', $action, $owner, $row, $product );
  self::fail( 'Ação inválida.' );
 }

 public static function public_product( $product ) {
  if ( ! $product ) { return false; }
  $parent = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : $product;
  if ( ! $parent || 'publish' !== $parent->get_status() || post_password_required( $parent->get_id() ) || 'publish' !== $product->get_status() ) { return false; }
  // A saved product remains monitorable when WooCommerce hides out-of-stock catalog items.
  return (bool) apply_filters( 'woocommerce_product_is_visible', 'hidden' !== $parent->get_catalog_visibility(), $parent->get_id() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Existing WooCommerce API filter, not declared by this plugin.
 }

 public static function card( $row, $readonly = false ) {
  $p = wc_get_product( $row->product_id );
  echo '<article class="pwr-card" data-product="' . esc_attr( $row->product_id ) . '">';
  if ( ! $readonly ) { echo '<label class="pwr-select-item"><input type="checkbox" class="pwr-select" value="' . esc_attr( $row->product_id ) . '" aria-label="Selecionar ' . esc_attr( $p ? $p->get_name() : 'produto' ) . '"></label><button type="button" class="pwr-remove" aria-label="Remover ' . esc_attr( $p ? $p->get_name() : 'produto' ) . ' da lista" title="Remover da lista">' . wp_kses( self::icon( 'close' ), self::svg_allowed() ) . '</button>'; }
  if ( self::public_product( $p ) ) {
   echo '<a class="pwr-image" href="' . esc_url( $p->get_permalink() ) . '">' . wp_kses_post( $p->get_image( 'woocommerce_thumbnail' ) ) . '</a><div class="pwr-card-body"><span class="pwr-stock' . ( $p->is_in_stock() ? '' : ' is-unavailable' ) . '">' . ( $p->is_in_stock() ? 'Em estoque' : 'Aguardando reposição' ) . '</span><h3><a href="' . esc_url( $p->get_permalink() ) . '">' . esc_html( $p->get_name() ) . '</a></h3><div class="pwr-price">' . wp_kses_post( $p->get_price_html() ) . '</div><a class="pwr-primary" href="' . esc_url( $p->get_permalink() ) . '">Ver produto ' . wp_kses( self::icon( 'arrow' ), self::svg_allowed() ) . '</a>';
   if ( ! $readonly && $p->is_purchasable() && $p->is_in_stock() && $p->is_type( array( 'simple', 'variation' ) ) ) { echo '<button type="button" class="pwr-cart" data-product="' . esc_attr( $p->get_id() ) . '">Adicionar ao carrinho</button>'; }
   do_action( 'portus_wr_product_extra', $row, $p, $readonly );
   echo '</div>';
  } else { echo '<div class="pwr-card-body"><p>Este produto não está mais disponível.</p></div>'; }
  echo '</article>';
 }


 public static function email_action() {
  $action = sanitize_key( PORTUS_WR_Compatibility::query( 'portus_wr_action' ) );
  if ( ! $action ) { return; }
  global $wpdb;
  nocache_headers();
  header( 'Referrer-Policy: no-referrer' );
  header( 'X-Robots-Tag: noindex, nofollow' );
  $id = absint( PORTUS_WR_Compatibility::query( 'id' ) );
  $token = PORTUS_WR_Compatibility::query( 'token' );
  $table = self::table();
  $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $id ) );
  if ( ! in_array( $action, array( 'confirm', 'cancel' ), true ) || ! $row || ! preg_match( '/^[a-f0-9]{64}$/', $token ) || ! $row->token_hash || ! hash_equals( $row->token_hash, hash( 'sha256', $token ) ) ) { wp_die( 'Link inválido ou cancelado.', 'Portus Wishlist Radar', array( 'response' => 400 ) ); }
  if ( 'confirm' === $action && ( ! $row->consent_at || strtotime( $row->consent_at . ' UTC' ) < time() - 2 * DAY_IN_SECONDS ) ) { wp_die( 'A confirmação expirou. Solicite um novo e-mail na sua lista.', 'Portus Wishlist Radar', array( 'response' => 410 ) ); }
  // A GET only displays the form, avoiding subscription changes by email link scanners.
  $nonce_action = 'portus_wr_email_' . $action . '_' . $id . '_' . $row->token_hash;
  if ( 'POST' === self::request_method() ) {
   if ( ! wp_verify_nonce( self::post_string( 'portus_wr_email_nonce' ), $nonce_action ) ) { wp_die( 'Sua sessão expirou. Reabra o link do e-mail e tente novamente.', 'Portus Wishlist Radar', array( 'response' => 403 ) ); }
   if ( 'cancel' === $action ) {
    PORTUS_WR_Features::cancel( $id );
    wp_die( 'Alertas cancelados e seu e-mail removido deste produto.', 'Portus Wishlist Radar', array( 'response' => 200 ) );
   }
   $wpdb->update( $table, array( 'confirmed' => 1 ), array( 'id' => $id ) );
   wp_die( 'E-mail confirmado! Você receberá os alertas escolhidos.', 'Portus Wishlist Radar', array( 'response' => 200 ) );
  }
  $label = 'cancel' === $action ? 'Cancelar alertas deste produto' : 'Confirmar meus alertas';
  wp_die( '<p>' . esc_html( $label ) . '</p><form method="post">' . wp_kses( wp_nonce_field( $nonce_action, 'portus_wr_email_nonce', false, false ), array( 'input' => array_fill_keys( array( 'type', 'id', 'name', 'value' ), true ) ) ) . '<button type="submit">' . esc_html( $label ) . '</button></form>', 'Portus Wishlist Radar', array( 'response' => 200 ) );
 }

 public static function process_alerts() {
  global $wpdb;
  $table = self::table();
  $cutoff = gmdate( 'Y-m-d H:i:s', time() - 180 * DAY_IN_SECONDS );
  $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE confirmed = 0 AND user_id = 0 AND created_at < %s LIMIT 100", $table, $cutoff ) );
  do_action( 'portus_wr_process_pro_alerts' );
 }

 public static function signed_cancel() {
  $id = absint( PORTUS_WR_Compatibility::query( 'portus_wr_cancel_id' ) );
  if ( ! $id ) { return; }
  global $wpdb;
  $table = self::table();
  $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM %i WHERE id = %d", $table, $id ) );
  $signature = PORTUS_WR_Compatibility::query( 'signature' );
  if ( ! $row || ! $row->token_hash || ! hash_equals( hash_hmac( 'sha256', 'cancel:' . $id . ':' . $row->token_hash, wp_salt( 'auth' ) ), $signature ) ) { wp_die( 'Link inválido.', 'Portus', array( 'response' => 400 ) ); }
  nocache_headers();
  header( 'Referrer-Policy: no-referrer' );
  header( 'X-Robots-Tag: noindex, nofollow' );
  $nonce_action = 'portus_wr_signed_cancel_' . $id . '_' . $row->token_hash;
  if ( 'POST' === self::request_method() ) {
   if ( ! wp_verify_nonce( self::post_string( 'portus_wr_email_nonce' ), $nonce_action ) ) { wp_die( 'Sua sessão expirou. Reabra o link do e-mail e tente novamente.', 'Portus', array( 'response' => 403 ) ); }
   PORTUS_WR_Features::cancel( $id );
   wp_die( 'Alertas cancelados e e-mail removido deste produto.', 'Portus', array( 'response' => 200 ) );
  }
  wp_die( '<form method="post">' . wp_kses( wp_nonce_field( $nonce_action, 'portus_wr_email_nonce', false, false ), array( 'input' => array_fill_keys( array( 'type', 'id', 'name', 'value' ), true ) ) ) . '<p>Cancelar os alertas deste produto?</p><button type="submit">Cancelar alertas</button></form>', 'Portus', array( 'response' => 200 ) );
 }

 public static function admin_menu() { add_submenu_page( 'woocommerce', 'Portus Wishlist Radar', 'Portus Wishlist', 'manage_woocommerce', 'portus-wishlist-radar', array( __CLASS__, 'admin_page' ) ); }

 public static function settings() { do_action( 'portus_wr_register_extension_settings' ); }

 public static function admin_page() {
  if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
  global $wpdb;
  $portus_wr_table = self::table();
  $portus_wr_stats = $wpdb->get_row( $wpdb->prepare( "SELECT COUNT(*) AS total, COALESCE(SUM(confirmed),0) AS confirmed, COALESCE(SUM(sent_count),0) AS sent FROM %i", $portus_wr_table ) );
  $portus_wr_top = $wpdb->get_results( $wpdb->prepare( "SELECT product_id, COUNT(*) AS total FROM %i GROUP BY product_id ORDER BY total DESC LIMIT 20", $portus_wr_table ) );
  include __DIR__ . '/admin-view.php';
 }

 public static function exporter( $exporters ) { $exporters['portus-wishlist-radar'] = array( 'exporter_friendly_name' => 'Portus Wishlist Radar', 'callback' => array( __CLASS__, 'export_data' ) ); return $exporters; }
 public static function eraser( $erasers ) { $erasers['portus-wishlist-radar'] = array( 'eraser_friendly_name' => 'Portus Wishlist Radar', 'callback' => array( __CLASS__, 'erase_data' ) ); return $erasers; }
 private static function privacy_rows( $email, $page ) {
  global $wpdb;
  $table = self::table(); $owners = PORTUS_WR_Reports::owners( $email );
  $where = 'email=%s'; $args = array( $email );
  if ( $owners ) { $where .= ' OR owner IN (' . implode( ',', array_fill( 0, count( $owners ), '%s' ) ) . ')'; $args = array_merge( $args, array_values( $owners ) ); }
  $args[] = ( max( 1, $page ) - 1 ) * 100;
  // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber,PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where consists only of fixed SQL and generated placeholders; all values and the identifier are bound below.
  return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE $where ORDER BY id LIMIT 100 OFFSET %d", array_merge( array( $table ), $args ) ) );
 }
 public static function export_data( $email, $page = 1 ) {
  $rows = self::privacy_rows( $email, $page );
  $data = array();
  foreach ( $rows as $row ) { $data[] = array( 'group_id' => 'portus-wishlist-radar', 'group_label' => 'Lista de desejos Portus', 'item_id' => 'pwr-' . $row->id, 'data' => array( array( 'name' => 'Produto (ID)', 'value' => $row->product_id ), array( 'name' => 'E-mail', 'value' => $row->email ), array( 'name' => 'Criado em (UTC)', 'value' => $row->created_at ), array( 'name' => 'Consentimento (UTC)', 'value' => $row->consent_at ), array( 'name' => 'Confirmação', 'value' => $row->confirmed ? 'Sim' : 'Não' ), array( 'name' => 'Alerta de preço', 'value' => $row->price_alert ? 'Sim' : 'Não' ), array( 'name' => 'Alerta de estoque', 'value' => $row->stock_alert ? 'Sim' : 'Não' ) ) ); }
  return array( 'data' => $data, 'done' => count( $rows ) < 100 );
 }
 public static function erase_data( $email, $page = 1 ) {
  global $wpdb;
  $owners = PORTUS_WR_Reports::owners( $email );
  $history = PORTUS_WR_Reports::erase_data( $email );
  $removed = (bool) $wpdb->delete( self::table(), array( 'email' => $email ) );
  foreach ( $owners as $owner ) { $removed = (bool) $wpdb->delete( self::table(), array( 'owner' => $owner ) ) || $removed; set_transient( 'portus_wr_undo_generation_' . $owner, bin2hex( random_bytes( 16 ) ), 11 * MINUTE_IN_SECONDS ); }
  return array( 'items_removed' => $removed || $history['items_removed'], 'items_retained' => false, 'messages' => array(), 'done' => true );
 }
}
add_action( 'template_redirect', array( 'PORTUS_WR_Plugin', 'signed_cancel' ) );
