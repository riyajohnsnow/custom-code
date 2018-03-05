<?php
if (!defined('ABSPATH')) {
    exit;
}

if(!class_exists('WC_Product_Variable_Subscription')){
class WC_Product_Variable_Subscription extends WC_Product_Variable {

    private $min_max_variation_data = array();
    private $sorted_variation_prices = array();

    public function get_type() {
        return 'variable-subscription';
    }

    public function __get($key) {

        if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
            $value = parent::__get($key);
        } else {
            $value = hf_product_deprecated_property_handler($key, $this);

            if (is_null($value)) {
                $value = parent::__get($key);
            }
        }

        return $value;
    }

    public function single_add_to_cart_text() {

        if ($this->is_purchasable() && $this->is_in_stock()) {
            $button_text = get_option(HF_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text', __('Subscribe', HF_Subscriptions::TEXT_DOMAIN));
            if(empty($button_text)){
                $button_text = __('Subscribe', HF_Subscriptions::TEXT_DOMAIN);
            }
        } else {
            $button_text = parent::add_to_cart_text();
        }
        return apply_filters('woocommerce_product_single_add_to_cart_text', $button_text, $this);
    }

    public function get_price_html($price = '') {

        $prices = $this->get_variation_prices(true);

        if (empty($prices['price'])) {
            return apply_filters('woocommerce_variable_empty_price_html', '', $this);
        }

        $tax_display_mode = get_option('woocommerce_tax_display_shop');

        $price = HF_Subscriptions_Product::get_price($this->get_meta('_min_price_variation_id'));
        $price = 'incl' == $tax_display_mode ? hf_get_price_including_tax($this, array('price' => $price)) : hf_get_price_excluding_tax($this, array('price' => $price));
        $price = $this->get_price_prefix($prices) . wc_price($price) . $this->get_price_suffix();
        $price = apply_filters('woocommerce_variable_price_html', $price, $this);
        $price = HF_Subscriptions_Product::get_price_string($this, array('price' => $price));

        return apply_filters('woocommerce_variable_subscription_price_html', apply_filters('woocommerce_get_price_html', $price, $this), $this);
    }

    function is_purchasable() {
        $purchasable = parent::is_purchasable();
        return apply_filters('hf_subscription_is_purchasable', $purchasable, $this);
    }

    public function is_type($type) {
        if ('variable' == $type || ( is_array($type) && in_array('variable', $type) )) {
            return true;
        } else {
            return parent::is_type($type);
        }
    }

    protected function sort_variation_prices($prices) {

        if (empty($prices)) {
            return $prices;
        }

        $prices_hash = md5(json_encode($prices));

        if (empty($this->sorted_variation_prices[$prices_hash])) {

            $child_variation_ids = array_keys($prices);
            $variation_hash = md5(json_encode($child_variation_ids));

            if (empty($this->min_max_variation_data[$variation_hash])) {
                $this->min_max_variation_data[$variation_hash] = hf_get_min_max_variation_data($this, $child_variation_ids);
            }

            $min_variation_id = $this->min_max_variation_data[$variation_hash]['min']['variation_id'];
            $max_variation_id = $this->min_max_variation_data[$variation_hash]['max']['variation_id'];

            $min_price = $prices[$min_variation_id];
            $max_price = $prices[$max_variation_id];

            unset($prices[$min_variation_id]);
            unset($prices[$max_variation_id]);

            $prices = array($min_variation_id => $min_price) + $prices;
            $prices += array($max_variation_id => $max_price);

            $this->sorted_variation_prices[$prices_hash] = $prices;
        }

        return $this->sorted_variation_prices[$prices_hash];
    }

    protected function get_price_prefix($prices) {

        $child_variation_ids = array_keys($prices['price']);
        $variation_hash = md5(json_encode($child_variation_ids));

        if (empty($this->min_max_variation_data[$variation_hash])) {
            $this->min_max_variation_data[$variation_hash] = hf_get_min_max_variation_data($this, $child_variation_ids);
        }

        if ($this->min_max_variation_data[$variation_hash]['identical']) {
            $prefix = '';
        } else {
            $prefix = hf_get_price_html_from_text($this);
        }

        return $prefix;
    }

}
}