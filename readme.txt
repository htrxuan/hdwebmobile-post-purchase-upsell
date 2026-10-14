=== HDWebmobile Post-Purchase Upsell ===
Contributors: htrxuan
Donate link: https://paypal.me/htrxuan/20
Tags: woocommerce, post-purchase upsell, thank you page, order bump, conversion
Requires at least: 6.9
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A one-click upsell on the order-received page -- the original order is always verified by its real key, the price always computed server-side.

== Description ==

HDWebmobile Post-Purchase Upsell shows one extra product, at a discount, on the order-received page right after checkout. Accepting it creates a brand new, separate order for just that item, with its own payment link -- nothing is added to or recharged on the original order.

= Security by design =
A common weakness in post-purchase upsell tools is an "accept this upsell" endpoint that takes the original order id straight from the request and trusts it to determine whose order (and billing/shipping address) the upsell attaches to, without ever verifying the requester actually owns that order (CWE-639, Authorization Bypass Through User-Controlled Key -- an IDOR). Anyone able to guess or increment an order id could attach an upsell order to a stranger's order, and in doing so read back that stranger's billing and shipping address.

This plugin resolves the original order exactly the way WooCommerce's own guest-order lookup does:

* The original order is only ever accepted when both its real id AND its real order key match, via a constant-time comparison -- the same check WooCommerce's own checkout page already performs before this plugin's offer is even shown.
* The offer's price is always computed fresh from the upsell product's own regular price and the configured discount -- never a value carried on the accept request.
* Accepting is idempotent: a retried or double-submitted accept can never create a second upsell order for the same purchase.

= Key Features =
* One admin-configured upsell offer shown right after checkout
* Accepting creates a genuinely separate order, pre-filled with the same billing details, ready to pay
* Works for guests and logged-in customers alike

= Limitations (please read before installing) =
* One global offer, not per-product upselling
* No automatic payment/recharge -- the customer completes a normal (short) checkout for the new order

= Enjoying this plugin? =
If it helps your site, please consider [leaving an honest review on WordPress.org](https://wordpress.org/support/plugin/hdwebmobile-post-purchase-upsell/reviews/#new-post) -- it helps other WordPress users find it. Found a problem or have an idea? Please [open a support topic](https://wordpress.org/support/plugin/hdwebmobile-post-purchase-upsell/) first so it can be fixed.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/hdwebmobile-post-purchase-upsell` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Post-Purchase Upsell** to choose a product and discount.

== How to Use ==

= 1. Configure the offer =
Pick a product, a discount percentage, and a headline.

= 2. Customers see it right after checkout =
A single button on the order-received page creates a new order for the upsell item at its discounted price.

== Screenshots ==

1. The upsell offer on the order-received page.
2. The settings screen under WooCommerce > HDWebmobile.

== Changelog ==

= 1.0.0 =
* Initial release: a one-click post-purchase upsell, with the original order always re-verified by its real key and the price always computed server-side.
