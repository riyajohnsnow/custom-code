<?php
if (!defined('ABSPATH')) {
    exit;
}

class WC_Product_Subscription_Variation_Legacy extends WC_Product_Subscription_Variation {

    protected $data = array();

    public function __construct($product, $args = array()) {

        parent::__construct($product, $args = array());
        $this->parent_product_type = $this->product_type;
        $this->product_type = 'subscription_variation';
        $this->subscription_variation_level_meta_data = array(
            'subscription_price' => 0,
            'subscription_period' => '',
            'subscription_period_interval' => 'day',
            'subscription_length' => 0,
        );

        $this->variation_level_meta_data = array_merge($this->variation_level_meta_data, $this->subscription_variation_level_meta_data);
    }

    public function __isset($key) {
        if (in_array($key, array('variation_data', 'variation_has_stock'))) {
            return true;
        } elseif (in_array($key, array_keys($this->variation_level_meta_data))) {
            return metadata_exists('post', $this->variation_id, '_' . $key);
        } elseif (in_array($key, array_keys($this->variation_inherited_meta_data))) {
            return metadata_exists('post', $this->variation_id, '_' . $key) || metadata_exists('post', $this->id, '_' . $key);
        } else {
            return metadata_exists('post', $this->id, '_' . $key);
        }
    }

    public function __get($key) {
        return WC_Product_Variation::__get($key);
    }

    public function get_parent_id() {
        return $this->id;
    }

}