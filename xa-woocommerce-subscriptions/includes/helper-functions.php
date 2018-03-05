<?php
if (!defined('ABSPATH')) {
    exit;
}

function hf_date_input($timestamp = 0, $args = array()) {

    $args = wp_parse_args($args, array(
        'name_attr' => '',
        'include_time' => true,
            )
    );

    $date = ( 0 !== $timestamp ) ? date_i18n('Y-m-d', $timestamp) : '';
    $date_input = '<input type="text" class="date-picker hf-subscriptions" placeholder="' . esc_attr__('YYYY-MM-DD', HF_Subscriptions::TEXT_DOMAIN) . '" name="' . esc_attr($args['name_attr']) . '" id="' . esc_attr($args['name_attr']) . '" maxlength="10" value="' . esc_attr($date) . '" pattern="([0-9]{4})-(0[1-9]|1[012])-(##|0[1-9#]|1[0-9]|2[0-9]|3[01])"/>';

    if (true === $args['include_time']) {
        $hours = ( 0 !== $timestamp ) ? date_i18n('H', $timestamp) : '';
        $hour_input = '<input type="text" class="hour" placeholder="' . esc_attr__('HH', HF_Subscriptions::TEXT_DOMAIN) . '" name="' . esc_attr($args['name_attr']) . '_hour" id="' . esc_attr($args['name_attr']) . '_hour" value="' . esc_attr($hours) . '" maxlength="2" size="2" pattern="([01]?[0-9]{1}|2[0-3]{1})" />';
        $minutes = ( 0 !== $timestamp ) ? date_i18n('i', $timestamp) : '';
        $minute_input = '<input type="text" class="minute" placeholder="' . esc_attr__('MM', HF_Subscriptions::TEXT_DOMAIN) . '" name="' . esc_attr($args['name_attr']) . '_minute" id="' . esc_attr($args['name_attr']) . '_minute" value="' . esc_attr($minutes) . '" maxlength="2" size="2" pattern="[0-5]{1}[0-9]{1}" />';
        $date_input = sprintf('%s@%s:%s', $date_input, $hour_input, $minute_input);
    }

    $timestamp_utc = ( 0 !== $timestamp ) ? $timestamp - get_option('gmt_offset', 0) * HOUR_IN_SECONDS : $timestamp;
    $date_input = '<div class="hf-date-input">' . $date_input . '</div>';

    return apply_filters('hf_subscriptions_date_input', $date_input, $timestamp, $args);
}

function hf_get_edit_post_link($post_id) {
    
    $post_type_object = get_post_type_object(get_post_type($post_id));
    if (!$post_type_object || !in_array($post_type_object->name, array('shop_order', 'hf_shop_subscription'))) {
        return;
    }

    return apply_filters('get_edit_post_link', admin_url(sprintf($post_type_object->_edit_link . '&action=edit', $post_id)), $post_id, '');
}

function hf_str_to_ascii($string) {

    $ascii = false;
    if (function_exists('iconv')) {
        $ascii = iconv('UTF-8', 'ASCII//IGNORE', $string);
    }
    return false === $ascii ? preg_replace('/[^a-zA-Z0-9_\-]/', '', $string) : $ascii;
}

function hf_json_encode($data) {
    if (function_exists('wp_json_encode')) {
        return wp_json_encode($data);
    }
    return json_encode($data);
}

function hf_array_insert_after($needle, $haystack, $new_key, $new_value) {

    if (array_key_exists($needle, $haystack)) {
        $new_array = array();
        foreach ($haystack as $key => $value) {
            $new_array[$key] = $value;
            if ($key === $needle) {
                $new_array[$new_key] = $new_value;
            }
        }
        return $new_array;
    }
    return $haystack;
}

function hf_get_rounding_precision() {
    if (function_exists('wc_get_rounding_precision')) {
        $precision = wc_get_rounding_precision();
    } elseif (defined('WC_ROUNDING_PRECISION')) {
        $precision = WC_ROUNDING_PRECISION;
    } else {
        $precision = wc_get_price_decimals() + 2;
    }

    return $precision;
}

function hf_maybe_prefix_key($key, $prefix = '_') {
    return ( substr($key, 0, strlen($prefix)) != $prefix ) ? $prefix . $key : $key;
}

function hf_get_product_limitation($product) {

    if (!is_object($product) || !is_a($product, 'WC_Product')) {
        $product = wc_get_product($product);
    }

    return apply_filters('hf_subscription_product_limitation', HF_Subscriptions_Product::get_meta_data($product, 'subscription_limit', 'no', 'use_default_value'), $product);
}

function hf_is_product_limited_for_user($product, $user_id = 0) {
    if (!is_object($product)) {
        $product = wc_get_product($product);
    }

    return ( ( 'active' == hf_get_product_limitation($product) && hf_user_has_subscription($user_id, $product->get_id(), 'on-hold') ) || ( 'no' !== hf_get_product_limitation($product) && hf_user_has_subscription($user_id, $product->get_id(), hf_get_product_limitation($product)) ) ) ? true : false;
}

function hf_order_contains_switch($order) {

    if (!is_a($order, 'WC_Abstract_Order')) {
        $order = wc_get_order($order);
    }
    if (!hf_is_order($order) || hf_order_contains_renewal($order)) {
        $is_switch_order = false;
    } else {
        $switched_subscriptions = hf_get_subscriptions_for_switch_order($order);
        if (!empty($switched_subscriptions)) {
            $is_switch_order = true;
        } else {
            $is_switch_order = false;
        }
    }

    return apply_filters('hf_subscriptions_is_switch_order', $is_switch_order, $order);
}

function hf_get_subscriptions_for_switch_order($order) {

    if (!is_a($order, 'WC_Abstract_Order')) {
        $order = wc_get_order($order);
    }

    $subscriptions = array();
    $subscription_ids = hf_get_objects_property($order, 'subscription_switch', 'multiple');

    foreach ($subscription_ids as $subscription_id) {
        $subscription = hf_get_subscription($subscription_id);
        if ($subscription) {
            $subscriptions[$subscription_id] = $subscription;
        }
    }
    return $subscriptions;
}

function hf_get_switch_orders_for_subscription($subscription_id) {

    $orders = array();

    $order_ids = get_posts(array(
        'post_type' => 'shop_order',
        'post_status' => 'any',
        'fields' => 'ids',
        'posts_per_page' => -1,
        'meta_query' => array(
            array(
                'key' => '_subscription_switch',
                'value' => $subscription_id,
            ),
        ),
            ));

    foreach ($order_ids as $order_id) {
        $orders[$order_id] = wc_get_order($order_id);
    }
    return $orders;
}

function hf_is_product_switchable_type($product) {

    if (!is_object($product)) {
        $product = wc_get_product($product);
    }

    $variation = null;
    if (empty($product)) {
        $is_product_switchable = false;
    } else {

        $parent_id = hf_get_objects_property($product, 'parent_id');

        if ($product->is_type('subscription_variation') && !empty($parent_id)) {
            $variation = $product;
            $product = wc_get_product($parent_id);
            ;
        }

        $allow_switching = get_option(HF_Subscriptions_Admin::$option_prefix . '_allow_switching', 'no');

        switch ($allow_switching) {
            case 'variable' :
                $is_product_switchable = ( $product->is_type(array('variable-subscription', 'subscription_variation')) ) ? true : false;
                break;
            case 'grouped' :
                $is_product_switchable = ( HF_Subscriptions_Product::get_parent_product_ids($product) ) ? true : false;
                break;
            case 'variable_grouped' :
                $is_product_switchable = ( $product->is_type(array('variable-subscription', 'subscription_variation')) || HF_Subscriptions_Product::get_parent_product_ids($product) ) ? true : false;
                break;
            case 'no' :
            default:
                $is_product_switchable = false;
                break;
        }
    }

    return apply_filters('hf_is_product_switchable', $is_product_switchable, $product, $variation);
}