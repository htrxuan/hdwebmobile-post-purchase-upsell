# HDWebmobile Post-Purchase Upsell

A one-click upsell on the order-received page -- the original order is always verified by its real key, the price always computed server-side.

- **WordPress.org:** https://wordpress.org/plugins/hdwebmobile-post-purchase-upsell/
- **Requires:** WordPress 6.9+, WooCommerce, PHP 7.4+
- **License:** GPLv2 or later

## Description

Shows one extra product, at a discount, on the order-received page right after checkout. Accepting it creates a brand new, separate order for just that item, with its own payment link -- nothing is added to or recharged on the original order.

## Why this plugin exists

A competing post-purchase upsell plugin's "accept this upsell" endpoint took the original order id straight from the request and trusted it to determine whose order (and billing/shipping address) the upsell attached to, without ever verifying the requester actually owned that order (CWE-639, Authorization Bypass Through User-Controlled Key -- an IDOR). Anyone able to guess or increment an order id could attach an upsell order to a stranger's order, and in doing so read back that stranger's billing and shipping address.

This plugin resolves the original order exactly the way WooCommerce's own guest-order lookup does:

* The original order is only ever accepted when both its real id AND its real order key match, via a constant-time comparison -- the same check WooCommerce's own checkout page already performs before this plugin's offer is even shown.
* The offer's price is always computed fresh from the upsell product's own regular price and the configured discount -- never a value carried on the accept request.
* Accepting is idempotent: a retried or double-submitted accept can never create a second upsell order for the same purchase.

## Features

* One admin-configured upsell offer shown right after checkout
* Accepting creates a genuinely separate order, pre-filled with the same billing details, ready to pay
* Works for guests and logged-in customers alike

## Limitations

* One global offer, not per-product upselling
* No automatic payment/recharge -- the customer completes a normal (short) checkout for the new order

## Installation

1. Upload to `/wp-content/plugins/hdwebmobile-post-purchase-upsell`, or install through the WordPress plugins screen.
2. Activate. WooCommerce must already be installed and active.
3. Go to **WooCommerce > HDWebmobile > Post-Purchase Upsell** to choose a product and discount.

## Development

```
includes/
  class-hdppu-activator.php   activation check
  class-hdppu-core.php        bootstraps admin + frontend
  class-hdppu-repository.php  settings, order-ownership check, and the ONLY place an upsell order is created -- see its docblock for the security design
  class-hdppu-admin.php       hub tab and settings form
  class-hdppu-frontend.php    the offer on the order-received page + its guest-friendly accept handler
  class-hdppu-hub.php         shared HDWebmobile admin hub (see the suite's other plugins)
```

## License

GPLv2 or later — https://www.gnu.org/licenses/gpl-2.0.html
