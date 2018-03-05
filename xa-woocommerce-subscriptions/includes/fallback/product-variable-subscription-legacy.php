<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Product_Variable_Subscription_Legacy extends WC_Product_Variable_Subscription {

    var $subscription_price;
    var $subscription_period;
    var $max_variation_period;
    var $subscription_period_interval;
    var $max_variation_period_interval;
    var $product_type;
    protected $prices_array;

    public function __construct($product) {

        parent::__construct($product);
        $this->parent_product_type = $this->product_type;
        $this->product_type = 'variable-subscription';
        $this->product_custom_fields = get_post_meta($this->id);

        if (!empty($this->product_custom_fields['_hf_subscription_price'][0])) {
            $this->subscription_price = $this->product_custom_fields['_hf_subscription_price'][0];
        }

        if (!empty($this->product_custom_fields['_subscription_period'][0])) {
            $this->subscription_period = $this->product_custom_fields['_subscription_period'][0];
        }

        if (!empty($this->product_custom_fields['_subscription_period_interval'][0])) {
            $this->subscription_period_interval = $this->product_custom_fields['_subscription_period_interval'][0];
        }

        if (!empty($this->product_custom_fields['_subscription_length'][0])) {
            $this->subscription_length = $this->product_custom_fields['_subscription_length'][0];
        }



    }

    public function get_variation_price($min_or_max = 'min', $display = false) {
        $variation_id = get_post_meta($this->id, '_' . $min_or_max . '_price_variation_id', true);

        if ($display) {
            if ($variation = wc_get_product($variation_id)) {
                if ('incl' == get_option('woocommerce_tax_display_shop')) {
                    $price = hf_get_price_including_tax($variation);
                } else {
                    $price = hf_get_price_excluding_tax($variation);
                }
            } else {
                $price = '';
            }
        } else {
            $price = get_post_meta($variation_id, '_price', true);
        }

        return apply_filters('woocommerce_get_variation_price', $price, $this, $min_or_max, $display);
    }

    public function get_variation_prices($display = false) {

        $price_hash = $this->get_price_hash($this, $display);

        $this->prices_array[$price_hash] = parent::get_variation_prices($display);

        $children = array_keys($this->prices_array[$price_hash]['price']);
        sort($children);

        $min_max_data = hf_get_min_max_variation_data($this, $children);

        $min_variation_id = $min_max_data['min']['variation_id'];
        $max_variation_id = $min_max_data['max']['variation_id'];

        foreach ($this->prices_array as $price_hash => $prices) {

            foreach ($prices as $price_key => $variation_prices) {

                $min_price = $prices[$price_key][$min_variation_id];
                $max_price = $prices[$price_key][$max_variation_id];

                unset($prices[$price_key][$min_variation_id]);
                unset($prices[$price_key][$max_variation_id]);

                $prices[$price_key] = array($min_variation_id => $min_price) + $prices[$price_key];
                $prices[$price_key] += array($max_variation_id => $max_price);

                $this->prices_array[$price_hash][$price_key] = $prices[$price_key];
            }
        }

        $this->subscription_price = $min_max_data['min']['price'];
        $this->subscription_period = $min_max_data['min']['period'];
        $this->subscription_period_interval = $min_max_data['min']['interval'];

        $this->max_variation_price = $min_max_data['max']['price'];
        $this->max_variation_period = $min_max_data['max']['period'];
        $this->max_variation_period_interval = $min_max_data['max']['interval'];

        $this->min_variation_price = $min_max_data['min']['price'];
        $this->min_variation_regular_price = $min_max_data['min']['regular_price'];

        return $this->prices_array[$price_hash];
    }

    protected function get_price_hash($display = false) {
        global $wp_filter;

        if ($display) {
            $price_hash = array(get_option('woocommerce_tax_display_shop', 'excl'), WC_Tax::get_rates());
        } else {
            $price_hash = array(false);
        }
        $filter_names = array('woocommerce_variation_prices_price', 'woocommerce_variation_prices_regular_price', 'woocommerce_variation_prices_sale_price');

        foreach ($filter_names as $filter_name) {
            if (!empty($wp_filter[$filter_name])) {
                $price_hash[$filter_name] = array();

                foreach ($wp_filter[$filter_name] as $priority => $callbacks) {
                    $price_hash[$filter_name][] = array_values(wp_list_pluck($callbacks, 'function'));
                }
            }
        }

        $price_hash = md5(json_encode(apply_filters('woocommerce_get_variation_prices_hash', $price_hash, $this, $display)));

        return $price_hash;
    }

    public function variable_product_sync($product_id = '') {

        WC_Product_Variable::variable_product_sync($product_id);
        $child_variation_ids = $this->get_children(true);

        if ($child_variation_ids) {
            $min_max_data = hf_get_min_max_variation_data($this, $child_variation_ids);
            update_post_meta($this->id, '_min_price_variation_id', $min_max_data['min']['variation_id']);
            update_post_meta($this->id, '_max_price_variation_id', $min_max_data['max']['variation_id']);
            update_post_meta($this->id, '_price', $min_max_data['min']['price']);
            update_post_meta($this->id, '_min_variation_price', $min_max_data['min']['price']);
            update_post_meta($this->id, '_max_variation_price', $min_max_data['max']['price']);
            update_post_meta($this->id, '_min_variation_regular_price', $min_max_data['min']['regular_price']);
            update_post_meta($this->id, '_max_variation_regular_price', $min_max_data['max']['regular_price']);
            update_post_meta($this->id, '_min_variation_sale_price', $min_max_data['min']['sale_price']);
            update_post_meta($this->id, '_max_variation_sale_price', $min_max_data['max']['sale_price']);
            update_post_meta($this->id, '_min_variation_period', $min_max_data['min']['period']);
            update_post_meta($this->id, '_max_variation_period', $min_max_data['max']['period']);
            update_post_meta($this->id, '_min_variation_period_interval', $min_max_data['min']['interval']);
            update_post_meta($this->id, '_max_variation_period_interval', $min_max_data['max']['interval']);
            update_post_meta($this->id, '_hf_subscription_price', $min_max_data['min']['price']);
            update_post_meta($this->id, '_subscription_period', $min_max_data['min']['period']);
            update_post_meta($this->id, '_subscription_period_interval', $min_max_data['min']['interval']);
            update_post_meta($this->id, '_subscription_length', $min_max_data['subscription']['length']);

            $this->subscription_price = $min_max_data['min']['price'];
            $this->subscription_period = $min_max_data['min']['period'];
            $this->subscription_period_interval = $min_max_data['min']['interval'];
            $this->subscription_length = $min_max_data['subscription']['length'];

            if (function_exists('wc_delete_product_transients')) {
                wc_delete_product_transients($this->id);
            } else {
                WC()->clear_product_transients($this->id);
            }
        } else {
            $this->subscription_price = '';
            $this->subscription_period = 'day';
            $this->subscription_period_interval = 1;
            $this->subscription_length = 0;
        }
    }

    public function get_price_html($price = '') {

        if (!isset($this->subscription_period) || !isset($this->subscription_period_interval) || !isset($this->max_variation_period) || !isset($this->max_variation_period_interval)) {
            $this->variable_product_sync();
        }

        if ($this->subscription_price !== '') {

            $price = '';

            if ($this->is_on_sale() && isset($this->min_variation_price) && $this->min_variation_regular_price !== $this->get_price()) {

                if (!$this->min_variation_price || $this->min_variation_price !== $this->max_variation_price) {
                    $price .= hf_get_price_html_from_text($this);
                }

                $variation_id = get_post_meta($this->id, '_min_price_variation_id', true);
                $variation = wc_get_product($variation_id);
                $tax_display_mode = get_option('woocommerce_tax_display_shop');

                $sale_price_args = array('qty' => 1, 'price' => $variation->get_sale_price());
                $regular_price_args = array('qty' => 1, 'price' => $variation->get_regular_price());

                if ('incl' == $tax_display_mode) {
                    $sale_price = hf_get_price_including_tax($variation, $sale_price_args);
                    $regular_price = hf_get_price_including_tax($variation, $regular_price_args);
                } else {
                    $sale_price = hf_get_price_excluding_tax($variation, $sale_price_args);
                    $regular_price = hf_get_price_excluding_tax($variation, $regular_price_args);
                }

                $price .= $this->get_price_html_from_to($regular_price, $sale_price);
            } else {

                if ($this->min_variation_price !== $this->max_variation_price) {
                    $price .= hf_get_price_html_from_text($this);
                }

                $price .= wc_price($this->get_variation_price('min', true));
            }

            if (false === strpos($price, hf_get_price_html_from_text($this))) {
                if ($this->subscription_period !== $this->max_variation_period) {
                    $price = hf_get_price_html_from_text($this) . $price;
                } elseif ($this->subscription_period_interval !== $this->max_variation_period_interval) {
                    $price = hf_get_price_html_from_text($this) . $price;
                }
            }

            $price .= $this->get_price_suffix();

            $price = HF_Subscriptions_Product::get_price_string($this, array('price' => $price));
        }

        return apply_filters('woocommerce_variable_subscription_price_html', $price, $this);
    }

    function get_meta($meta_key = '', $single = true, $context = 'view') {
        return get_post_meta($this->get_id(), $meta_key, $single);
    }

    public function get_child($child_id) {
        return wc_get_product($child_id, array(
            'product_type' => 'Subscription_Variation',
            'parent_id' => $this->id,
            'parent' => $this,
        ));
    }

    public function get_default_attributes($context = 'view') {
        return $this->get_variation_default_attributes();
    }

}