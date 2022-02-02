<?php
/**
 * Plugin Name:     Otomaties WooCommerce Coupon Generator
 * Plugin URI:      http://tombroucke.be
 * Description:     Generate coupons in bulk
 * Author:          Tom Broucke
 * Author URI:      http://tombroucke.be
 * Text Domain:     coupon-generator
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         CouponGenerator
 */

namespace Otomaties\WooCommerce\CouponGenerator;

if (! defined('ABSPATH')) {
    exit;
}

class Plugin
{

    private static $instance = null;

    /**
     * Creates or returns an instance of this class.
     * @since  1.0.0
     * @return Plugin A single instance of this class.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_init', array($this, 'generateCoupons'));
        add_action('admin_menu', array($this, 'generateCouponsMenuItem'));
    }

    public function generateCouponsMenuItem()
    {
        add_submenu_page(
            'woocommerce-marketing',
            __('Bulk generate coupons', 'coupon-generator'),
            __('Bulk generate coupons', 'coupon-generator'),
            'edit_posts',
            'generate-coupons',
            array($this, 'generateCouponsPage'),
            58
        );
    }

    public function generateCoupons()
    {
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        $expiry = filter_input(INPUT_POST, 'expiry', FILTER_SANITIZE_STRING);
        $discountType = filter_input(INPUT_POST, 'type', FILTER_SANITIZE_STRING);
        $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_INT);
        $usageLimit = filter_input(INPUT_POST, 'usage_limit', FILTER_SANITIZE_NUMBER_INT);
        $price = filter_input(INPUT_POST, 'price', FILTER_SANITIZE_NUMBER_FLOAT);
        $productCategories = isset($_POST['product_categories']) ? $_POST['product_categories'] : [];

        if ($action && $action == 'generate_coupons' && $amount && $expiry && $discountType && $price) {
            if (!check_admin_referer('generate_coupons' . get_current_user_id())) {
                wp_redirect(admin_url('edit.php?post_type=shop_coupon'));
            }

            for ($i=0; $i < $amount; $i++) {
                $coupon_code = substr(md5(uniqid(mt_rand(), true)), 0, 8);

                $coupon = array(
                    'post_title' => $coupon_code,
                    'post_content' => '',
                    'post_status' => 'publish',
                    'post_author' => get_current_user_id(),
                    'post_type' => 'shop_coupon'
                );

                $new_coupon_id = wp_insert_post($coupon);

                // Add meta
                update_post_meta($new_coupon_id, 'discount_type', $discountType);
                update_post_meta($new_coupon_id, 'coupon_amount', $price);
                update_post_meta($new_coupon_id, 'individual_use', 'no');
                update_post_meta($new_coupon_id, 'product_ids', '');
                update_post_meta($new_coupon_id, 'exclude_product_ids', '');
                update_post_meta($new_coupon_id, 'usage_limit', $usageLimit);
                update_post_meta($new_coupon_id, 'expiry_date', $expiry);
                update_post_meta($new_coupon_id, 'apply_before_tax', 'yes');
                update_post_meta($new_coupon_id, 'free_shipping', 'no');

                if (!empty($productCategories)) {
                    $productCategoriesFiltered = [];
                    foreach ($productCategories as $key => $value) {
                        $productCategoriesFiltered[sanitize_key($key)] = sanitize_key($value);
                    }
                    update_post_meta($new_coupon_id, 'product_categories', $productCategoriesFiltered);
                }
            }
            wp_redirect(admin_url('edit.php?post_type=shop_coupon'));
        }
    }

    public function generateCouponsPage()
    {
        ?>
        <div class="wrap">
            <h1><?php _e('Generate coupons', 'coupon-generator'); ?></h1>

            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Type', 'coupon-generator'); ?></th>
                        <td>
                            <select name="type">
                                <?php foreach (wc_get_coupon_types() as $couponType => $couponName) : ?>
                                    <option value="<?php echo $couponType; ?>"><?php echo $couponName; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e('Price / percentage', 'coupon-generator'); ?></th>
                        <td><input type="text" name="price" value="5" required /></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e('Amount', 'coupon-generator'); ?></th>
                        <td><input type="number" name="amount" value="10" required /></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e('Usage limit', 'coupon-generator'); ?></th>
                        <td>
                            <input type="number" name="usage_limit" value="1" required />
                            <p class="description"><?php _e('0 for unlimited', 'coupon-generator') ?></p>
                        </td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e('Expiry date (YYYY-MM-DD)', 'coupon-generator'); ?></th>
                        <td><input type="text" name="expiry" class="datepicker" value="<?php echo date('Y-m-d', strtotime('+1 year')) ?>" required /></td>
                    </tr>

                    <tr valign="top">
                        <th scope="row"><?php _e('Product categories', 'coupon-generator'); ?></th>
                        <td>
                            <select id="product_categories" name="product_categories[]" class="wc-enhanced-select" multiple="multiple">
                                <?php
                                $categories   = get_terms('product_cat', 'orderby=name&hide_empty=0');
                                if ($categories) {
                                    foreach ($categories as $cat) {
                                        echo '<option value="' . esc_attr($cat->term_id) . '">' . esc_html($cat->name) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </td>
                    </tr>


                </table>
                <?php wp_nonce_field('generate_coupons' . get_current_user_id()); ?>
                <input type="hidden" name="action" value="generate_coupons">
                <input class="button button-primary" type="submit" value="<?php _e('Generate', 'coupon-generator'); ?>">
            </form>
        </div>
        <?php
    }
}

add_action('plugins_loaded', array( '\\Otomaties\\WooCommerce\\CouponGenerator\\Plugin', 'get_instance' ));
