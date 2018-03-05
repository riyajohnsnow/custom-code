<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscriptions_Coupon {

    public static $coupon_error;
    private static $removed_coupons = array();

    public function __construct() {

        add_filter('woocommerce_coupon_discount_types', array($this, 'add_discount_types'));
        add_filter('woocommerce_coupon_get_discount_amount', array($this, 'get_discount_amount'), 10, 5);
        add_filter('woocommerce_coupon_is_valid', array($this, 'validate_subscription_coupon'), 10, 2);
        add_action('woocommerce_before_calculate_totals', array($this, 'remove_coupons'), 10);
        add_filter('woocommerce_product_coupon_types', array($this, 'filter_product_coupon_types'), 10, 1);

        if (!is_admin()) {
            add_filter('woocommerce_coupon_discount_types', array($this, 'add_hf_coupons_to_supported_coupon_types'));
        }
        add_filter('woocommerce_cart_totals_coupon_label', array($this, 'get_hf_coupon_label'), 10, 2);
    }

    public function add_discount_types($discount_types) {

        return array_merge(
                $discount_types, array(
                        'recurring_fee' => __('Recurring Product Discount', HF_Subscriptions::TEXT_DOMAIN),
                        'recurring_percent' => __('Recurring Product % Discount', HF_Subscriptions::TEXT_DOMAIN),
                )
        );
    }

    public function get_discount_amount($discount, $discounting_amount, $cart_item, $single, $coupon) {

        $coupon_type = hf_get_coupon_property($coupon, 'discount_type');

        if (!in_array($coupon_type, array('recurring_fee', 'recurring_percent', 'renewal_fee', 'renewal_percent', 'renewal_cart'))) {
            return $discount;
        }

        if (!hf_cart_contains_renewal() && !HF_Subscriptions_Product::is_subscription($cart_item['data'])) {
            return $discount;
        }
        if (hf_cart_contains_renewal() && !self::is_subsbcription_renewal_line_item($cart_item['data'], $cart_item)) {
            return $discount;
        }

        $discount_amount = 0;
        $cart_item_qty = is_null($cart_item) ? 1 : $cart_item['quantity'];
        $calculation_type = HF_Subscription_Cart::get_calculation_type();

        $apply_recurring_coupon = $apply_recurring_percent_coupon = $apply_initial_coupon = $apply_initial_percent_coupon = $apply_renewal_cart_coupon = false;

        if ('recurring_total' == $calculation_type) {
            $apply_recurring_coupon = ( 'recurring_fee' == $coupon_type ) ? true : false;
            $apply_recurring_percent_coupon = ( 'recurring_percent' == $coupon_type ) ? true : false;
        }

        if ('none' == $calculation_type) {


                if ('recurring_fee' == $coupon_type) {
                    $apply_initial_coupon = true;
                }
                if ('recurring_percent' == $coupon_type) {
                    $apply_initial_percent_coupon = true;
                }
            


            if ('renewal_fee' == $coupon_type) {
                $apply_recurring_coupon = true;
            }
            if ('renewal_percent' == $coupon_type) {
                $apply_recurring_percent_coupon = true;
            }
            if ('renewal_cart' == $coupon_type) {
                $apply_renewal_cart_coupon = true;
            }
        }

        if ($apply_recurring_coupon || $apply_initial_coupon) {

            if ($apply_initial_coupon && 'recurring_fee' == $coupon_type) {
                $discounting_amount = 0;
            }

            $discount_amount = min(hf_get_coupon_property($coupon, 'coupon_amount'), $discounting_amount);
            $discount_amount = $single ? $discount_amount : $discount_amount * $cart_item_qty;
        } elseif ($apply_recurring_percent_coupon) {

            $discount_amount = ( $discounting_amount / 100 ) * hf_get_coupon_property($coupon, 'coupon_amount');
        } elseif ($apply_initial_percent_coupon) {

            if ('recurring_percent' == $coupon_type) {
                $discounting_amount = 0;
            }
            $discount_amount = ( $discounting_amount / 100 ) * hf_get_coupon_property($coupon, 'coupon_amount');
        } elseif ($apply_renewal_cart_coupon) {

            $discount_percent = ( $discounting_amount * $cart_item['quantity'] ) / self::get_renewal_subtotal(hf_get_coupon_property($coupon, 'code'));
            $discount_amount = ( hf_get_coupon_property($coupon, 'coupon_amount') * $discount_percent ) / $cart_item_qty;
        }

        $discount_amount = round($discount_amount, hf_get_rounding_precision());
        return $discount_amount;
    }

    public static function cart_contains_discount($coupon_type = 'any') {

        $contains_discount = false;
        $core_coupons = array('fixed_product', 'percent_product', 'fixed_cart', 'percent');

        if (WC()->cart->applied_coupons) {

            foreach (WC()->cart->applied_coupons as $code) {

                $coupon = new WC_Coupon($code);
                $cart_coupon_type = hf_get_coupon_property($coupon, 'discount_type');

                if ('any' == $coupon_type || $coupon_type == $cart_coupon_type || ( 'core' == $coupon_type && in_array($cart_coupon_type, $core_coupons) )) {
                    $contains_discount = true;
                    break;
                }
            }
        }

        return $contains_discount;
    }

    public function validate_subscription_coupon($valid, $coupon) {

        if (!apply_filters('hf_subscription_validate_coupon_type', true, $coupon, $valid)) {
            return $valid;
        }

        self::$coupon_error = '';
        $coupon_type = hf_get_coupon_property($coupon, 'discount_type');

        if (!in_array($coupon_type, array('recurring_fee',  'recurring_percent',  'renewal_fee', 'renewal_percent', 'renewal_cart'))) {

            if (( hf_cart_contains_renewal() || HF_Subscription_Cart::cart_contains_subscription() ) && 0 == WC()->cart->subtotal) {
                self::$coupon_error = __('Sorry, this coupon is only valid for an initial payment and the cart does not require an initial payment.', HF_Subscriptions::TEXT_DOMAIN);
            }
        } else {

            if (hf_cart_contains_renewal() && !in_array($coupon_type, array('renewal_fee', 'renewal_percent', 'renewal_cart'))) {
                self::$coupon_error = __('Sorry, this coupon is only valid for new subscriptions.', HF_Subscriptions::TEXT_DOMAIN);
            }

            if (!hf_cart_contains_renewal() && !HF_Subscription_Cart::cart_contains_subscription()) {
                self::$coupon_error = __('Sorry, this coupon is only valid for subscription products.', HF_Subscriptions::TEXT_DOMAIN);
            }

            if (!hf_cart_contains_renewal() && in_array($coupon_type, array('renewal_fee', 'renewal_percent', 'renewal_cart'))) {
                self::$coupon_error = sprintf(__('Sorry, the "%1$s" coupon is only valid for renewals.', HF_Subscriptions::TEXT_DOMAIN), hf_get_coupon_property($coupon, 'code'));
            }

        }

        if (!empty(self::$coupon_error)) {
            $valid = false;
            add_filter('woocommerce_coupon_error', __CLASS__ . '::add_coupon_error', 10);
        }

        return $valid;
    }

    public static function add_coupon_error($error) {

        if (self::$coupon_error) {
            return self::$coupon_error;
        } else {
            return $error;
        }
    }

    private static function is_subscription_discountable($cart_item, $coupon) {

        $product_cats = wp_get_post_terms($cart_item['product_id'], 'product_cat', array('fields' => 'ids'));

        $this_item_is_discounted = false;

        if (sizeof($coupon_product_ids = hf_get_coupon_property($coupon, 'product_ids')) > 0) {

            if (in_array(hf_get_canonical_product_id($cart_item), $coupon_product_ids) || in_array($cart_item['data']->get_parent(), $coupon_product_ids)) {
                $this_item_is_discounted = true;
            }
        } elseif (sizeof($coupon_product_categories = hf_get_coupon_property($coupon, 'product_categories')) > 0) {
            if (sizeof(array_intersect($product_cats, $coupon_product_categories)) > 0) {
                $this_item_is_discounted = true;
            }
        } else {
            $this_item_is_discounted = true;
        }

        if (sizeof($coupon_excluded_product_ids = hf_get_coupon_property($coupon, 'exclude_product_ids')) > 0) {
            if (in_array(hf_get_canonical_product_id($cart_item), $coupon_excluded_product_ids) || in_array($cart_item['data']->get_parent(), $coupon_excluded_product_ids)) {
                $this_item_is_discounted = false;
            }
        }

        if (sizeof($coupon_excluded_product_categories = hf_get_coupon_property($coupon, 'exclude_product_categories')) > 0) {
            if (sizeof(array_intersect($product_cats, $coupon_excluded_product_categories)) > 0) {
                $this_item_is_discounted = false;
            }
        }

        return apply_filters('woocommerce_item_is_discounted', $this_item_is_discounted, $cart_item, $before_tax = false);
    }

    public function remove_coupons($cart) {

        $calculation_type = HF_Subscription_Cart::get_calculation_type();

        if ('none' == $calculation_type || !HF_Subscription_Cart::cart_contains_subscription() || (!is_checkout() && !is_cart() && !defined('WOOCOMMERCE_CHECKOUT') && !defined('WOOCOMMERCE_CART') )) {
            return;
        }

        $applied_coupons = $cart->get_applied_coupons();

        if (!empty($applied_coupons)) {

            $coupons_to_reapply = array();

            foreach ($applied_coupons as $coupon_code) {

                $coupon = new WC_Coupon($coupon_code);
                $coupon_type = hf_get_coupon_property($coupon, 'discount_type');

                if (in_array($coupon_type, array('recurring_fee', 'recurring_percent'))) {
                    if ('recurring_total' == $calculation_type) {
                        $coupons_to_reapply[] = $coupon_code;
                    } elseif ('none' == $calculation_type && !HF_Subscription_Cart::all_cart_items_have_free_trial()) {
                        $coupons_to_reapply[] = $coupon_code;
                    } else {
                        self::$removed_coupons[] = $coupon_code;
                    }
                } elseif (( 'none' == $calculation_type ) && !in_array($coupon_type, array('recurring_fee', 'recurring_percent'))) {
                    $coupons_to_reapply[] = $coupon_code;
                } else {
                    self::$removed_coupons[] = $coupon_code;
                }
            }

            $cart->remove_coupons();
            $cart->applied_coupons = $coupons_to_reapply;

            if (isset($cart->coupons)) {
                $cart->coupons = $cart->get_coupons();
            }
        }
    }

    public function filter_product_coupon_types($product_coupon_types) {

        if (is_array($product_coupon_types)) {
            $product_coupon_types = array_merge($product_coupon_types, array('recurring_fee', 'recurring_percent', 'renewal_fee', 'renewal_percent', 'renewal_cart'));
        }
        return $product_coupon_types;
    }

    private static function get_renewal_subtotal($code) {

        $renewal_coupons = WC()->session->get('hf_renewal_coupons');
        if (empty($renewal_coupons)) {
            return false;
        }
        $subtotal = 0;
        foreach ($renewal_coupons as $order_id => $coupons) {
            foreach ($coupons as $coupon_code => $coupon_properties) {
                if ($coupon_code == $code) {
                    if ($order = wc_get_order($order_id)) {
                        $subtotal = $order->get_subtotal();
                    }
                    break;
                }
            }
        }
        return $subtotal;
    }

    private static function is_subsbcription_renewal_line_item($product_id, $cart_item) {

        $is_subscription_line_item = false;

        if (is_object($product_id)) {
            $product_id = $product_id->get_id();
        }

        if (!empty($cart_item['subscription_renewal'])) {
            if ($subscription = hf_get_subscription($cart_item['subscription_renewal']['subscription_id'])) {
                foreach ($subscription->get_items() as $item) {
                    $item_product_id = hf_get_canonical_product_id($item);
                    if (!empty($item_product_id) && $item_product_id == $product_id) {
                        $is_subscription_line_item = true;
                    }
                }
            }
        }

        return apply_filters('is_subscription_renewal_line_item', $is_subscription_line_item, $product_id, $cart_item);
    }

    public function add_hf_coupons_to_supported_coupon_types($coupon_types) {
        return array_merge(
                $coupon_types, array(
                    'renewal_percent' => __('Renewal % discount', HF_Subscriptions::TEXT_DOMAIN),
                    'renewal_fee' => __('Renewal product discount', HF_Subscriptions::TEXT_DOMAIN),
                    'renewal_cart' => __('Renewal cart discount', HF_Subscriptions::TEXT_DOMAIN),
                )
        );
    }

    public function get_hf_coupon_label($label, $coupon) {

        if ('renewal_cart' === hf_get_coupon_property($coupon, 'discount_type')) {
            $label = esc_html(__('Renewal Discount', HF_Subscriptions::TEXT_DOMAIN));
        }
        return $label;
    }

    public static function increase_coupon_discount_amount($cart, $code, $amount) {

        if (empty($cart->coupon_discount_amounts[$code])) {
            $cart->coupon_discount_amounts[$code] = 0;
        }
        $cart->coupon_discount_amounts[$code] += $amount;
        return $cart;
    }

    public static function restore_coupons($cart) {

        if (!empty(self::$removed_coupons)) {
            $cart->applied_coupons = array_merge($cart->applied_coupons, self::$removed_coupons);
            if (isset($cart->coupons)) {
                $cart->coupons = $cart->get_coupons();
            }
            self::$removed_coupons = array();
        }
    }

}

new HF_Subscriptions_Coupon();