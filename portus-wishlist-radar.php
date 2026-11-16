<?php
/**
 * Plugin Name: Portus Wishlist Radar
 * Plugin URI: https://useportus.com/portus-wishlist-radar-free/
 * Description: Lista de desejos responsiva para WooCommerce. O complemento Pro adiciona alertas de preço e reposição.
 * Version: 1.1.4
 * Author: Portus
 * Author URI: https://useportus.com/
 * Text Domain: portus-wishlist-radar
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;
define( 'PORTUS_WR_VERSION', '1.1.4' );
define( 'PORTUS_WR_FILE', __FILE__ );
require_once __DIR__ . '/includes/class-portus-wr-compatibility.php';
require_once __DIR__ . '/includes/class-pwr-plugin.php';
require_once __DIR__ . '/includes/class-pwr-integrations.php';
require_once __DIR__ . '/includes/class-pwr-features.php';
require_once __DIR__ . '/includes/class-pwr-reports.php';
register_activation_hook( __FILE__, array( 'PORTUS_WR_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PORTUS_WR_Plugin', 'deactivate' ) );
add_action( 'plugins_loaded', array( 'PORTUS_WR_Plugin', 'boot' ) );
add_action( 'before_woocommerce_init', static function () {
 if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
  \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
 }
} );
