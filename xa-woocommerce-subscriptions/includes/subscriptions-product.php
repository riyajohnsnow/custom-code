<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscriptions_Product {

    protected static $subscription_meta_fields = array(
        '_hf_subscription_price',
        '_subscription_period',
        '_subscription_period_interval',
        '_subscription_length',
    );

    public function __construct() {

        
        add_filter('woocommerce_product_class', array($this, 'set_subscription_variation_class'), 10, 4);
        add_filter('woocommerce_available_variation', array($this, 'maybe_set_variations_price_html'), 10, 3);
        add_action('woocommerce_variable_product_sync_data', array($this, 'variable_subscription_product_sync'), 10);
        add_filter('woocommerce_grouped_price_html', array($this, 'get_grouped_price_html'), 10, 2);
        add_filter('user_has_cap', array($this, 'user_do_not_allow_delete_subscription'), 10, 3);
        add_filter('post_row_actions', array($this, 'subscription_row_actions'), 10, 2);
        add_filter('bulk_actions-edit-product', array($this, 'subscription_bulk_actions_unset_delete'), 10);
        add_action('wp_scheduled_delete', array($this, 'prevent_scheduled_deletion'), 9);
        add_action('wp_ajax_woocommerce_remove_variation',array($this, 'remove_variations'), 9, 2);
        add_action('wp_ajax_woocommerce_remove_variations', array($this, 'remove_variations'), 9, 2);
        add_action('woocommerce_bulk_edit_variations', array($this, 'bulk_edit_variations'), 10, 4);
    }


    public static function add_to_cart_text($button_text, $product_type = '') {
        global $product;

        if (self::is_subscription($product) || in_array($product_type, array('subscription', 'subscription-variation'))) {
            $button_text = get_option(HF_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text', __('Subscribe', HF_Subscriptions::TEXT_DOMAIN));
            if(empty($button_text)){
                $button_text = __('Subscribe', HF_Subscriptions::TEXT_DOMAIN);
            }
        }

        return $button_text;
    }

    public static function is_subscription($product) {

        $is_subscription = $product_id = false;

        $product = self::maybe_get_product_instance($product);

        if (is_object($product)) {

            $product_id = $product->get_id();

            if ($product->is_type(array('wcpb','subscription', 'subscription_variation', 'variable-subscription'))) {
                $is_subscription = true;
            }
        }

        return apply_filters('woocommerce_is_subscription', $is_subscription, $product_id, $product);
    }

    public function get_grouped_price_html($price, $grouped_product) {

        $child_prices = array();
        $contains_subscription = false;

        foreach ($grouped_product->get_children() as $child_product_id) {

            if (self::is_subscription($child_product_id)) {

                $contains_subscription = true;

                $child_product = wc_get_product($child_product_id);

                $tax_display_mode = get_option('woocommerce_tax_display_shop');
                $child_price = 'incl' == $tax_display_mode ? hf_get_price_including_tax($child_product, array('price' => $child_product->get_price())) : hf_get_price_excluding_tax($child_product, array('price' => $child_product->get_price()));


                $child_prices[] = $child_price;
            } else {

                $child_prices[] = get_post_meta($child_product_id, '_price', true);
            }
        }

        if (!$contains_subscription) {
            return $price;
        } else {
            $price = '';
        }

        $child_prices = array_unique($child_prices);

        if (!empty($child_prices)) {
            $min_price = min($child_prices);
        } else {
            $min_price = '';
        }

        if (sizeof($child_prices) > 1) {
            $price .= hf_get_price_html_from_text($grouped_product);
        }

        $price .= wc_price($min_price);

        return $price;
    }

    public static function get_price_string($product, $include = array()) {
        global $wp_locale;

        $product = self::maybe_get_product_instance($product);

        if (!self::is_subscription($product)) {
            return;
        }

        $include = wp_parse_args($include, array(
            'tax_calculation' => get_option('woocommerce_tax_display_shop'),
            'subscription_price' => true,
            'subscription_period' => true,
            'subscription_length' => true,
            )
        );

        $include = apply_filters('hf_subscription_product_price_string_inclusions', $include, $product);

        $base_price = self::get_price($product);


        if (false != $include['tax_calculation']) {

            if (in_array($include['tax_calculation'], array('exclude_tax', 'excl'))) { // Subtract Tax
                if (isset($include['price'])) {
                    $price = $include['price'];
                } else {
                    $price = hf_get_price_excluding_tax($product, array('price' => $include['price']));
                }
            } else { 
                if (isset($include['price'])) {
                    $price = $include['price'];
                } else {
                    $price = hf_get_price_including_tax($product);
                }
            }
        } else {

            if (isset($include['price'])) {
                $price = $include['price'];
            } else {
                $price = wc_price($base_price);
            }
        }

        $price .= ' <span class="subscription-details">';

        $billing_interval = self::get_interval($product);
        $billing_period = self::get_period($product);
        $subscription_length = self::get_length($product);

        if ($include['subscription_length']) {
            $ranges = hf_get_subscription_ranges($billing_period);
        }

        if ($include['subscription_length'] && 0 != $subscription_length) {
            $include_length = true;
        } else {
            $include_length = false;
        }

        $subscription_string = '';

        if ($include['subscription_price'] && $include['subscription_period']) {
            if ($include_length && $subscription_length == $billing_interval) {
                $subscription_string = $price;
            } else {
                $subscription_string = sprintf(_n('%1$s / %2$s', ' %1$s every %2$s', $billing_interval, HF_Subscriptions::TEXT_DOMAIN), $price, hf_get_subscription_period_strings($billing_interval, $billing_period));
            }
        } elseif ($include['subscription_price']) {
            $subscription_string = $price;
        } elseif ($include['subscription_period']) {
            $subscription_string = sprintf(__('every %s', HF_Subscriptions::TEXT_DOMAIN), hf_get_subscription_period_strings($billing_interval, $billing_period));
        }

        if ($include_length) {
            $subscription_string = sprintf(__('%1$s for %2$s', HF_Subscriptions::TEXT_DOMAIN), $subscription_string, $ranges[$subscription_length]);
        }


        $subscription_string .= '</span>';

        return apply_filters('hf_subscription_product_price_string', $subscription_string, $product, $include);
    }

    // returns the active price per period for a product if it is a subscription.

    public static function get_price($product) {

        $product = self::maybe_get_product_instance($product);

        $subscription_price = self::get_meta_data($product, 'subscription_price', 0);
        $sale_price = self::get_sale_price($product);
        $active_price = ( $subscription_price ) ? $subscription_price : self::get_regular_price($product);

        if ($product->is_on_sale() && $subscription_price > $sale_price) {
            $active_price = $sale_price;
        }

        return apply_filters('hf_subscription_product_price', $active_price, $product);
    }

    public static function get_regular_price($product, $context = 'view') {

        if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            $regular_price = $product->regular_price;
        } else {
            $regular_price = $product->get_regular_price($context);
        }

        return apply_filters('hf_subscription_product_regular_price', $regular_price, $product);
    }

    public static function get_sale_price($product, $context = 'view') {

        if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            $sale_price = $product->sale_price;
        } else {
            $sale_price = $product->get_sale_price($context);
        }
        return apply_filters('hf_subscription_product_sale_price', $sale_price, $product);
    }

    public static function get_period($product) {
        return apply_filters('hf_subscription_product_period', self::get_meta_data($product, 'subscription_period', ''), self::maybe_get_product_instance($product));
    }

    public static function get_interval($product) {
        return apply_filters('hf_subscription_product_period_interval', self::get_meta_data($product, 'subscription_period_interval', 1, 'use_default_value'), self::maybe_get_product_instance($product));
    }

    public static function get_length($product) {
        return apply_filters('hf_subscription_product_length', self::get_meta_data($product, 'subscription_length', 0, 'use_default_value'), self::maybe_get_product_instance($product));
    }

    public static function get_first_renewal_payment_date($product_id, $from_date = '', $timezone = 'gmt') {

        $first_renewal_timestamp = self::get_first_renewal_payment_time($product_id, $from_date, $timezone);

        if ($first_renewal_timestamp > 0) {
            $first_renewal_date = gmdate('Y-m-d H:i:s', $first_renewal_timestamp);
        } else {
            $first_renewal_date = 0;
        }
        return apply_filters('hf_subscription_product_first_renewal_payment_date', $first_renewal_date, $product_id, $from_date, $timezone);
    }

    public static function get_first_renewal_payment_time($product_id, $from_date = '', $timezone = 'gmt') {

        if (!self::is_subscription($product_id)) {
            return 0;
        }

        $from_date_param = $from_date;
        $billing_interval = self::get_interval($product_id);
        $billing_length = self::get_length($product_id);

        if ($billing_interval !== $billing_length) {

            if (empty($from_date)) {
                $from_date = gmdate('Y-m-d H:i:s');
            }


                $first_renewal_timestamp = hf_add_time($billing_interval, self::get_period($product_id), hf_date_to_time($from_date));

                if ('site' == $timezone) {
                    $first_renewal_timestamp += ( get_option('gmt_offset') * HOUR_IN_SECONDS );
                }
            
        } else {
            $first_renewal_timestamp = 0;
        }
        return apply_filters('hf_subscription_product_first_renewal_payment_time', $first_renewal_timestamp, $product_id, $from_date_param, $timezone);
    }

    public static function get_expiration_date($product_id, $from_date = '') {

        $subscription_length = self::get_length($product_id);

        if ($subscription_length > 0) {

            if (empty($from_date)) {
                $from_date = gmdate('Y-m-d H:i:s');
            }

            $expiration_date = gmdate('Y-m-d H:i:s', hf_add_time($subscription_length, self::get_period($product_id), hf_date_to_time($from_date)));
        } else {

            $expiration_date = 0;
        }

        return apply_filters('hf_subscription_product_expiration_date', $expiration_date, $product_id, $from_date);
    }


    public function set_subscription_variation_class($classname, $product_type, $post_type, $product_id) {

        if ('product_variation' === $post_type && 'variation' === $product_type) {

            $terms = get_the_terms(get_post($product_id)->post_parent, 'product_type');
            $parent_product_type = !empty($terms) && isset(current($terms)->slug) ? current($terms)->slug : '';

            if ('variable-subscription' === $parent_product_type) {
                $classname = 'WC_Product_Subscription_Variation';
            }
        }

        return $classname;
    }

    public function maybe_set_variations_price_html($variation_details, $variable_product, $variation) {

        if ($variable_product->is_type('variable-subscription') && empty($variation_details['price_html'])) {
            $variation_details['price_html'] = '<span class="price">' . $variation->get_price_html() . '</span>';
        }

        return $variation_details;
    }
    
    public static function user_do_not_allow_delete_subscription($allcaps, $caps, $args) {
        
        global $wpdb;

        if (isset($args[0]) && in_array($args[0], array('delete_post', 'delete_product')) && isset($args[2]) && (!isset($_GET['action']) || 'untrash' != $_GET['action'] ) && 0 === strpos(get_post_type($args[2]), 'product')) {

            $user_id = $args[2];
            $post_id = $args[2];
            $product = wc_get_product($post_id);

            if (false !== $product && 'trash' == hf_get_objects_property($product, 'post_status') && $product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
                $product_id = ( $product->is_type('subscription_variation') ) ? $product->get_parent_id() : $post_id;
                $subscription_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$wpdb->prefix}woocommerce_order_itemmeta` WHERE `meta_key` = '_product_id' AND `meta_value` = %d", $product_id));

                if ($subscription_count > 0) {
                    $allcaps[$caps[0]] = false;
                }
            }
        }

        return $allcaps;
    }

    public function subscription_row_actions($actions, $post) {
        global $the_product;

        if (!empty($the_product) && !isset($actions['untrash']) && $the_product->is_type(array('subscription', 'variable-subscription', 'subscription_variation'))) {
            $post_type_object = get_post_type_object($post->post_type);
            if ('trash' == $post->post_status && current_user_can($post_type_object->cap->edit_post, $post->ID)) {
                $actions['untrash'] = "<a
				title='" . esc_attr__('Restore this item from the Trash', HF_Subscriptions::TEXT_DOMAIN) . "'
				href='" . wp_nonce_url(admin_url(sprintf($post_type_object->_edit_link . '&amp;action=untrash', $post->ID)), 'untrash-post_' . $post->ID) . "'>" . __('Restore', HF_Subscriptions::TEXT_DOMAIN) . '</a>';
            }
        }

        return $actions;
    }

    public static function subscription_bulk_actions_unset_delete($actions) {
        unset($actions['delete']);
        return $actions;
    }

    public static function needs_one_time_shipping($product) {
        $product = self::maybe_get_product_instance($product);
        if ($product && $product->is_type('variation') && is_callable(array($product, 'get_parent_id'))) {
            $product = self::maybe_get_product_instance($product->get_parent_id());
        }
        return apply_filters('hf_subscription_product_needs_one_time_shipping', 'yes' === self::get_meta_data($product, 'subscription_one_time_shipping', 'no'), $product);
    }

    public function prevent_scheduled_deletion() {
        
        global $wpdb;

        $query = "UPDATE $wpdb->postmeta
					INNER JOIN $wpdb->posts ON $wpdb->postmeta.post_id = $wpdb->posts.ID
					SET $wpdb->postmeta.meta_key = '_wc_trash_meta_time'
					WHERE $wpdb->postmeta.meta_key = '_wp_trash_meta_time'
					AND $wpdb->posts.post_type IN ( 'product', 'product_variation')
					AND $wpdb->posts.post_status = 'trash'";

        $wpdb->query($query);
    }

    public function remove_variations() {

        if (isset($_POST['variation_id'])) {
            check_ajax_referer('delete-variation', 'security');
            $variation_ids = array($_POST['variation_id']);
        } else { 
            check_ajax_referer('delete-variations', 'security');
            $variation_ids = (array) $_POST['variation_ids'];
        }

        foreach ($variation_ids as $index => $variation_id) {

            $variation_post = get_post($variation_id);

            if ($variation_post && $variation_post->post_type == 'product_variation') {

                $variation_product = wc_get_product($variation_id);

                if ($variation_product && $variation_product->is_type('subscription_variation')) {

                    wp_trash_post($variation_id);

                    if (isset($_POST['variation_id'])) {
                        die();
                    } else {
                        unset($_POST['variation_ids'][$index]);
                    }
                }
            }
        }
    }

    public function bulk_edit_variations($bulk_action, $data, $variable_product_id, $variation_ids) {

        if (!isset($data['value'])) {
            return;
        } elseif (HF_Subscriptions::is_woocommerce_prior_to('2.5')) {
            if (!self::is_subscription($variable_product_id)) {
                return;
            }
        } else {
            if (empty($_POST['security']) || !wp_verify_nonce($_POST['security'], 'bulk-edit-variations') || 'variable-subscription' !== $_POST['product_type']) {
                return;
            }
        }

        $meta_key = str_replace('variable', '', $bulk_action);

        if ('_regular_price' == $meta_key) {
            $meta_key = '_hf_subscription_price';
        }

        if (in_array($meta_key, self::$subscription_meta_fields)) {
            foreach ($variation_ids as $variation_id) {
                update_post_meta($variation_id, $meta_key, stripslashes($data['value']));
            }
        } elseif (in_array($meta_key, array('_regular_price_increase', '_regular_price_decrease'))) {
            $operator = ( '_regular_price_increase' == $meta_key ) ? '+' : '-';
            $value = wc_clean($data['value']);

            foreach ($variation_ids as $variation_id) {
                $subscription_price = get_post_meta($variation_id, '_hf_subscription_price', true);

                if ('%' === substr($value, -1)) {
                    $percent = wc_format_decimal(substr($value, 0, -1));
                    $subscription_price += ( ( $subscription_price / 100 ) * $percent ) * "{$operator}1";
                } else {
                    $subscription_price += $value * "{$operator}1";
                }

                update_post_meta($variation_id, '_hf_subscription_price', $subscription_price);
            }
        }
    }

    private static function maybe_get_product_instance($product) {

        if (!is_object($product) || !is_a($product, 'WC_Product')) {
            $product = wc_get_product($product);
        }

        return $product;
    }

    public static function get_meta_data($product, $meta_key, $default_value, $empty_handling = 'allow_empty') {

        $product = self::maybe_get_product_instance($product);

        $meta_value = $default_value;

        if (self::is_subscription($product)) {

            if (is_callable(array($product, 'meta_exists'))) {
                $prefixed_key = hf_maybe_prefix_key($meta_key);

                if ($product->meta_exists($prefixed_key)) {
                    $meta_value = $product->get_meta($prefixed_key, true);
                }
            } elseif (isset($product->{$meta_key})) {
                $meta_value = $product->{$meta_key};
            }
        }

        if ('use_default_value' === $empty_handling && empty($meta_value)) {
            $meta_value = $default_value;
        }

        return $meta_value;
    }

    public function variable_subscription_product_sync($product) {

        if (self::is_subscription($product)) {

            $child_variation_ids = $product->get_visible_children();

            if ($child_variation_ids) {

                $min_max_data = hf_get_min_max_variation_data($product, $child_variation_ids);

                $product->add_meta_data('_min_price_variation_id', $min_max_data['min']['variation_id'], true);
                $product->add_meta_data('_max_price_variation_id', $min_max_data['max']['variation_id'], true);

                $product->add_meta_data('_min_variation_price', $min_max_data['min']['price'], true);
                $product->add_meta_data('_max_variation_price', $min_max_data['max']['price'], true);
                $product->add_meta_data('_min_variation_regular_price', $min_max_data['min']['regular_price'], true);
                $product->add_meta_data('_max_variation_regular_price', $min_max_data['max']['regular_price'], true);
                $product->add_meta_data('_min_variation_sale_price', $min_max_data['min']['sale_price'], true);
                $product->add_meta_data('_max_variation_sale_price', $min_max_data['max']['sale_price'], true);

                $product->add_meta_data('_min_variation_period', $min_max_data['min']['period'], true);
                $product->add_meta_data('_max_variation_period', $min_max_data['max']['period'], true);
                $product->add_meta_data('_min_variation_period_interval', $min_max_data['min']['interval'], true);
                $product->add_meta_data('_max_variation_period_interval', $min_max_data['max']['interval'], true);

                $product->add_meta_data('_hf_subscription_price', $min_max_data['min']['price'], true);
                $product->add_meta_data('_subscription_period', $min_max_data['min']['period'], true);
                $product->add_meta_data('_subscription_period_interval', $min_max_data['min']['interval'], true);
                $product->add_meta_data('_subscription_length', $min_max_data['subscription']['length'], true);
            }
        }

        return $product;
    }

    public static function get_parent_product_ids($product) {
        
        global $wpdb;
        $parent_product_ids = array();

        if (HF_Subscriptions::is_woocommerce_prior_to('3.0') && $product->get_parent()) {
            $parent_product_ids[] = $product->get_parent();
        } else {
            $parent_product_ids = $wpdb->get_col($wpdb->prepare(
                            "SELECT post_id
				FROM {$wpdb->prefix}postmeta
				WHERE meta_key = '_children' AND meta_value LIKE '%%i:%d;%%'", $product->get_id()
                    ));
        }

        return $parent_product_ids;
    }

}

new HF_Subscriptions_Product();