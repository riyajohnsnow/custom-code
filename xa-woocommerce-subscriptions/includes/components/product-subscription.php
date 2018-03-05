<?php
if (!defined('ABSPATH')) {
    exit;
}

if(!class_exists('WC_Product_Subscription')){
class WC_Product_Subscription extends WC_Product_Simple {

    public function get_type() {
        return 'subscription';
    }

    public function __get($key) {

        $value = hf_product_deprecated_property_handler($key, $this);
        if (is_null($value)) {
            $value = parent::__get($key);
        }
        return $value;
    }

    public function get_price_html($price = '') {

        $price = parent::get_price_html($price);
        if (!empty($price)) {
            $price = HF_Subscriptions_Product::get_price_string($this, array('price' => $price));
        }
        return $price;
    }

    public function add_to_cart_text() {

        if ($this->is_purchasable() && $this->is_in_stock()) {
            $button_text = get_option(HF_Subscriptions_Admin::$option_prefix . '_add_to_cart_button_text', __('Subscribe', HF_Subscriptions::TEXT_DOMAIN));
            if(empty($button_text)){
               $button_text = __('Subscribe', HF_Subscriptions::TEXT_DOMAIN);
            }
        } else {
            $button_text = parent::add_to_cart_text();
        }
        return apply_filters('woocommerce_product_add_to_cart_text', $button_text, $this);
    }

    public function single_add_to_cart_text() {
        return apply_filters('woocommerce_product_single_add_to_cart_text', self::add_to_cart_text(), $this);
    }

    function is_purchasable() {
        $purchasable = parent::is_purchasable();
        return apply_filters('hf_subscription_is_purchasable', $purchasable, $this);
    }

}
}