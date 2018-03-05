<?php

if (!defined('ABSPATH')) {
    exit;
}

// subscription product variation class extends the WC_Product_Variation product class to create subscription product variations.
if(!class_exists('WC_Product_Subscription_Variation')){
class WC_Product_Subscription_Variation extends WC_Product_Variation {

    protected $subscription_variation_level_meta_data;

    public function __construct($product = 0) {
        parent::__construct($product);
        $this->subscription_variation_level_meta_data = new HF_Post_Meta_Property($this->get_id());
    }

    public function __get($key) {

        if ('subscription_variation_level_meta_data' === $key) {
            hf_deprecated_argument(__CLASS__ . '::$' . $key, '2.2.0', 'Product properties should not be accessed directly with WooCommerce 3.0+. Use the getter in HF_Subscriptions_Product instead.');
            $value = $this->subscription_variation_level_meta_data;
        } else {
            $value = hf_product_deprecated_property_handler($key, $this);
            if (is_null($value)) {
                $value = parent::__get($key);
            }
        }
        return $value;
    }

    public function get_type() {
        return 'subscription_variation';
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

    public function is_purchasable() {
        $purchasable = wc_get_product($this->get_parent_id())->is_purchasable();
        return apply_filters('hf_subscription_variation_is_purchasable', $purchasable, $this);
    }

    public function is_type($type) {
        if ('variation' == $type || ( is_array($type) && in_array('variation', $type) )) {
            return true;
        } else {
            return parent::is_type($type);
        }
    }
}
}