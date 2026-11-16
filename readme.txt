=== Portus Wishlist Radar ===
Contributors: portusoficial
Tags: wishlist, woocommerce, favorites, product-wishlist
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Requires Plugins: woocommerce
Stable tag: 1.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A responsive WooCommerce wishlist with guest-to-account merging, product variations, sorting, bulk removal and My Account integration.

== Description ==

Portus Wishlist Radar lets customers save products and individual variations for later. Visitors can use a wishlist without creating an account, and their saved products are merged when they log in.

The free plugin includes:

* Save/unsave heart buttons with persistent state and removal undo.
* Individual product variations and supported add-to-cart actions.
* Sorting, selection and bulk removal.
* A Favorites section in WooCommerce My Account.
* A responsive four-column desktop grid, two columns on tablets and one on phones.
* Configurable button color, shape, content and position.
* Integration with identifiable classic, block and page-builder product cards, including Elementor and Essential Addons layouts.
* Integration with the favorite button in Portus Cart for WooCommerce.
* A basic product-interest ranking and WordPress personal-data export/erasure integration.

Use [portus_wishlist] to display the list and [portus_wishlist_button id="123"] to position a button in a custom template. Automatic insertion needs an identifiable product and the theme's standard WordPress script loaders.

The optional Pro add-on, distributed separately at https://useportus.com/portus-wishlist-radar-pro/, implements confirmed price/restock email alerts, custom lists, revocable sharing, email customization and consent-based reports. These features are implemented in the add-on; the free plugin works independently.

= Technical limits =

The normal safety bound is 500 saved products per owner. The same bound applies with or without the Pro add-on; payment does not increase it. Old unconfirmed guest data is removed after 180 days for retention purposes, not as a trial or a payment restriction.

= Privacy =

When wishlist controls load, a visitor identifier cookie may be created and can last up to 180 days. Account lists use the signed-in user. Wishlist records stay in the store database. Saved-product events support store interest statistics and personal-data export and are retained for up to one year. No external analytics service is contacted. The plugin integrates with WordPress personal-data tools. Data is preserved by default on deactivation and uninstallation; permanent removal requires the explicit PORTUS_WR_DELETE_DATA setting.

== Installation ==

1. Install and activate WooCommerce.
2. Upload and activate Portus Wishlist Radar.
3. Open WooCommerce > Portus Wishlist to configure the button and view the list page.
4. Use the automatically created wishlist page or My Account > Favorites.
5. Clear page/CDN caches after updating and exclude private pages and admin-ajax.php responses from shared caching.

== Frequently Asked Questions ==

= Is an account required? =

No. Visitors can save products. Saved visitor products are merged into the account when signing in.

= Where are the email alert controls? =

Email sending and subscription controls are provided by the separate Pro add-on. The free plugin does not send alerts.

= Does this work with every custom template? =

Automatic buttons need identifiable products. Use [portus_wishlist_button id="123"] or data-pwr-product-id="123" for custom layouts. Test your theme and extensions before rollout.

= Are products and settings preserved during updates? =

Yes. Keep the original plugin folder name when replacing the ZIP. The Pro add-on also uses the existing data. Install matching Free and Pro versions.

= Can I cancel a subscription after deactivating Pro? =

Existing unsubscribe links and personal-data controls remain available in the base plugin. Deactivating Pro pauses its email processor.

== Screenshots ==

1. Free wishlist with four desktop product columns, sorting and bulk removal.
2. A saved heart button and wishlist link on the product page.
3. Favorites inside WooCommerce My Account.
4. Responsive mobile layout with one product per row.
5. Product-interest ranking and free settings in the dashboard.
6. Empty wishlist with a link to explore the shop.

== Changelog ==

= 1.1.5 =
* Match the dashboard logo to the directory icon with subtle rounded corners and transparent edges.

= 1.1.4 =
* Added WordPress nonce validation to email confirmation and unsubscribe forms, in addition to the existing record-bound token checks.
* Sanitize filtered shortcode and AJAX HTML at the final output boundary while preserving wishlist controls.
* Use distinctive PORTUS_WR/portus_wr identifiers for declarations, hooks, settings and new storage. Preserve existing favorites, settings, visitor identities, license settings and previously emailed links through an explicit compatibility layer.

= 1.1.3 =
* Added a branded admin header and responsive cards showing the installed version, edition, license information and active theme.
* Kept the overview and Pro license navigation above the existing dashboard banner.

= 1.1.2 =
* Validated inline appearance values at output and hardened inline JSON encoding.
* Added nonce checks to auxiliary wishlist and product-resolution handlers.
* Normalized request-method and action input handling.
* Kept the directory package focused on the fully functional free implementation.

= 1.1.1 =
* Moved the Pro license interface into the Wishlist dashboard with an accessible navigation link.
* Preserved the Free dashboard, existing license state and customer data.

= 1.1.0 =
* Moved premium server and browser implementations into the separate Pro add-on.
* Preserved existing favorites, lists, consent records and the approved interface.
* Hardened custom-table identifiers and SVG output.
* Updated distribution documentation and screenshots metadata.

= 1.0.0 =
* First stable Free and Pro packages.
* Email cancellation also revokes optional measurement and clears delivery retries.
* Preserved the approved dashboard and delivered a separate visual kit.
