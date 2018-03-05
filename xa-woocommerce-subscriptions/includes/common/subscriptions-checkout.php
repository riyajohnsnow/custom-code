<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscription_Checkout {

    private static $guest_checkout_option_changed = false;

    public function __construct() {

        add_action('woocommerce_checkout_order_processed', __CLASS__. '::process_checkout', 100, 2);
        add_action('woocommerce_before_checkout_form', array($this, 'make_checkout_registration_possible'), -1);
        add_action('woocommerce_checkout_fields',  array($this, 'make_checkout_account_fields_required'), 10);
        add_action('woocommerce_after_checkout_form',  array($this, 'restore_checkout_registration_settings'), 100);
        add_filter('woocommerce_params',  array($this, 'filter_woocommerce_script_paramaters'), 10, 1);
        add_filter('wc_checkout_params',  array($this, 'filter_woocommerce_script_paramaters'), 10, 1);
        add_action('woocommerce_before_checkout_process', array($this, 'force_registration_during_checkout'), 10);
        add_action('woocommerce_checkout_create_order_line_item',  array($this, 'remove_backorder_meta_from_subscription_line_item'), 10, 4);
    }

    public static function process_checkout($order_id, $posted_data) {

        if (!HF_Subscription_Cart::cart_contains_subscription()) {
            return;
        }
        $order = new WC_Order($order_id);
        $subscriptions = array();

        $subscriptions = hf_get_subscriptions_for_order(hf_get_objects_property($order, 'id'), array('order_type' => 'parent'));

        if (!empty($subscriptions)) {
            remove_action('before_delete_post', 'HF_Subscription_Manager::maybe_cancel_subscription');
            foreach ($subscriptions as $subscription) {
                wp_delete_post($subscription->get_id());
            }
            add_action('before_delete_post', 'HF_Subscription_Manager::maybe_cancel_subscription');
        }

        HF_Subscription_Cart::set_global_recurring_shipping_packages();

        //loop for each element in cart
        foreach (WC()->cart->recurring_carts as $recurring_cart) {

            $subscription = self::create_subscription($order, $recurring_cart, $posted_data);

            if (is_wp_error($subscription)) {
                throw new Exception($subscription->get_error_message());
            }

            do_action('woocommerce_checkout_subscription_created', $subscription, $order, $recurring_cart);
        }

        do_action('subscriptions_created_for_order', $order);
    }

    public static function create_subscription($order, $cart, $posted_data) {
        global $wpdb;

        try {
            $wpdb->query('START TRANSACTION');

            //Riya
            $bundles_exist = 0;
            foreach ($cart->get_cart() as $cart_item) {
                $bundles_exist = $cart_item['data']->meta_exists('wcpb_bundle_products');
                if ($bundles_exist) {
                    $bundleProducts = json_decode($cart_item['data']->get_meta('wcpb_bundle_products', true));
                }
            }
            $variation_id = hf_cart_pluck($cart, 'variation_id');
            $product_id = empty($variation_id) ? hf_cart_pluck($cart, 'product_id') : $variation_id;

            //Riya : Customised Flow for bundle subscription product
            if ($bundles_exist && count($bundleProducts) > 0) {
                //Riya : Loop for each product from bundle
                foreach ($bundleProducts as $bundleProductId => $bundleProductValue) {
                    //Riya : To check product inside bundle is type of subscription or not
                    if (HF_Subscriptions_Product::is_subscription($bundleProductId)) {
                        $subscription_period = HF_Subscriptions_Product::get_meta_data($bundleProductId, "subscription_period", 0);
                        $billing_interval = HF_Subscriptions_Product::get_meta_data($bundleProductId, "subscription_period_interval", 0);
                        $subscription = hf_create_subscription(array(
                            'start_date' => $cart->start_date,
                            'order_id' => hf_get_objects_property($order, 'id'),
                            'customer_id' => $order->get_user_id(),
                            'billing_period' => $subscription_period,
                            'billing_interval' => $billing_interval,
                            'customer_note' => hf_get_objects_property($order, 'customer_note'),
                        ));

                        if (is_wp_error($subscription)) {
                            throw new Exception($subscription->get_error_message());
                        }

                        $subscription = hf_copy_order_address($order, $subscription);

                        $subscription->update_dates(array(
                            'next_payment' => $cart->next_payment_date,
                            'end' => $cart->end_date,
                        ));

                        $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                        $order_payment_method = hf_get_objects_property($order, 'payment_method');

                        if ($cart->needs_payment() && isset($available_gateways[$order_payment_method])) {
                            $subscription->set_payment_method($available_gateways[$order_payment_method]);
                        }

                        if (!$cart->needs_payment()) {
                            $subscription->set_requires_manual_renewal(true);
                        } elseif (!isset($available_gateways[$order_payment_method]) || !$available_gateways[$order_payment_method]->supports('subscriptions')) {
                            $subscription->set_requires_manual_renewal(true);
                        }

                        hf_copy_order_meta($order, $subscription, 'subscription');

                        if (is_callable(array(WC()->checkout, 'create_order_line_items'))) {
                            WC()->checkout->create_order_line_items($subscription, $cart);
                        } else {
                            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                                $item_id = self::add_cart_item($subscription, $cart_item, $cart_item_key);
                            }
                        }

                        if (is_callable(array(WC()->checkout, 'create_order_fee_lines'))) {
                            WC()->checkout->create_order_fee_lines($subscription, $cart);
                        } else {
                            foreach ($cart->get_fees() as $fee_key => $fee) {
                                $item_id = $subscription->add_fee($fee);

                                if (!$item_id) {
                                    throw new Exception(sprintf(__('Error %d: Unable to create subscription. Please try again.', HF_Subscriptions::TEXT_DOMAIN), 403));
                                }

                                do_action('woocommerce_add_order_fee_meta', $subscription->get_id(), $item_id, $fee, $fee_key);
                            }
                        }

                        self::add_shipping($subscription, $cart);

                        if (is_callable(array(WC()->checkout, 'create_order_tax_lines'))) {
                            WC()->checkout->create_order_tax_lines($subscription, $cart);
                        } else {
                            foreach (array_keys($cart->taxes + $cart->shipping_taxes) as $tax_rate_id) {
                                if ($tax_rate_id && !$subscription->add_tax($tax_rate_id, $cart->get_tax_amount($tax_rate_id), $cart->get_shipping_tax_amount($tax_rate_id)) && apply_filters('woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated') !== $tax_rate_id) {
                                    throw new Exception(sprintf(__('Error %d: Unable to add tax to subscription. Please try again.', HF_Subscriptions::TEXT_DOMAIN), 405));
                                }
                            }
                        }

                        if (is_callable(array(WC()->checkout, 'create_order_coupon_lines'))) {
                            WC()->checkout->create_order_coupon_lines($subscription, $cart);
                        } else {
                            foreach ($cart->get_coupons() as $code => $coupon) {
                                if (!$subscription->add_coupon($code, $cart->get_coupon_discount_amount($code), $cart->get_coupon_discount_tax_amount($code))) {
                                    throw new Exception(sprintf(__('Error %d: Unable to create order. Please try again.', HF_Subscriptions::TEXT_DOMAIN), 406));
                                }
                            }
                        }

                        $subscription->set_shipping_total($cart->shipping_total);
                        $subscription->set_discount_total($cart->get_cart_discount_total());
                        $subscription->set_discount_tax($cart->get_cart_discount_tax_total());
                        $subscription->set_cart_tax($cart->tax_total);
                        $subscription->set_shipping_tax($cart->shipping_tax_total);
                        $subscription->set_total($cart->total);

                        do_action('woocommerce_checkout_create_subscription', $subscription, $posted_data);
                        $subscription->save();
                    }

                }
            } else {
                //Riya : Normal Flow for single subscription product
                $subscription = hf_create_subscription(array(
                    'start_date' => $cart->start_date,
                    'order_id' => hf_get_objects_property($order, 'id'),
                    'customer_id' => $order->get_user_id(),
                    'billing_period' => hf_cart_pluck($cart, 'subscription_period'),
                    'billing_interval' => hf_cart_pluck($cart, 'subscription_period_interval'),
                    'customer_note' => hf_get_objects_property($order, 'customer_note'),
                ));

                if (is_wp_error($subscription)) {
                    throw new Exception($subscription->get_error_message());
                }

                $subscription = hf_copy_order_address($order, $subscription);

                $subscription->update_dates(array(
                    'next_payment' => $cart->next_payment_date,
                    'end' => $cart->end_date,
                ));

                $available_gateways = WC()->payment_gateways->get_available_payment_gateways();
                $order_payment_method = hf_get_objects_property($order, 'payment_method');

                if ($cart->needs_payment() && isset($available_gateways[$order_payment_method])) {
                    $subscription->set_payment_method($available_gateways[$order_payment_method]);
                }

                if (!$cart->needs_payment()) {
                    $subscription->set_requires_manual_renewal(true);
                } elseif (!isset($available_gateways[$order_payment_method]) || !$available_gateways[$order_payment_method]->supports('subscriptions')) {
                    $subscription->set_requires_manual_renewal(true);
                }

                hf_copy_order_meta($order, $subscription, 'subscription');

                if (is_callable(array(WC()->checkout, 'create_order_line_items'))) {
                    WC()->checkout->create_order_line_items($subscription, $cart);
                } else {
                    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                        $item_id = self::add_cart_item($subscription, $cart_item, $cart_item_key);
                    }
                }

                if (is_callable(array(WC()->checkout, 'create_order_fee_lines'))) {
                    WC()->checkout->create_order_fee_lines($subscription, $cart);
                } else {
                    foreach ($cart->get_fees() as $fee_key => $fee) {
                        $item_id = $subscription->add_fee($fee);

                        if (!$item_id) {
                            throw new Exception(sprintf(__('Error %d: Unable to create subscription. Please try again.', HF_Subscriptions::TEXT_DOMAIN), 403));
                        }

                        do_action('woocommerce_add_order_fee_meta', $subscription->get_id(), $item_id, $fee, $fee_key);
                    }
                }

                self::add_shipping($subscription, $cart);

                if (is_callable(array(WC()->checkout, 'create_order_tax_lines'))) {
                    WC()->checkout->create_order_tax_lines($subscription, $cart);
                } else {
                    foreach (array_keys($cart->taxes + $cart->shipping_taxes) as $tax_rate_id) {
                        if ($tax_rate_id && !$subscription->add_tax($tax_rate_id, $cart->get_tax_amount($tax_rate_id), $cart->get_shipping_tax_amount($tax_rate_id)) && apply_filters('woocommerce_cart_remove_taxes_zero_rate_id', 'zero-rated') !== $tax_rate_id) {
                            throw new Exception(sprintf(__('Error %d: Unable to add tax to subscription. Please try again.', HF_Subscriptions::TEXT_DOMAIN), 405));
                        }
                    }
                }

                if (is_callable(array(WC()->checkout, 'create_order_coupon_lines'))) {
                    WC()->checkout->create_order_coupon_lines($subscription, $cart);
                } else {
                    foreach ($cart->get_coupons() as $code => $coupon) {
                        if (!$subscription->add_coupon($code, $cart->get_coupon_discount_amount($code), $cart->get_coupon_discount_tax_amount($code))) {
                            throw new Exception(sprintf(__('Error %d: Unable to create order. Please try again.', HF_Subscriptions::TEXT_DOMAIN), 406));
                        }
                    }
                }

                $subscription->set_shipping_total($cart->shipping_total);
                $subscription->set_discount_total($cart->get_cart_discount_total());
                $subscription->set_discount_tax($cart->get_cart_discount_tax_total());
                $subscription->set_cart_tax($cart->tax_total);
                $subscription->set_shipping_tax($cart->shipping_tax_total);
                $subscription->set_total($cart->total);

                do_action('woocommerce_checkout_create_subscription', $subscription, $posted_data);
                $subscription->save();
            }
            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return new WP_Error('checkout-error', $e->getMessage());
        }

        return $subscription;
    }

    public static function add_shipping($subscription, $cart) {

        HF_Subscription_Cart::set_calculation_type('recurring_total');

        foreach ($cart->get_shipping_packages() as $package_index => $base_package) {

            $package = HF_Subscription_Cart::get_calculated_shipping_for_package($base_package);
            $recurring_shipping_package_key = HF_Subscription_Cart::get_recurring_shipping_package_key($cart->recurring_cart_key, $package_index);
            $shipping_method_id = isset(WC()->checkout()->shipping_methods[$package_index]) ? WC()->checkout()->shipping_methods[$package_index] : '';

            if (isset(WC()->checkout()->shipping_methods[$recurring_shipping_package_key])) {
                $shipping_method_id = WC()->checkout()->shipping_methods[$recurring_shipping_package_key];
                $package_key = $recurring_shipping_package_key;
            } else {
                $package_key = $package_index;
            }

            if (isset($package['rates'][$shipping_method_id])) {

                if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {

                    $item_id = $subscription->add_shipping($package['rates'][$shipping_method_id]);

                    do_action('woocommerce_add_shipping_order_item', $subscription->get_id(), $item_id, $package_key);
                    do_action('hf_subscription_add_recurring_shipping_order_item', $subscription->get_id(), $item_id, $package_key);
                } else {

                    $shipping_rate = $package['rates'][$shipping_method_id];
                    $item = new WC_Order_Item_Shipping();
                    $item->legacy_package_key = $package_key;
                    $item->set_props(array(
                        'method_title' => $shipping_rate->label,
                        'method_id' => $shipping_rate->id,
                        'total' => wc_format_decimal($shipping_rate->cost),
                        'taxes' => array('total' => $shipping_rate->taxes),
                        'order_id' => $subscription->get_id(),
                    ));

                    foreach ($shipping_rate->get_meta_data() as $key => $value) {
                        $item->add_meta_data($key, $value, true);
                    }

                    $subscription->add_item($item);

                    $item->save();
                    wc_do_deprecated_action('hf_subscription_add_recurring_shipping_order_item', array($subscription->get_id(), $item->get_id(), $package_key), '2.2.0', 'CRUD and woocommerce_checkout_create_subscription_shipping_item action instead');

                    do_action('woocommerce_checkout_create_order_shipping_item', $item, $package_key, $package);
                    do_action('woocommerce_checkout_create_subscription_shipping_item', $item, $package_key, $package);
                }
            }
        }

        HF_Subscription_Cart::set_calculation_type('none');
    }

    public function remove_backorder_meta_from_subscription_line_item($item, $cart_item_key, $cart_item, $subscription) {

        if (hf_is_subscription($subscription)) {
            $item->delete_meta_data(apply_filters('woocommerce_backordered_item_meta_name', __('Backordered', HF_Subscriptions::TEXT_DOMAIN)));
        }
    }

    public static function add_cart_item($subscription, $cart_item, $cart_item_key) {
        
        if (!HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            _deprecated_function(__METHOD__, '2.2.0', 'WC_Checkout::create_order_line_items( $subscription, $cart )');
        }

        $item_id = $subscription->add_product(
                $cart_item['data'], $cart_item['quantity'], array(
            'variation' => $cart_item['variation'],
            'totals' => array(
                'subtotal' => $cart_item['line_subtotal'],
                'subtotal_tax' => $cart_item['line_subtotal_tax'],
                'total' => $cart_item['line_total'],
                'tax' => $cart_item['line_tax'],
                'tax_data' => $cart_item['line_tax_data'],
            ),
                )
        );

        if (!$item_id) {

            throw new Exception(sprintf(__('Error %d: Unable to create subscription. Please try again.', HF_Subscriptions::TEXT_DOMAIN), 402));
        }

        $cart_item_product_id = ( 0 != $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];


        if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            do_action('woocommerce_add_subscription_item_meta', $item_id, $cart_item, $cart_item_key);
        } else {
            wc_do_deprecated_action('woocommerce_add_subscription_item_meta', array($item_id, $cart_item, $cart_item_key), '3.0', 'CRUD and woocommerce_checkout_create_order_line_item action instead');
        }

        return $item_id;
    }

    public function make_checkout_registration_possible($checkout = '') {

        if (HF_Subscription_Cart::cart_contains_subscription() && !is_user_logged_in()) {

            if (true === $checkout->enable_guest_checkout) {
                $checkout->enable_guest_checkout = false;
                self::$guest_checkout_option_changed = true;

                $checkout->must_create_account = true;
            }
        }
    }

    public function make_checkout_account_fields_required($checkout_fields) {

        if (HF_Subscription_Cart::cart_contains_subscription() && !is_user_logged_in()) {

            $account_fields = array(
                'account_username',
                'account_password',
                'account_password-2',
            );

            foreach ($account_fields as $account_field) {
                if (isset($checkout_fields['account'][$account_field])) {
                    $checkout_fields['account'][$account_field]['required'] = true;
                }
            }
        }

        return $checkout_fields;
    }

    public function restore_checkout_registration_settings($checkout = '') {

        if (self::$guest_checkout_option_changed) {
            $checkout->enable_guest_checkout = true;
            if (!is_user_logged_in()) {
                $checkout->must_create_account = false;
            }
        }
    }

    public function filter_woocommerce_script_paramaters($woocommerce_params) {

        if (HF_Subscription_Cart::cart_contains_subscription() && !is_user_logged_in() && isset($woocommerce_params['option_guest_checkout']) && 'yes' == $woocommerce_params['option_guest_checkout']) {
            $woocommerce_params['option_guest_checkout'] = 'no';
        }

        return $woocommerce_params;
    }

    public function force_registration_during_checkout($woocommerce_params) {

        if (HF_Subscription_Cart::cart_contains_subscription() && !is_user_logged_in()) {
            $_POST['createaccount'] = 1;
        }
    }

}

new HF_Subscription_Checkout();