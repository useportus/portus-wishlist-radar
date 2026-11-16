# Portus Wishlist Radar

Portus Wishlist Radar is a responsive wishlist plugin for WooCommerce.

Let customers save products and individual variations, keep their favorites across visits, and access their wishlist from My Account. Guest favorites are merged into the customer's account when signing in.

[WordPress.org Plugin](https://wordpress.org/plugins/portus-wishlist-radar/) · [Product page](https://useportus.com/portus-wishlist-radar-free/) · [Documentation](https://useportus.com/docs/documentacao-portus-wishlist-radar-free/) · [Support](mailto:plugins@useportus.com)

## Features

- Guest and signed-in customer wishlists.
- Automatic merging of guest favorites at login.
- Support for individual products and product variations.
- Heart buttons with persistent saved state and removal undo.
- Sorting, selection, bulk removal, and supported add-to-cart actions.
- Favorites section in WooCommerce My Account.
- Responsive grid: four columns on desktop, two on tablets, and one on mobile.
- Configurable button color, shape, label, and position.
- Integration with identifiable classic, block, Elementor, and Essential Addons product cards.
- Integration with the favorite button in Portus Cart for WooCommerce.
- Shortcodes for custom pages and product templates.
- Basic product-interest ranking and WordPress personal-data export/erasure integration.

## Requirements

- WordPress 6.5 or newer.
- PHP 7.4 or newer.
- WooCommerce installed and active.
- Standard WordPress script loaders and identifiable product markup for automatic button placement.

## Installation

The recommended installation method is through WordPress.org:

1. Open **Plugins > Add New** in WordPress.
2. Search for **Portus Wishlist Radar**.
3. Install and activate the plugin.
4. Open **WooCommerce > Portus Wishlist**.
5. Configure the button and open the automatically created wishlist page.

Use `[portus_wishlist]` to display a wishlist on a custom page, or `[portus_wishlist_button id="123"]` to place a product button in a custom template.

## Free and Pro

This public repository tracks the Free WordPress.org edition of Portus Wishlist Radar.

All features included here are available without a license key, trial period, or payment. The commercial Pro add-on is distributed separately through Portus and is not included in this repository. It provides confirmed price/restock email alerts, custom lists, sharing, email customization, and advanced reports.

## Privacy and Compatibility

Wishlist records are stored in the store's database. A visitor identifier cookie supports guest lists; the Free edition does not contact an external analytics service. See `readme.txt` for retention, technical limits, and personal-data controls.

Automatic buttons need identifiable products. Test custom themes and templates; use the product-button shortcode where automatic insertion is unavailable. Version 1.1.4 preserves earlier favorites, options, visitor identities, and existing email links through its compatibility layer.

## WordPress.org Assets

The `wordpress-org-assets/` directory contains the public icon, banners, and screenshots used by the WordPress.org plugin page.

## Development Notes

The runtime files mirror the published Free 1.1.4 package. Releases to WordPress.org are handled separately through its official SVN repository. GitHub updates do not automatically publish a new directory release.

## Maintainer

Portus Wishlist Radar is created and maintained by [Daniel Portela](https://github.com/daniel-portela) under [Portus](https://useportus.com).

## License

GPLv2 or later. See [LICENSE.txt](LICENSE.txt) for details.
