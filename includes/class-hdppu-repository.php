<?php

namespace htrxuan\hdppu;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings, order-ownership verification, and the only place an upsell order is ever created.
 *
 * A competing post-purchase upsell plugin's "accept this upsell" endpoint took the original
 * order id straight from the request and trusted it to determine whose order the upsell (and
 * its billing/shipping address) attached to, without ever verifying the requester actually
 * owned that order (CWE-639, Authorization Bypass Through User-Controlled Key -- an IDOR).
 * Anyone able to guess or increment an order id could attach an upsell order to a stranger's
 * order, and in doing so read back that stranger's billing/shipping address from the created
 * order.
 *
 * This plugin resolves the original order the exact same way WooCommerce's own guest-order
 * lookup does (confirmed by reading `includes/shortcodes/class-wc-shortcode-checkout.php`):
 * the real order id AND its order key must both match, via `hash_equals()`, before anything is
 * ever read from or written to that order -- there is no code path that acts on a bare order id
 * alone. The offer itself is only ever rendered from `woocommerce_thankyou`, which (per WC
 * core's own `templates/checkout/thankyou.php`) only fires after the checkout shortcode has
 * ALREADY performed this same key check for that page view -- so this class re-derives and
 * re-verifies the same order+key pair independently, as defense in depth, rather than trusting
 * that the page context alone is safe.
 */
class HDPPU_Repository
{
    public static function get_settings()
    {
        $defaults = array(
            'enabled'          => 0,
            'product_id'       => 0,
            'discount_percent' => 20.0,
            'headline'         => __('Add this to your order for a special one-time price!', 'hdwebmobile-post-purchase-upsell'),
        );
        $settings = get_option('hdppu_settings', array());
        return wp_parse_args(is_array($settings) ? $settings : array(), $defaults);
    }

    public static function save_settings($input)
    {
        $current = self::get_settings();

        $percent = isset($input['discount_percent']) ? (float) $input['discount_percent'] : $current['discount_percent'];
        if ($percent < 0) {
            $percent = 0.0;
        }
        if ($percent > 99) {
            $percent = 99.0;
        }

        $product_id = isset($input['product_id']) ? absint($input['product_id']) : 0;
        if ($product_id && !wc_get_product($product_id)) {
            $product_id = 0;
        }

        $settings = array(
            'enabled'          => !empty($input['enabled']) ? 1 : 0,
            'product_id'       => $product_id,
            'discount_percent' => $percent,
            'headline'         => isset($input['headline']) ? sanitize_text_field($input['headline']) : $current['headline'],
        );
        update_option('hdppu_settings', $settings);
        return $settings;
    }

    public static function is_offer_available()
    {
        $settings = self::get_settings();
        if (empty($settings['enabled']) || empty($settings['product_id'])) {
            return false;
        }
        $product = wc_get_product($settings['product_id']);
        return $product instanceof \WC_Product && $product->is_purchasable() && $product->is_in_stock();
    }

    public static function get_discounted_price()
    {
        $settings = self::get_settings();
        $product  = wc_get_product($settings['product_id']);
        if (!$product instanceof \WC_Product) {
            return null;
        }
        $regular = (float) $product->get_regular_price();
        if ($regular <= 0) {
            return null;
        }
        return round($regular * (1 - (float) $settings['discount_percent'] / 100), 2);
    }

    /**
     * The only order lookup in this plugin. Both the id AND the key must match a real order --
     * mirrors WooCommerce's own guest-order-ownership check exactly. Returns null for anything
     * that doesn't check out, with no distinction in behavior between "wrong key" and "no such
     * order" that could be used to enumerate valid order ids.
     */
    public static function resolve_order($order_id, $order_key)
    {
        $order_id = absint($order_id);
        if (!$order_id || !is_string($order_key) || '' === $order_key) {
            return null;
        }
        $order = wc_get_order($order_id);
        if (!$order instanceof \WC_Order) {
            return null;
        }
        if ($order->get_id() !== $order_id || !hash_equals($order->get_order_key(), $order_key)) {
            return null;
        }
        return $order;
    }

    public static function already_offered(\WC_Order $order)
    {
        return 'yes' === $order->get_meta('_hdppu_offered');
    }

    /**
     * The only write path that creates an upsell order. Takes a real, already-verified
     * WC_Order (never an id) and returns the new order, or null if the offer can't be applied
     * (already offered, or no longer available). The new order's price is always
     * get_discounted_price()'s own fresh computation -- never a value from a caller.
     */
    public static function create_upsell_order(\WC_Order $original_order)
    {
        if (self::already_offered($original_order)) {
            return null;
        }

        // Mark the original order immediately, before creating anything, so a retried/
        // double-submitted accept can never create two upsell orders for the same purchase.
        $original_order->update_meta_data('_hdppu_offered', 'yes');
        $original_order->save();

        if (!self::is_offer_available()) {
            return null;
        }
        $settings = self::get_settings();
        $price    = self::get_discounted_price();
        if (null === $price) {
            return null;
        }

        $new_order = wc_create_order(array(
            'customer_id' => $original_order->get_customer_id(),
            'status'      => 'pending',
        ));
        if (is_wp_error($new_order)) {
            return null;
        }

        // Pass the price directly via $args rather than fetching and re-saving the item
        // afterward -- WC_Order::add_product() itself keeps a single, canonical in-memory
        // item object; a second object later fetched via get_item($item_id) is a separate
        // instance that calculate_totals() below never sees, so mutating that copy would
        // silently leave the order's real total computed from the product's regular price
        // instead (caught live by this plugin's own test suite before this fix).
        $product = wc_get_product($settings['product_id']);
        $new_order->add_product($product, 1, array(
            'subtotal' => $price,
            'total'    => $price,
        ));

        foreach (array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'state', 'postcode', 'country', 'email', 'phone') as $field) {
            $getter = 'get_billing_' . $field;
            $setter = 'set_billing_' . $field;
            if (is_callable(array($original_order, $getter)) && is_callable(array($new_order, $setter))) {
                $new_order->{$setter}($original_order->{$getter}());
            }
        }

        $new_order->update_meta_data('_hdppu_original_order_id', $original_order->get_id());
        $new_order->calculate_totals();
        $new_order->save();

        $original_order->update_meta_data('_hdppu_upsell_order_id', $new_order->get_id());
        $original_order->save();

        return $new_order;
    }
}
