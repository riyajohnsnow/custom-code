<?php

if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscription_Cart {

    private static $calculation_type = 'none'; //none ,combined_total,recurring_total,free_trial_total
    private static $recurring_cart_key = 'none';
    private static $recurring_shipping_packages = array();
    private static $shipping_rates = array();
    private static $cached_recurring_cart = null;

    public function __construct() {

        add_action('woocommerce_before_calculate_totals', array($this, 'add_calculation_price_filter'), 10);
        add_action('woocommerce_calculate_totals',  array($this, 'remove_calculation_price_filter'), 10);
        add_action('woocommerce_after_calculate_totals',  array($this, 'remove_calculation_price_filter'), 10);
        add_filter('woocommerce_calculated_total',   array($this, 'calculate_subscription_totals'), 1000, 2);
        add_filter('woocommerce_cart_shipping_packages',  array($this, 'set_cart_shipping_packages'), -10, 1);
        add_filter('woocommerce_cart_product_subtotal', __CLASS__.'::get_formatted_product_subtotal', 11, 4);
        add_filter('woocommerce_cart_needs_payment',  array($this, 'cart_needs_payment'), 10, 2);
        add_filter('woocommerce_cart_product_price',  array($this, 'cart_product_price'), 10, 2);
        add_action('wc_ajax_get_refreshed_fragments',  array($this, 'pre_get_refreshed_fragments'), 1);
        add_action('wp_ajax_woocommerce_get_refreshed_fragments',  array($this, 'pre_get_refreshed_fragments'), 1);
        add_action('wp_ajax_nopriv_woocommerce_get_refreshed_fragments',  array($this, 'pre_get_refreshed_fragments'), 1, 1);
        add_action('woocommerce_ajax_added_to_cart',  array($this, 'pre_get_refreshed_fragments'), 1, 1);
        add_action('woocommerce_cart_totals_after_order_total',   array($this, 'display_recurring_totals'));
        add_action('woocommerce_review_order_after_order_total',  array($this, 'display_recurring_totals'));
        add_action('woocommerce_add_to_cart_validation',  array($this, 'check_valid_add_to_cart'), 10, 6);
        add_filter('woocommerce_cart_needs_shipping',  array($this, 'cart_needs_shipping'), 11, 1);
        add_action('woocommerce_remove_cart_item',   array($this, 'maybe_reset_chosen_shipping_methods'));
        add_action('woocommerce_before_cart_item_quantity_zero', array($this, 'maybe_reset_chosen_shipping_methods'));
        add_action('woocommerce_checkout_update_order_review', array( $this, 'add_shipping_method_post_data'));
        add_filter('woocommerce_shipping_chosen_method', array( $this, 'set_chosen_shipping_method'), 10, 2);
        add_filter('woocommerce_package_rates', array( $this, 'cache_package_rates'), 1, 2);
        add_filter('woocommerce_shipping_packages',  array( $this, 'reset_shipping_method_counts'), 1000, 1);
        add_filter('woocommerce_local_pickup_methods',  array( $this, 'filter_recurring_cart_chosen_shipping_method'), 100, 1);
        add_filter('wc_shipping_local_pickup_plus_chosen_shipping_methods', array( $this, 'filter_recurring_cart_chosen_shipping_method'), 10, 1);
        add_action('woocommerce_after_checkout_validation',  array( $this, 'validate_recurring_shipping_methods'));
        add_filter('woocommerce_shipping_free_shipping_is_available',  array( $this, 'maybe_recalculate_shipping_method_availability'), 10, 2);
        add_filter('woocommerce_add_to_cart_handler',   array( $this, 'add_to_cart_handler'), 10, 2);
    }

    public function add_calculation_price_filter() {

        WC()->cart->recurring_carts = array();
        if (!self::cart_contains_subscription()) {
            return;
        }
        if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            add_filter('woocommerce_get_price', __CLASS__ . '::set_subscription_prices_for_calculation', 100, 2);
        } else {
            add_filter('woocommerce_product_get_price', __CLASS__ . '::set_subscription_prices_for_calculation', 100, 2);
            add_filter('woocommerce_product_variation_get_price', __CLASS__ . '::set_subscription_prices_for_calculation', 100, 2);
        }
    }

    public function remove_calculation_price_filter() {
        
        if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            remove_filter('woocommerce_get_price', __CLASS__ . '::set_subscription_prices_for_calculation', 100);
        } else {
            remove_filter('woocommerce_product_get_price', __CLASS__ . '::set_subscription_prices_for_calculation', 100);
            remove_filter('woocommerce_product_variation_get_price', __CLASS__ . '::set_subscription_prices_for_calculation', 100);
        }
    }

    public function add_to_cart_handler($handler, $product) {

        if (HF_Subscriptions_Product::is_subscription($product)) {
            switch ($handler) {
                case 'variable-subscription' :
                    $handler = 'variable';
                    break;
                case 'subscription' :
                    $handler = 'simple';
                    break;
            }
        }
        return $handler;
    }

    public static function set_subscription_prices_for_calculation($price, $product) {

        if (HF_Subscriptions_Product::is_subscription($product)) {
            $price = apply_filters('hf_subscription_cart_get_price', $price, $product);
        } elseif ('recurring_total' == self::$calculation_type) {
            $price = 0;
        }
        return $price;
    }

    public function calculate_subscription_totals($total, $cart) {

        if (!self::cart_contains_subscription() && !hf_cart_contains_resubscribe()) {
            return $total;
        } elseif ('none' != self::$calculation_type) {
            return $total;
        }

        WC()->cart->total = ( $total < 0 ) ? 0 : $total;
        do_action('hf_subscription_cart_before_grouping');
        $subscription_groups = array();

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (HF_Subscriptions_Product::is_subscription($cart_item['data'])) {
                $subscription_groups[self::get_recurring_cart_key($cart_item)][] = $cart_item_key;
            }
        }

        do_action('hf_subscription_cart_after_grouping');
        $recurring_carts = array();
        WC()->session->set('hf_shipping_methods', WC()->session->get('chosen_shipping_methods', array()));
        self::$calculation_type = 'recurring_total';

        foreach ($subscription_groups as $recurring_cart_key => $subscription_group) {

            $recurring_cart = clone WC()->cart;
            $product = null;
            self::$recurring_cart_key = $recurring_cart->recurring_cart_key = $recurring_cart_key;
            foreach ($recurring_cart->get_cart() as $cart_item_key => $cart_item) {
                if (!in_array($cart_item_key, $subscription_group)) {
                    unset($recurring_cart->cart_contents[$cart_item_key]);
                    continue;
                }
                if (null === $product) {
                    $product = $cart_item['data'];
                }
            }

            $recurring_cart->start_date = apply_filters('hf_recurring_cart_start_date', gmdate('Y-m-d H:i:s'), $recurring_cart);
            $recurring_cart->next_payment_date = apply_filters('hf_recurring_cart_next_payment_date', HF_Subscriptions_Product::get_first_renewal_payment_date($product, $recurring_cart->start_date), $recurring_cart, $product);
            $recurring_cart->end_date = apply_filters('hf_recurring_cart_end_date', HF_Subscriptions_Product::get_expiration_date($product, $recurring_cart->start_date), $recurring_cart, $product);
            self::$cached_recurring_cart = $recurring_cart;
            
            if (HF_Subscriptions::is_woocommerce_prior_to('3.2')) {
            $recurring_cart->fees = array();
            }else{
                $recurring_cart->add_fee(array(), array());
            }
            
            $recurring_cart->fee_total = 0;
            WC()->shipping->reset_shipping();
            self::maybe_restore_shipping_methods();
            $recurring_cart->calculate_totals();
            $recurring_carts[$recurring_cart_key] = clone $recurring_cart;
            $recurring_carts[$recurring_cart_key]->removed_cart_contents = array();
            $recurring_carts[$recurring_cart_key]->cart_session_data = array();
            self::$recurring_shipping_packages[$recurring_cart_key] = WC()->shipping->get_packages();
        }

        self::$calculation_type = self::$recurring_cart_key = 'none';

        WC()->shipping->reset_shipping();
        self::maybe_restore_shipping_methods();
        WC()->cart->calculate_shipping();

        unset(WC()->session->hf_shipping_methods);

        WC()->cart->recurring_carts = $recurring_carts;

        $total = max(0, round(WC()->cart->cart_contents_total + WC()->cart->tax_total + WC()->cart->shipping_tax_total + WC()->cart->shipping_total + WC()->cart->fee_total, WC()->cart->dp));

        if (!self::charge_shipping_up_front()) {
            $total = max(0, $total - WC()->cart->shipping_tax_total - WC()->cart->shipping_total);
            WC()->cart->shipping_taxes = array();
            WC()->cart->shipping_tax_total = 0;
            WC()->cart->shipping_total = 0;
        }

        return apply_filters('hf_subscription_calculated_total', $total);
    }

    public static function charge_shipping_up_front() {

        $charge_shipping_up_front = true;

        return apply_filters('hf_subscription_cart_shipping_up_front', $charge_shipping_up_front);
    }

    public function cart_needs_shipping($needs_shipping) {

        if (self::cart_contains_subscription()) {
            if ('none' == self::$calculation_type) {
                if (true == $needs_shipping && !self::charge_shipping_up_front() && !self::cart_contains_subscriptions_needing_shipping()) {
                    $needs_shipping = false;
                } elseif (false == $needs_shipping && ( self::charge_shipping_up_front() || self::cart_contains_subscriptions_needing_shipping() )) {
                    $needs_shipping = false;
                }
            } elseif ('recurring_total' == self::$calculation_type) {
                if (true == $needs_shipping && !self::cart_contains_subscriptions_needing_shipping()) {
                    $needs_shipping = false;
                } elseif (false == $needs_shipping && self::cart_contains_subscriptions_needing_shipping()) {
                    $needs_shipping = true;
                }
            }
        }

        return $needs_shipping;
    }

    public function maybe_reset_chosen_shipping_methods($cart_item_key) {

        if (isset(WC()->cart->cart_contents[$cart_item_key])) {

            $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
            foreach ($chosen_methods as $key => $methods) {
                if (!is_numeric($key)) {
                    unset($chosen_methods[$key]);
                }
            }

            WC()->session->set('chosen_shipping_methods', $chosen_methods);
        }
    }

    public function add_shipping_method_post_data() {

        if (!HF_Subscriptions::is_woocommerce_prior_to('2.6')) {
            return;
        }
        check_ajax_referer('update-order-review', 'security');
        parse_str($_POST['post_data'], $form_data);

        if (!isset($_POST['shipping_method'])) {
            $_POST['shipping_method'] = array();
        }
        if (!isset($form_data['shipping_method'])) {
            $form_data['shipping_method'] = array();
        }

        foreach ($form_data['shipping_method'] as $key => $methods) {
            if (!is_numeric($key) && !array_key_exists($key, $_POST['shipping_method'])) {
                $_POST['shipping_method'][$key] = $methods;
            }
        }
    }

    public function reset_shipping_method_counts($packages) {

        if ('none' !== self::$recurring_cart_key) {
            WC()->session->set('shipping_method_counts', array());
        }
        return $packages;
    }

    public function set_chosen_shipping_method($default_method, $available_methods, $package_index = 0) {

        $chosen_methods = WC()->session->get('chosen_shipping_methods', array());
        $recurring_cart_package_key = self::get_recurring_shipping_package_key(self::$recurring_cart_key, $package_index);
        if ('none' !== self::$recurring_cart_key && isset($chosen_methods[$recurring_cart_package_key]) && isset($available_methods[$chosen_methods[$recurring_cart_package_key]])) {
            $default_method = $chosen_methods[$recurring_cart_package_key];
        } elseif (isset($chosen_methods[$package_index]) && $default_method !== $chosen_methods[$package_index] && isset($available_methods[$chosen_methods[$package_index]])) {
            $default_method = $chosen_methods[$package_index];
        }
        return $default_method;
    }

    public static function get_recurring_shipping_package_key($recurring_cart_key, $package_index) {
        return $recurring_cart_key . '_' . $package_index;
    }

    public static function set_global_recurring_shipping_packages() {
        foreach (self::$recurring_shipping_packages as $recurring_cart_key => $packages) {
            foreach ($packages as $package_index => $package) {
                WC()->shipping->packages[self::get_recurring_shipping_package_key($recurring_cart_key, $package_index)] = $package;
            }
        }
    }

    public static function cart_contains_subscriptions_needing_shipping() {

        if ('no' === get_option('woocommerce_calc_shipping')) {
            return false;
        }

        $cart_contains_subscriptions_needing_shipping = false;

        if (self::cart_contains_subscription()) {
            foreach (WC()->cart->cart_contents as $cart_item_key => $values) {
                $_product = $values['data'];
                if (HF_Subscriptions_Product::is_subscription($_product) && $_product->needs_shipping() && false === HF_Subscriptions_Product::needs_one_time_shipping($_product)) {
                    $cart_contains_subscriptions_needing_shipping = true;
                }
            }
        }

        return apply_filters('woocommerce_cart_contains_subscriptions_needing_shipping', $cart_contains_subscriptions_needing_shipping);
    }

    public function set_cart_shipping_packages($packages) {

        if (self::cart_contains_subscription()) {
            if ('none' == self::$calculation_type) {
                foreach ($packages as $index => $package) {

                    if (empty($packages[$index]['contents'])) {
                        unset($packages[$index]);
                    }
                }
            } elseif ('recurring_total' == self::$calculation_type) {
                foreach ($packages as $index => $package) {
                    foreach ($package['contents'] as $cart_item_key => $cart_item) {
                        if (HF_Subscriptions_Product::needs_one_time_shipping($cart_item['data'])) {
                            $packages[$index]['contents_cost'] -= $cart_item['line_total'];
                            unset($packages[$index]['contents'][$cart_item_key]);
                        }
                    }

                    if (empty($packages[$index]['contents'])) {
                        unset($packages[$index]);
                    } else {
                        $packages[$index]['recurring_cart_key'] = self::$recurring_cart_key;
                    }
                }
            }
        }

        return $packages;
    }

    public static function get_formatted_product_subtotal($product_subtotal, $product, $quantity, $cart) {

        if (HF_Subscriptions_Product::is_subscription($product) && !hf_cart_contains_renewal()) {

            if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
                $product_price_filter = 'woocommerce_get_price';
            } else {
                $product_price_filter = is_a($product, 'WC_Product_Variation') ? 'woocommerce_product_variation_get_price' : 'woocommerce_product_get_price';
            }
            $product_subtotal = HF_Subscriptions_Product::get_price_string($product, array(
                        'price' => $product_subtotal,
                        'tax_calculation' => WC()->cart->tax_display_cart,
                            )
            );

            if (false !== strpos($product_subtotal, WC()->countries->inc_tax_or_vat())) {
                $product_subtotal = str_replace(WC()->countries->inc_tax_or_vat(), '', $product_subtotal) . ' <small class="tax_label">' . WC()->countries->inc_tax_or_vat() . '</small>';
            }
            if (false !== strpos($product_subtotal, WC()->countries->ex_tax_or_vat())) {
                $product_subtotal = str_replace(WC()->countries->ex_tax_or_vat(), '', $product_subtotal) . ' <small class="tax_label">' . WC()->countries->ex_tax_or_vat() . '</small>';
            }

            $product_subtotal = '<span class="subscription-price">' . $product_subtotal . '</span>';
        }

        return $product_subtotal;
    }

    public static function cart_contains_subscription() {

        $contains_subscription = false;

        if (!empty(WC()->cart->cart_contents) && !hf_cart_contains_renewal()) {
            foreach (WC()->cart->cart_contents as $cart_item) {
                if (HF_Subscriptions_Product::is_subscription($cart_item['data'])) {
                    $contains_subscription = true;
                    break;
                }
            }
        }
        return $contains_subscription;
    }

    public static function cart_contains_free_trial() {

        $cart_contains_free_trial = false;
        return $cart_contains_free_trial;
    }

    public static function get_calculation_type() {
        return self::$calculation_type;
    }

    public static function set_calculation_type($calculation_type) {

        self::$calculation_type = $calculation_type;
        return $calculation_type;
    }



    public function cart_needs_payment($needs_payment, $cart) {

        if (false === $needs_payment && self::cart_contains_subscription() && $cart->total == 0 ) {

            $recurring_total = 0;
            $is_one_period = true;
            $is_synced = false;

            foreach (WC()->cart->recurring_carts as $cart) {

                $recurring_total += $cart->total;

                $cart_length = hf_cart_pluck($cart, 'subscription_length');

                if (0 == $cart_length || hf_cart_pluck($cart, 'subscription_period_interval') != $cart_length) {
                    $is_one_period = false;
                }

                $is_synced = ( $is_synced  ) ? true : false;
            }

            $has_trial = self::cart_contains_free_trial();

            if ($recurring_total > 0 && ( false === $is_one_period || true === $has_trial || ( false !== $is_synced ) )) {
                $needs_payment = true;
            }
        }

        return $needs_payment;
    }

    private static function maybe_restore_shipping_methods() {
        if (!empty($_POST['calc_shipping']) && wp_verify_nonce($_POST['_wpnonce'], 'woocommerce-cart') && function_exists('WC')) {

            try {
                WC()->shipping->reset_shipping();

                $country = wc_clean($_POST['calc_shipping_country']);
                $state = isset($_POST['calc_shipping_state']) ? wc_clean($_POST['calc_shipping_state']) : '';
                $postcode = apply_filters('woocommerce_shipping_calculator_enable_postcode', true) ? wc_clean($_POST['calc_shipping_postcode']) : '';
                $city = apply_filters('woocommerce_shipping_calculator_enable_city', false) ? wc_clean($_POST['calc_shipping_city']) : '';

                if ($postcode && !WC_Validation::is_postcode($postcode, $country)) {
                    throw new Exception(__('Please enter a valid postcode/ZIP.', HF_Subscriptions::TEXT_DOMAIN));
                } elseif ($postcode) {
                    $postcode = wc_format_postcode($postcode, $country);
                }

                if ($country) {
                    WC()->customer->set_location($country, $state, $postcode, $city);
                    WC()->customer->set_shipping_location($country, $state, $postcode, $city);
                } else {
                    WC()->customer->set_to_base();
                    WC()->customer->set_shipping_to_base();
                }

                WC()->customer->calculated_shipping(true);

                do_action('woocommerce_calculated_shipping');
            } catch (Exception $e) {
                if (!empty($e)) {
                    wc_add_notice($e->getMessage(), 'error');
                }
            }
        }

        self::maybe_restore_chosen_shipping_method();

        if (isset($_POST['shipping_method']) && is_array($_POST['shipping_method'])) {
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods', array());
            foreach ($_POST['shipping_method'] as $i => $value) {
                $chosen_shipping_methods[$i] = wc_clean($value);
            }
            WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);
        }
    }

    public function cart_product_price($price, $product) {

        if (HF_Subscriptions_Product::is_subscription($product)) {
            $price = HF_Subscriptions_Product::get_price_string($product, array('price' => $price, 'tax_calculation' => WC()->cart->tax_display_cart));
        }

        return $price;
    }

    public function pre_get_refreshed_fragments() {
        
        if (defined('DOING_AJAX') && true === DOING_AJAX && !defined('WOOCOMMERCE_CART')) {
            define('WOOCOMMERCE_CART', true);
            WC()->cart->calculate_totals();
        }
    }

    public function display_recurring_totals() {

        if (self::cart_contains_subscription()) {

            self::$calculation_type = 'recurring_total';
            $shipping_methods = array();
            $carts_with_multiple_payments = 0;
            foreach (WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart) {
                if (0 != $recurring_cart->next_payment_date) {
                    $carts_with_multiple_payments++;
                }
            }
            if ($carts_with_multiple_payments >= 1) {
                wc_get_template('checkout/recurring-totals.php', array('shipping_methods' => $shipping_methods, 'recurring_carts' => WC()->cart->recurring_carts, 'carts_with_multiple_payments' => $carts_with_multiple_payments), '', plugin_dir_path(HF_Subscriptions::PLUGN_BASE_PATH) . 'templates/');
            }
            self::$calculation_type = 'none';
        }
    }

    public static function get_recurring_cart_key($cart_item, $renewal_time = '') {

        $cart_key = '';
        $product = $cart_item['data'];
        $product_id = hf_get_canonical_product_id($product);
        $renewal_time = !empty($renewal_time) ? $renewal_time : HF_Subscriptions_Product::get_first_renewal_payment_time($product_id);
        $interval = HF_Subscriptions_Product::get_interval($product);
        $period = HF_Subscriptions_Product::get_period($product);
        $length = HF_Subscriptions_Product::get_length($product);

        if ($renewal_time > 0) {
            $cart_key .= gmdate('Y_m_d_', $renewal_time);
        }

        switch ($interval) {
            case 1 :
                if ('day' == $period) {
                    $cart_key .= 'daily';
                } else {
                    $cart_key .= sprintf('%sly', $period);
                }
                break;
            case 2 :
                $cart_key .= sprintf('every_2nd_%s', $period);
                break;
            case 3 :
                $cart_key .= sprintf('every_3rd_%s', $period);
                break;
            default:
                $cart_key .= sprintf('every_%dth_%s', $interval, $period);
                break;
        }

        if ($length > 0) {
            $cart_key .= '_for_';
            $cart_key .= sprintf('%d_%s', $length, $period);
            if ($length > 1) {
                $cart_key .= 's';
            }
        }

        return apply_filters('hf_subscription_recurring_cart_key', $cart_key, $cart_item);
    }

    public function check_valid_add_to_cart($is_valid, $product_id, $quantity, $variation_id = '', $variations = array(), $item_data = array()) {

        if ($is_valid && !isset($item_data['subscription_renewal']) && hf_cart_contains_renewal() && HF_Subscriptions_Product::is_subscription($product_id)) {
            wc_add_notice(__('That subscription product can not be added to your cart as it already contains a subscription renewal.', HF_Subscriptions::TEXT_DOMAIN), 'error');
            $is_valid = false;
        }

        return $is_valid;
    }

    public function filter_recurring_cart_chosen_shipping_method($shipping_methods) {

        if ('recurring_total' == self::$calculation_type && 'none' !== self::$recurring_cart_key) {
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods', array());
            $standard_package_methods = array();
            $recurring_cart_shipping_methods = array();
            foreach ($chosen_shipping_methods as $key => $method) {

                if (is_numeric($key)) {
                    $standard_package_methods[$key] = $method;
                } else if (strpos($key, self::$recurring_cart_key) !== false) {

                    $recurring_cart_shipping_methods[$key] = $method;
                }
            }

            $applicable_chosen_shipping_methods = ( empty($recurring_cart_shipping_methods) ) ? $standard_package_methods : $recurring_cart_shipping_methods;
            $shipping_methods = array_intersect($applicable_chosen_shipping_methods, $shipping_methods);
        }
        return $shipping_methods;
    }

    public static function validate_recurring_shipping_methods() {

        $shipping_methods = WC()->checkout()->shipping_methods;
        $added_invalid_notice = false;
        $standard_packages = WC()->shipping->get_packages();

        $calculation_type = self::$calculation_type;
        self::$calculation_type = 'recurring_total';
        $recurring_cart_key_flag = self::$recurring_cart_key;

        foreach (WC()->cart->recurring_carts as $recurring_cart_key => $recurring_cart) {

            if (false === $recurring_cart->needs_shipping() || 0 == $recurring_cart->next_payment_date) {
                continue;
            }

            self::$recurring_cart_key = $recurring_cart_key;
            $packages = $recurring_cart->get_shipping_packages();
            foreach ($packages as $package_index => $base_package) {
                $package = self::get_calculated_shipping_for_package($base_package);

                if (( isset($standard_packages[$package_index]) && $package['rates'] == $standard_packages[$package_index]['rates'] ) && apply_filters('hf_cart_totals_shipping_html_price_only', true, $package, WC()->cart->recurring_carts[$recurring_cart_key])) {
                    continue;
                }

                $recurring_shipping_package_key = HF_Subscription_Cart::get_recurring_shipping_package_key($recurring_cart_key, $package_index);

                if (!isset($package['rates'][$shipping_methods[$recurring_shipping_package_key]])) {

                    if (!$added_invalid_notice) {
                        wc_add_notice(__('Invalid recurring shipping method.', HF_Subscriptions::TEXT_DOMAIN), 'error');
                        $added_invalid_notice = true;
                    }

                    WC()->checkout()->shipping_methods[$recurring_shipping_package_key] = '';
                }
            }
        }

        self::$calculation_type = $calculation_type;
        self::$recurring_cart_key = $recurring_cart_key_flag;
    }

    public static function cart_contains_product($product_id) {

        $cart_contains_product = false;

        if (!empty(WC()->cart->cart_contents)) {
            foreach (WC()->cart->cart_contents as $cart_item) {
                if (hf_get_canonical_product_id($cart_item) == $product_id) {
                    $cart_contains_product = true;
                    break;
                }
            }
        }

        return $cart_contains_product;
    }

    public function cache_package_rates($rates, $package) {
        self::$shipping_rates[self::get_package_shipping_rates_cache_key($package)] = $rates;
        return $rates;
    }

    public static function get_calculated_shipping_for_package($package) {
        $key = self::get_package_shipping_rates_cache_key($package);

        if (isset(self::$shipping_rates[$key])) {
            $package['rates'] = apply_filters('woocommerce_package_rates', self::$shipping_rates[$key], $package);
        } else {
            $package = WC()->shipping->calculate_shipping_for_package($package);
        }

        return $package;
    }

    private static function get_package_shipping_rates_cache_key($package) {
        return md5(json_encode(array(array_keys($package['contents']), $package['contents_cost'], $package['applied_coupons'])));
    }

    public function maybe_recalculate_shipping_method_availability($is_available, $package) {

        if (isset($package['recurring_cart_key']) && isset(self::$cached_recurring_cart) && $package['recurring_cart_key'] == self::$cached_recurring_cart->recurring_cart_key) {

            $global_cart = WC()->cart;
            WC()->cart = self::$cached_recurring_cart;

            foreach (WC()->shipping->get_shipping_methods() as $shipping_method) {
                if ($shipping_method->id == 'free_shipping') {
                    remove_filter('woocommerce_shipping_free_shipping_is_available', __METHOD__);
                    $is_available = $shipping_method->is_available($package);
                    add_filter('woocommerce_shipping_free_shipping_is_available', __METHOD__, 10, 2);
                    break;
                }
            }

            WC()->cart = $global_cart;
        }

        return $is_available;
    }

    public static function maybe_restore_chosen_shipping_method() {
        $chosen_shipping_method_cache = WC()->session->get('hf_shipping_methods', false);
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods', array());

        if (false !== $chosen_shipping_method_cache && empty($chosen_shipping_methods)) {
            WC()->session->set('chosen_shipping_methods', $chosen_shipping_method_cache);
        }
    }

}

new HF_Subscription_Cart();