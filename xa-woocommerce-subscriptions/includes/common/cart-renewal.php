<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Cart_Renewal {

    public $cart_item_key = 'subscription_renewal';

    public function __construct() {

        $this->init_hooks();

        add_action('woocommerce_loaded', array(&$this, 'attach_dependant_hooks'), 10);
        add_filter('woocommerce_get_checkout_payment_url', array(&$this, 'get_checkout_payment_url'), 10, 2);
        add_filter('woocommerce_my_account_my_orders_actions', array(&$this, 'filter_my_account_my_orders_actions'), 10, 2);
        add_filter('woocommerce_default_order_status', array(&$this, 'maybe_preserve_order_status'));
        add_filter('woocommerce_create_order', array(&$this, 'set_renewal_order_cart_hash'), 10, 1);
        add_filter('woocommerce_login_redirect', array(&$this, 'maybe_redirect_after_login'), 10, 1);
        add_action('woocommerce_checkout_order_processed', array(&$this, 'update_session_cart_after_updating_renewal_order'), 10);

    }

    public function attach_dependant_hooks() {

        if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            add_action('woocommerce_add_order_item_meta', array(&$this, 'update_line_item_cart_data'), 10, 3);
            add_filter('woocommerce_checkout_update_customer_data', array(&$this, 'maybe_update_subscription_customer_data'), 10, 2);
        } else {
            add_action('woocommerce_checkout_create_order_line_item', array(&$this, 'add_line_item_meta'), 10, 3);
            add_action('woocommerce_checkout_update_order_meta', array(&$this, 'set_order_item_id'), 10, 2);
            add_action('woocommerce_hidden_order_itemmeta', array(&$this, 'hidden_order_itemmeta'), 10);
            add_filter('woocommerce_checkout_update_user_meta', array(&$this, 'maybe_update_subscription_address_data'), 10, 2);
        }
    }

    public function init_hooks() {

        add_filter('woocommerce_get_cart_item_from_session', array(&$this, 'get_cart_item_from_session'), 10, 3);
        add_action('woocommerce_cart_loaded_from_session', array(&$this, 'cart_items_loaded_from_session'), 10);
        add_action('woocommerce_cart_calculate_fees', array(&$this, 'maybe_add_fees'), 10, 1);
        add_action('template_redirect', array(&$this, 'maybe_setup_cart'), 100);
        add_action('hf_after_renewal_setup_cart_subscription', array(&$this, 'maybe_setup_discounts'), 10, 2);
        add_filter('woocommerce_get_shop_coupon_data', array(&$this, 'renewal_coupon_data'), 10, 2);
        add_action('hf_before_renewal_setup_cart_subscriptions', array(&$this, 'clear_coupons'), 10);
        add_action('woocommerce_remove_cart_item', array(&$this, 'maybe_remove_items'), 10, 1);
        add_action('woocommerce_before_cart_item_quantity_zero', array(&$this, 'maybe_remove_items'), 10, 1);
        add_action('woocommerce_cart_emptied', array(&$this, 'clear_coupons'), 10);
        add_filter('woocommerce_cart_item_removed_title', array(&$this, 'items_removed_title'), 10, 2);
        add_action('woocommerce_cart_item_restored', array(&$this, 'maybe_restore_items'), 10, 1);
        add_filter('woocommerce_product_addons_adjust_price', array(&$this, 'product_addons_adjust_price'), 10, 2);
        add_filter('woocommerce_checkout_get_value', array(&$this, 'checkout_get_value'), 10, 2);
        add_filter('woocommerce_ship_to_different_address_checked', array(&$this, 'maybe_check_ship_to_different_address'), 100, 1);
        add_filter('woocommerce_get_item_data', array(&$this, 'display_line_item_data_in_cart'), 10, 2);
        add_action('woocommerce_loaded', array(&$this, 'attach_dependant_callbacks'), 10);
    }

    public function attach_dependant_callbacks() {

        if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            add_action('woocommerce_add_order_item_meta', array(&$this, 'add_order_item_meta'), 10, 2);
            add_action('woocommerce_add_subscription_item_meta', array(&$this, 'add_order_item_meta'), 10, 2);
        } else {
            add_action('woocommerce_checkout_create_order_line_item', array(&$this, 'add_order_line_item_meta'), 10, 3);
        }
    }

    public function maybe_setup_cart() {

        global $wp;

        if (isset($_GET['pay_for_order']) && isset($_GET['key']) && isset($wp->query_vars['order-pay'])) {

            $order_key = $_GET['key'];
            $order_id = ( isset($wp->query_vars['order-pay']) ) ? $wp->query_vars['order-pay'] : absint($_GET['order_id']);
            $order = wc_get_order($wp->query_vars['order-pay']);

            if (hf_get_objects_property($order, 'order_key') == $order_key && $order->has_status(array('pending', 'failed')) && hf_order_contains_renewal($order)) {

                if (!is_user_logged_in()) {

                    $redirect = add_query_arg(array(
                        'hf_redirect' => 'pay_for_order',
                        'hf_redirect_id' => $order_id,
                            ), get_permalink(wc_get_page_id('myaccount')));

                    wp_safe_redirect($redirect);
                    exit;
                } elseif (!current_user_can('pay_for_order', $order_id)) {

                    wc_add_notice(__('That doesn\'t appear to be your order.', HF_Subscriptions::TEXT_DOMAIN), 'error');
                    wp_safe_redirect(get_permalink(wc_get_page_id('myaccount')));
                    exit;
                }

                $subscriptions = hf_get_subscriptions_for_renewal_order($order);
                do_action('hf_before_renewal_setup_cart_subscriptions', $subscriptions, $order);

                foreach ($subscriptions as $subscription) {
                    do_action('hf_before_renewal_setup_cart_subscription', $subscription, $order);
                    $this->setup_cart($order, array(
                        'subscription_id' => $subscription->get_id(),
                        'renewal_order_id' => $order_id,
                    ));

                    do_action('hf_after_renewal_setup_cart_subscription', $subscription, $order);
                }

                do_action('hf_after_renewal_setup_cart_subscriptions', $subscriptions, $order);

                if (WC()->cart->cart_contents_count != 0) {
                    WC()->session->set('order_awaiting_payment', $order_id);
                    wc_add_notice(__('Complete checkout to renew your subscription.', HF_Subscriptions::TEXT_DOMAIN), 'success');
                }

                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
        }
    }

    protected function setup_cart($subscription, $cart_item_data) {

        WC()->cart->empty_cart(true);
        $success = true;

        foreach ($subscription->get_items() as $item_id => $line_item) {

            $variations = array();
            $item_data = array();
            $custom_line_item_meta = array();
            $reserved_item_meta_keys = array(
                '_item_meta',
                '_item_meta_array',
                '_qty',
                '_tax_class',
                '_product_id',
                '_variation_id',
                '_line_subtotal',
                '_line_total',
                '_line_tax',
                '_line_tax_data',
                '_line_subtotal_tax',
                '_cart_item_key_' . $this->cart_item_key,
                'Backordered',
            );

            if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {

                $product_id = (int) $line_item['product_id'];
                $quantity = (int) $line_item['qty'];
                $variation_id = (int) $line_item['variation_id'];
                $item_name = $line_item['name'];

                foreach ($line_item['item_meta'] as $meta_name => $meta_value) {
                    if (taxonomy_is_product_attribute($meta_name)) {
                        $variations[$meta_name] = $meta_value[0];
                    } elseif (meta_is_product_attribute($meta_name, $meta_value[0], $product_id)) {
                        $variations[$meta_name] = $meta_value[0];
                    } elseif (!in_array($meta_name, $reserved_item_meta_keys)) {
                        $custom_line_item_meta[$meta_name] = $meta_value[0];
                    }
                }
            } else {

                $product_id = $line_item->get_product_id();
                $quantity = $line_item->get_quantity();
                $variation_id = $line_item->get_variation_id();
                $item_name = $line_item->get_name();

                foreach ($line_item->get_meta_data() as $meta) {
                    if (taxonomy_is_product_attribute($meta->key)) {
                        $variations[$meta->key] = $meta->value;
                    } elseif (meta_is_product_attribute($meta->key, $meta->value, $product_id)) {
                        $variations[$meta->key] = $meta->value;
                    } elseif (!in_array($meta->key, $reserved_item_meta_keys)) {
                        $custom_line_item_meta[$meta->key] = $meta->value;
                    }
                }
            }

            $product_id = apply_filters('woocommerce_add_to_cart_product_id', $product_id);
            $product = wc_get_product($product_id);

            $product_deleted_error_message = apply_filters('hf_subscription_renew_deleted_product_error_message', __('The %s product has been deleted and can no longer be renewed. Please choose a new product or contact us for assistance.', HF_Subscriptions::TEXT_DOMAIN));

            if (false === $product) {

                wc_add_notice(sprintf($product_deleted_error_message, $item_name), 'error');
            } elseif ($product->is_type(array('variable-subscription')) && !empty($variation_id)) {

                $variation = wc_get_product($variation_id);

                if (false === $variation) {
                    wc_add_notice(sprintf($product_deleted_error_message, $item_name), 'error');
                }
            }

            $cart_item_data['line_item_id'] = $item_id;
            $cart_item_data['custom_line_item_meta'] = $custom_line_item_meta;

            $item_data = apply_filters('woocommerce_order_again_cart_item_data', array($this->cart_item_key => $cart_item_data), $line_item, $subscription);

            if (!apply_filters('woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations, $item_data)) {
                continue;
            }

            $cart_item_key = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations, $item_data);
            $success = $success && (bool) $cart_item_key;
        }

        if (!$success && hf_is_subscription($subscription)) {
            wc_add_notice(sprintf(esc_html__('Subscription #%s has not been added to the cart.', HF_Subscriptions::TEXT_DOMAIN), $subscription->get_order_number()), 'error');
            WC()->cart->empty_cart(true);
        }

        do_action('woocommerce_setup_cart_for_' . $this->cart_item_key, $subscription, $cart_item_data);
    }

    public function maybe_setup_discounts($subscription, $order = null) {
        if (null === $order) {
            $order = $subscription;
        }

        if (hf_is_subscription($order) || hf_order_contains_renewal($order)) {

            $used_coupons = $order->get_used_coupons();
            $order_discount = hf_get_objects_property($order, 'cart_discount');

            if (!empty($used_coupons)) {
                $coupon_items = $order->get_items('coupon');

                foreach ($coupon_items as $coupon_item) {

                    $coupon = new WC_Coupon($coupon_item['name']);
                    $coupon_type = hf_get_coupon_property($coupon, 'discount_type');
                    $coupon_code = '';

                    if (true === hf_get_coupon_property($coupon, 'exists')) {

                        if (in_array($coupon_type, array('recurring_percent', 'recurring_fee'))) {

                            if ('recurring_percent' == $coupon_type) {
                                hf_set_coupon_property($coupon, 'discount_type', 'renewal_percent');
                            } elseif ('recurring_fee' == $coupon_type) {
                                hf_set_coupon_property($coupon, 'discount_type', 'renewal_fee');
                            }

                            $coupon_code = hf_get_coupon_property($coupon, 'code');
                        }
                    } else {
                        hf_set_coupon_property($coupon, 'discount_type', 'renewal_cart');

                        $coupon_code = hf_get_coupon_property($coupon, 'code');
                        $coupon_amount = is_callable(array($coupon_item, 'get_discount')) ? $coupon_item->get_discount() : $coupon_item['item_meta']['discount_amount']['0'];

                        hf_set_coupon_property($coupon, 'coupon_amount', $coupon_amount);
                    }

                    if (!empty($coupon_code)) {

                        if (!HF_Subscriptions::is_woocommerce_prior_to('2.5')) {
                            hf_set_coupon_property($coupon, 'product_ids', $this->get_products($order));
                        }

                        $this->store_coupon(hf_get_objects_property($order, 'id'), $coupon);

                        if (WC()->cart && !WC()->cart->has_discount($coupon_code)) {
                            WC()->cart->add_discount($coupon_code);
                        }
                    }
                }
            } elseif (!empty($order_discount)) {
                $coupon = new WC_Coupon('discount_renewal');

                hf_set_coupon_property($coupon, 'discount_type', 'renewal_cart');
                hf_set_coupon_property($coupon, 'coupon_amount', $order_discount);

                if (!HF_Subscriptions::is_woocommerce_prior_to('2.5')) {
                    hf_set_coupon_property($coupon, 'product_ids', $this->get_products($order));
                }

                $this->store_coupon(hf_get_objects_property($order, 'id'), $coupon);

                if (WC()->cart && !WC()->cart->has_discount('discount_renewal')) {
                    WC()->cart->add_discount('discount_renewal');
                }
            }
        }
    }

    public function cart_items_loaded_from_session($cart) {
        $removed_count_subscription = $removed_count_order = 0;

        foreach ($cart->cart_contents as $key => $item) {
            if (isset($item[$this->cart_item_key]['subscription_id']) && !hf_is_subscription($item[$this->cart_item_key]['subscription_id'])) {
                $cart->remove_cart_item($key);
                $removed_count_subscription++;
                continue;
            }

            if (isset($item[$this->cart_item_key]['renewal_order_id']) && !'shop_order' == get_post_type($item[$this->cart_item_key]['renewal_order_id'])) {
                $cart->remove_cart_item($key);
                $removed_count_order++;
                continue;
            }
        }

        if ($removed_count_subscription) {
            $error_message = esc_html(_n('We couldn\'t find the original subscription for an item in your cart. The item was removed.', 'We couldn\'t find the original subscriptions for items in your cart. The items were removed.', $removed_count_subscription, HF_Subscriptions::TEXT_DOMAIN));
            if (!wc_has_notice($error_message, 'notice')) {
                wc_add_notice($error_message, 'notice');
            }
        }

        if ($removed_count_order) {
            $error_message = esc_html(_n('We couldn\'t find the original renewal order for an item in your cart. The item was removed.', 'We couldn\'t find the original renewal orders for items in your cart. The items were removed.', $removed_count_order, HF_Subscriptions::TEXT_DOMAIN));
            if (!wc_has_notice($error_message, 'notice')) {
                wc_add_notice($error_message, 'notice');
            }
        }
    }

    public function get_cart_item_from_session($cart_item_session_data, $cart_item, $key) {

        if (isset($cart_item[$this->cart_item_key]['subscription_id'])) {
            $cart_item_session_data[$this->cart_item_key] = $cart_item[$this->cart_item_key];

            $_product = $cart_item_session_data['data'];

            $subscription = $this->get_order($cart_item);

            if ($subscription) {
                $subscription_items = $subscription->get_items();
                $item_to_renew = $subscription_items[$cart_item_session_data[$this->cart_item_key]['line_item_id']];

                $price = $item_to_renew['line_subtotal'];

                if (wc_prices_include_tax()) {

                    if (apply_filters('woocommerce_adjust_non_base_location_prices', true)) {
                        $base_tax_rates = WC_Tax::get_base_tax_rates(hf_get_objects_property($_product, 'tax_class'));
                    } else {
                        $base_tax_rates = WC_Tax::get_rates(hf_get_objects_property($_product, 'tax_class'));
                    }

                    $base_taxes_on_item = WC_Tax::calc_tax($price, $base_tax_rates, false, false);
                    $price += array_sum($base_taxes_on_item);
                }

                $_product->set_price($price / $item_to_renew['qty']);

                $line_item_name = is_callable($item_to_renew, 'get_name') ? $item_to_renew->get_name() : $item_to_renew['name'];
                hf_set_objects_property($_product, 'name', apply_filters('hf_subscription_renewal_product_title', $line_item_name, $_product), 'set_prop_only');

                $cart_item_session_data['quantity'] = $item_to_renew['qty'];
            }
        }

        return $cart_item_session_data;
    }

    public function checkout_get_value($value, $key) {

        if ($this->cart_contains() && did_action('woocommerce_checkout_init') > 0) {

            remove_filter('woocommerce_checkout_get_value', array(&$this, 'checkout_get_value'), 10, 2);

            if (is_callable(array(WC()->checkout(), 'get_checkout_fields'))) {
                $address_fields = array_merge(WC()->checkout()->get_checkout_fields('billing'), WC()->checkout()->get_checkout_fields('shipping'));
            } else {
                $address_fields = array_merge(WC()->checkout()->checkout_fields['billing'], WC()->checkout()->checkout_fields['shipping']);
            }

            add_filter('woocommerce_checkout_get_value', array(&$this, 'checkout_get_value'), 10, 2);

            if (array_key_exists($key, $address_fields) && false !== ( $item = $this->cart_contains() )) {

                $order = $this->get_order($item);

                if (( $order_value = hf_get_objects_property($order, $key))) {
                    $value = $order_value;
                }
            }
        }

        return $value;
    }

    public function maybe_check_ship_to_different_address($ship_to_different_address) {

        if (!$ship_to_different_address && false !== ( $item = $this->cart_contains() )) {

            $order = $this->get_order($item);

            $renewal_shipping_address = $order->get_address('shipping');
            $renewal_billing_address = $order->get_address('billing');

            if (isset($renewal_billing_address['email'])) {
                unset($renewal_billing_address['email']);
            }

            if (isset($renewal_billing_address['phone'])) {
                unset($renewal_billing_address['phone']);
            }

            if ($renewal_shipping_address != $renewal_billing_address) {
                $ship_to_different_address = 1;
            }
        }

        return $ship_to_different_address;
    }

    public function maybe_update_subscription_customer_data($update_customer_data, $checkout_object) {

        $cart_renewal_item = $this->cart_contains();

        if (false !== $cart_renewal_item) {

            $subscription = hf_get_subscription($cart_renewal_item[$this->cart_item_key]['subscription_id']);

            $billing_address = array();
            if ($checkout_object->checkout_fields['billing']) {
                foreach (array_keys($checkout_object->checkout_fields['billing']) as $field) {
                    $field_name = str_replace('billing_', '', $field);
                    $billing_address[$field_name] = $checkout_object->get_posted_address_data($field_name);
                }
            }

            $shipping_address = array();
            if ($checkout_object->checkout_fields['shipping']) {
                foreach (array_keys($checkout_object->checkout_fields['shipping']) as $field) {
                    $field_name = str_replace('shipping_', '', $field);
                    $shipping_address[$field_name] = $checkout_object->get_posted_address_data($field_name, 'shipping');
                }
            }

            $subscription->set_address($billing_address, 'billing');
            $subscription->set_address($shipping_address, 'shipping');
        }

        return $update_customer_data;
    }

    public function get_checkout_payment_url($pay_url, $order) {

        if (hf_order_contains_renewal($order)) {
            $pay_url = add_query_arg(array($this->cart_item_key => 'true'), $pay_url);
        }

        return $pay_url;
    }

    public function filter_my_account_my_orders_actions($actions, $order) {

        if (hf_order_contains_renewal($order)) {
            unset($actions['cancel']);
            $subscriptions = hf_get_subscriptions_for_renewal_order($order);
            foreach ($subscriptions as $subscription) {
                if (empty($subscription) || !$subscription->has_status(array('on-hold', 'pending'))) {
                    unset($actions['pay']);
                    break;
                }
            }
        }

        return $actions;
    }

    public function maybe_preserve_order_status($order_status) {

        if (null !== WC()->session && 'failed' !== $order_status) {

            $order_id = absint(WC()->session->order_awaiting_payment);
            remove_filter('woocommerce_default_order_status', array(&$this, __FUNCTION__), 10);

            if ($order_id > 0 && ( $order = wc_get_order($order_id) ) && hf_order_contains_renewal($order) && $order->has_status('failed')) {
                $order_status = 'failed';
            }

            add_filter('woocommerce_default_order_status', array(&$this, __FUNCTION__));
        }
        return $order_status;
    }

    public function maybe_remove_items($cart_item_key) {

        if (isset(WC()->cart->cart_contents[$cart_item_key][$this->cart_item_key]['subscription_id'])) {

            $removed_item_count = 0;
            $subscription_id = WC()->cart->cart_contents[$cart_item_key][$this->cart_item_key]['subscription_id'];

            foreach (WC()->cart->cart_contents as $key => $cart_item) {

                if (isset($cart_item[$this->cart_item_key]) && $subscription_id == $cart_item[$this->cart_item_key]['subscription_id']) {
                    WC()->cart->removed_cart_contents[$key] = WC()->cart->cart_contents[$key];
                    unset(WC()->cart->cart_contents[$key]);
                    $removed_item_count++;
                }
            }

            unset(WC()->session->order_awaiting_payment);
            $this->clear_coupons();

            if ($removed_item_count > 1 && 'woocommerce_before_cart_item_quantity_zero' == current_filter()) {
                wc_add_notice(esc_html__('All linked subscription items have been removed from the cart.', HF_Subscriptions::TEXT_DOMAIN), 'notice');
            }
        }
    }

    protected function cart_contains() {
        return hf_cart_contains_renewal();
    }

    public function items_removed_title($product_title, $cart_item) {

        if (isset($cart_item[$this->cart_item_key]['subscription_id'])) {
            $subscription = $this->get_order($cart_item);
            $product_title = ( count($subscription->get_items()) > 1 ) ? esc_html_x('All linked subscription items were', 'Used in WooCommerce by removed item notification: "_All linked subscription items were_ removed. Undo?" Filter for item title.', HF_Subscriptions::TEXT_DOMAIN) : $product_title;
        }
        return $product_title;
    }

    public function maybe_restore_items($cart_item_key) {

        if (isset(WC()->cart->cart_contents[$cart_item_key][$this->cart_item_key]['subscription_id'])) {
            $subscription_id = WC()->cart->cart_contents[$cart_item_key][$this->cart_item_key]['subscription_id'];
            foreach (WC()->cart->removed_cart_contents as $key => $cart_item) {
                if (isset($cart_item[$this->cart_item_key]) && $key != $cart_item_key && $cart_item[$this->cart_item_key]['subscription_id'] == $subscription_id) {
                    WC()->cart->cart_contents[$key] = WC()->cart->removed_cart_contents[$key];
                    unset(WC()->cart->removed_cart_contents[$key]);
                }
            }

            if (isset(WC()->cart->cart_contents[$cart_item_key][$this->cart_item_key]['renewal_order_id'])) {
                WC()->session->set('order_awaiting_payment', WC()->cart->cart_contents[$cart_item_key][$this->cart_item_key]['renewal_order_id']);
            }
        }
    }

    public function renewal_coupon_data($data, $code) {

        if (!is_object(WC()->session)) {
            return $data;
        }
        $renewal_coupons = WC()->session->get('hf_renewal_coupons');
        if (empty($renewal_coupons)) {
            return $data;
        }
        foreach ($renewal_coupons as $order_id => $coupons) {
            foreach ($coupons as $coupon_code => $coupon_properties) {

                if ($coupon_code == $code) {
                    $expiry_date_property = HF_Subscriptions::is_woocommerce_prior_to('3.0') ? 'expiry_date' : 'date_expires';

                    $renewal_coupon_overrides = array(
                        'id' => true,
                        'usage_limit' => '',
                        'usage_count' => '',
                        $expiry_date_property => '',
                    );

                    $data = array_merge($coupon_properties, $renewal_coupon_overrides);
                    break 2;
                }
            }
        }

        return $data;
    }

    protected function get_products($order) {

        $product_ids = array();

        if (is_a($order, 'WC_Abstract_Order')) {
            foreach ($order->get_items() as $item) {
                $product_id = hf_get_canonical_product_id($item);
                if (!empty($product_id)) {
                    $product_ids[] = $product_id;
                }
            }
        }

        return $product_ids;
    }

    protected function store_coupon($order_id, $coupon) {
        if (!empty($order_id) && !empty($coupon)) {
            $renewal_coupons = WC()->session->get('hf_renewal_coupons', array());
            $use_bools = HF_Subscriptions::is_woocommerce_prior_to('3.0');
            $coupon_properties = array();
            $property_defaults = array(
                'discount_type' => '',
                'amount' => 0,
                'individual_use' => ( $use_bools ) ? false : 'no',
                'product_ids' => array(),
                'excluded_product_ids' => array(),
                'free_shipping' => ( $use_bools ) ? false : 'no',
                'product_categories' => array(),
                'excluded_product_categories' => array(),
                'exclude_sale_items' => ( $use_bools ) ? false : 'no',
                'minimum_amount' => '',
                'maximum_amount' => '',
                'email_restrictions' => array(),
            );

            foreach ($property_defaults as $property => $value) {
                $getter = 'get_' . $property;

                if (is_callable(array($coupon, $getter))) {
                    $value = $coupon->$getter();
                } else {
                    $getter_to_property_map = array(
                        'amount' => 'coupon_amount',
                        'excluded_product_ids' => 'exclude_product_ids',
                        'date_expires' => 'expiry_date',
                        'excluded_product_categories' => 'exclude_product_categories',
                        'email_restrictions' => 'customer_email',
                    );

                    $property = array_key_exists($property, $getter_to_property_map) ? $getter_to_property_map[$property] : $property;

                    if (property_exists($coupon, $property)) {
                        $value = $coupon->$property;
                    }
                }

                $coupon_properties[$property] = $value;
            }

            if (array_key_exists($order_id, $renewal_coupons)) {
                $renewal_coupons[$order_id][hf_get_coupon_property($coupon, 'code')] = $coupon_properties;
            } else {
                $renewal_coupons[$order_id] = array(hf_get_coupon_property($coupon, 'code') => $coupon_properties);
            }
            WC()->session->set('hf_renewal_coupons', $renewal_coupons);
        }
    }

    public function clear_coupons() {

        $renewal_coupons = WC()->session->get('hf_renewal_coupons');

        if (!empty($renewal_coupons)) {
            foreach ($renewal_coupons as $order_id => $coupons) {
                foreach ($coupons as $coupon_code => $coupon_properties) {
                    WC()->cart->remove_coupons($coupon_code);
                }
            }
        }
        WC()->session->set('hf_renewal_coupons', array());
    }

    public function maybe_add_fees($cart) {

        if ($cart_item = $this->cart_contains()) {

            $order = $this->get_order($cart_item);

            do_action('woocommerce_adjust_order_fees_for_setup_cart_for_' . $this->cart_item_key, $order, $cart);

            if ($order instanceof WC_Order) {
                foreach ($order->get_fees() as $fee) {
                    $cart->add_fee($fee['name'], $fee['line_total'], abs($fee['line_tax']) > 0, $fee['tax_class']);
                }
            }
        }
    }

    public function product_addons_adjust_price($adjust_price, $cart_item) {

        if (true === $adjust_price && isset($cart_item[$this->cart_item_key])) {
            $adjust_price = false;
        }
        return $adjust_price;
    }

    protected function get_order($cart_item = '') {
        $order = false;
        if (empty($cart_item)) {
            $cart_item = $this->cart_contains();
        }
        if (false !== $cart_item && isset($cart_item[$this->cart_item_key])) {
            $order = wc_get_order($cart_item[$this->cart_item_key]['renewal_order_id']);
        }
        return $order;
    }

    protected function set_cart_hash($order_id) {
        $order = wc_get_order($order_id);
        hf_set_objects_property($order, 'cart_hash', md5(json_encode(wc_clean(WC()->cart->get_cart_for_session())) . WC()->cart->total));
    }

    public function set_renewal_order_cart_hash($order) {

        if ($item = hf_cart_contains_renewal()) {
            $this->set_cart_hash($item[$this->cart_item_key]['renewal_order_id']);
        }
        return $order;
    }

    function maybe_redirect_after_login($redirect) {
        if (isset($_GET['hf_redirect'], $_GET['hf_redirect_id']) && 'pay_for_order' == $_GET['hf_redirect']) {
            $order = wc_get_order($_GET['hf_redirect_id']);
            if ($order) {
                $redirect = $order->get_checkout_payment_url();
            }
        }
        return $redirect;
    }

    public function update_session_cart_after_updating_renewal_order() {

        if ($this->cart_contains()) {
            WC()->session->cart = WC()->cart->get_cart_for_session();
        }
    }

    public function add_line_item_meta($order_item, $cart_item_key, $cart_item) {
        if (isset($cart_item[$this->cart_item_key])) {
            $order_item->add_meta_data('_cart_item_key_' . $this->cart_item_key, $cart_item_key);
        }
    }

    public function set_order_item_id($order_id, $posted_checkout_data) {

        $order = wc_get_order($order_id);
        foreach ($order->get_items('line_item') as $order_item_id => $order_item) {
            $cart_item_key = $order_item->get_meta('_cart_item_key_' . $this->cart_item_key);
            if (!empty($cart_item_key)) {
                $this->set_cart_item_order_item_id($cart_item_key, $order_item_id);
            }
        }
    }

    protected function set_cart_item_order_item_id($cart_item_key, $order_item_id) {
        WC()->cart->cart_contents[$cart_item_key][$this->cart_item_key]['line_item_id'] = $order_item_id;
    }

    public function hidden_order_itemmeta($hidden_meta_keys) {

        if (apply_filters('hf_subscription_hide_itemmeta', !defined('HF_DEBUG') || true !== HF_DEBUG)) {
            $hidden_meta_keys[] = '_cart_item_key_' . $this->cart_item_key;
        }
        return $hidden_meta_keys;
    }

    public function maybe_update_subscription_address_data($customer_id, $checkout_data) {
        $cart_renewal_item = $this->cart_contains();

        if (false !== $cart_renewal_item) {
            $subscription = hf_get_subscription($cart_renewal_item[$this->cart_item_key]['subscription_id']);
            $billing_address = $shipping_address = array();

            foreach (array('billing', 'shipping') as $address_type) {
                $checkout_fields = WC()->checkout()->get_checkout_fields($address_type);

                if (is_array($checkout_fields)) {
                    foreach (array_keys($checkout_fields) as $field) {
                        if (isset($checkout_data[$field])) {
                            $field_name = str_replace($address_type . '_', '', $field);
                            ${$address_type . '_address'}[$field_name] = $checkout_data[$field];
                        }
                    }
                }
            }

            $subscription->set_address($billing_address, 'billing');
            $subscription->set_address($shipping_address, 'shipping');
        }
    }

    public function display_line_item_data_in_cart($cart_item_data, $cart_item) {

        if (!empty($cart_item[$this->cart_item_key]['custom_line_item_meta'])) {
            foreach ($cart_item[$this->cart_item_key]['custom_line_item_meta'] as $item_meta_key => $value) {

                $cart_item_data[] = array(
                    'key' => $item_meta_key,
                    'value' => $value,
                    'hidden' => substr($item_meta_key, 0, 1) === '_', // meta keys prefixed with an `_` are hidden by default
                );
            }
        }

        return $cart_item_data;
    }

    public function add_order_item_meta($item_id, $cart_item_data) {
        if (!empty($cart_item_data[$this->cart_item_key]['custom_line_item_meta'])) {
            foreach ($cart_item_data[$this->cart_item_key]['custom_line_item_meta'] as $meta_key => $value) {
                woocommerce_add_order_item_meta($item_id, $meta_key, $value);
            }
        }
    }

    public function add_order_line_item_meta($item, $cart_item_key, $cart_item_data) {
        if (!empty($cart_item_data[$this->cart_item_key]['custom_line_item_meta'])) {
            foreach ($cart_item_data[$this->cart_item_key]['custom_line_item_meta'] as $meta_key => $value) {
                $item->add_meta_data($meta_key, $value);
            }
        }
    }

    public function update_line_item_cart_data($item_id, $cart_item_data, $cart_item_key) {

        if (isset($cart_item_data[$this->cart_item_key])) {
            WC()->cart->cart_contents[$cart_item_key][$this->cart_item_key]['line_item_id'] = $item_id;
        }
    }

}

new HF_Cart_Renewal();