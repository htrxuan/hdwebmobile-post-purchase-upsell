<?php

namespace htrxuan\hdppu;

if (!defined('ABSPATH')) {
    exit;
}

class HDPPU_Admin
{

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
        require_once HDPPU_PLUGIN_DIR . 'includes/class-hdppu-hub.php';
        add_filter('hdwebmobile_hub_tabs', array($this, 'register_hub_tabs'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_hub_tabs($tabs)
    {
        $tabs['post-purchase-upsell'] = array(
            'label'  => __('Post-Purchase Upsell', 'hdwebmobile-post-purchase-upsell'),
            'order'  => 161,
            'render' => array($this, 'render_page'),
        );
        return $tabs;
    }

    public function register_settings()
    {
        register_setting('hdppu_settings_group', 'hdppu_settings', array('htrxuan\\hdppu\\HDPPU_Repository', 'save_settings'));
    }

    public function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to do this.', 'hdwebmobile-post-purchase-upsell'));
        }

        $settings = HDPPU_Repository::get_settings();
        ?>
        <p><?php esc_html_e('Offer one extra product, at a discount, on the order-received page right after checkout. Accepting creates a separate new order for just that item, at its own payment link -- nothing is added to, or recharged on, the original order.', 'hdwebmobile-post-purchase-upsell'); ?></p>

        <form method="post" action="options.php">
            <?php settings_fields('hdppu_settings_group'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable', 'hdwebmobile-post-purchase-upsell'); ?></th>
                    <td><label><input type="checkbox" name="hdppu_settings[enabled]" value="1" <?php checked(!empty($settings['enabled'])); ?> /> <?php esc_html_e('Show the offer on the order-received page', 'hdwebmobile-post-purchase-upsell'); ?></label></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hdppu_product_id"><?php esc_html_e('Product', 'hdwebmobile-post-purchase-upsell'); ?></label></th>
                    <td><?php $this->render_product_select($settings['product_id']); ?></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hdppu_discount_percent"><?php esc_html_e('Discount %', 'hdwebmobile-post-purchase-upsell'); ?></label></th>
                    <td><input type="number" id="hdppu_discount_percent" name="hdppu_settings[discount_percent]" step="any" min="0" max="99" value="<?php echo esc_attr($settings['discount_percent']); ?>" style="width:100px;" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="hdppu_headline"><?php esc_html_e('Headline', 'hdwebmobile-post-purchase-upsell'); ?></label></th>
                    <td><input type="text" id="hdppu_headline" name="hdppu_settings[headline]" value="<?php echo esc_attr($settings['headline']); ?>" style="width:100%;max-width:500px;" /></td>
                </tr>
            </table>
            <?php submit_button(__('Save Settings', 'hdwebmobile-post-purchase-upsell')); ?>
        </form>
        <?php
    }

    private function render_product_select($selected_id)
    {
        $products = wc_get_products(array('limit' => 200, 'status' => 'publish', 'orderby' => 'title', 'order' => 'ASC'));
        ?>
        <select id="hdppu_product_id" name="hdppu_settings[product_id]">
            <option value="0"><?php esc_html_e('-- Select a product --', 'hdwebmobile-post-purchase-upsell'); ?></option>
            <?php foreach ($products as $product) : ?>
                <option value="<?php echo esc_attr($product->get_id()); ?>" <?php selected((int) $selected_id, $product->get_id()); ?>>
                    <?php echo esc_html($product->get_name()); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }
}
