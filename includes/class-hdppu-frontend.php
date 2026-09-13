<?php

namespace htrxuan\hdppu;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders the upsell offer on the order-received page and handles its (guest-friendly) accept
 * submission. See HDPPU_Repository's docblock for the order-ownership security design.
 */
final class HDPPU_Frontend
{

    const NONCE_ACTION = 'hdppu_accept';

    private static $instance = null;

    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('woocommerce_thankyou', array($this, 'render_offer'));
        add_action('admin_post_hdppu_accept', array($this, 'handle_accept'));
        add_action('admin_post_nopriv_hdppu_accept', array($this, 'handle_accept'));
    }

    /**
     * $order_id comes from woocommerce_thankyou's own parameter (the real order id WooCommerce
     * itself just displayed), and the key is read from the same request's own query string --
     * both are re-verified together via HDPPU_Repository::resolve_order() regardless of the
     * fact that WC core's thank-you template only reaches this hook after already checking the
     * same pair itself.
     */
    public function render_offer($order_id)
    {
        $key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a read-only re-derivation of the order key WooCommerce's own thank-you page already validated for this exact request; nothing is written here.
        $order = HDPPU_Repository::resolve_order($order_id, $key);
        if (!$order || HDPPU_Repository::already_offered($order) || !HDPPU_Repository::is_offer_available()) {
            return;
        }

        $settings = HDPPU_Repository::get_settings();
        $product  = wc_get_product($settings['product_id']);
        if (!$product) {
            return;
        }
        $price = HDPPU_Repository::get_discounted_price();
        ?>
        <div class="hdppu-offer">
            <h2><?php echo esc_html($settings['headline']); ?></h2>
            <p>
                <?php echo esc_html($product->get_name()); ?> &mdash;
                <del><?php echo wp_kses_post(wc_price((float) $product->get_regular_price())); ?></del>
                <ins><?php echo wp_kses_post(wc_price((float) $price)); ?></ins>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="hdppu_accept" />
                <input type="hidden" name="order_id" value="<?php echo esc_attr($order->get_id()); ?>" />
                <input type="hidden" name="order_key" value="<?php echo esc_attr($order->get_order_key()); ?>" />
                <?php wp_nonce_field(self::NONCE_ACTION, 'hdppu_nonce'); ?>
                <button type="submit" class="button alt"><?php esc_html_e('Yes, add it to my order!', 'hdwebmobile-post-purchase-upsell'); ?></button>
            </form>
        </div>
        <?php
    }

    public function handle_accept()
    {
        check_admin_referer(self::NONCE_ACTION, 'hdppu_nonce');

        $order_id  = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $order_key = isset($_POST['order_key']) ? sanitize_text_field(wp_unslash($_POST['order_key'])) : '';

        $order = HDPPU_Repository::resolve_order($order_id, $order_key);
        if (!$order) {
            wp_die(esc_html__('That order could not be found.', 'hdwebmobile-post-purchase-upsell'));
        }

        $new_order = HDPPU_Repository::create_upsell_order($order);

        if ($new_order) {
            wp_safe_redirect($new_order->get_checkout_payment_url());
            exit;
        }

        wp_safe_redirect($order->get_checkout_order_received_url());
        exit;
    }
}
