<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Product_Legacy {

    public function __construct() {
        add_filter('woocommerce_product_class', array($this, 'set_product_class'), 100, 4);
    }

    public function set_product_class($classname, $product_type, $post_type, $product_id) {

        if (HF_Subscriptions::is_woocommerce_prior_to('3.0') && in_array($classname, array('WC_Product_Subscription', 'WC_Product_Variable_Subscription', 'WC_Product_Subscription_Variation'))) {
            $classname .= '_Legacy';
        }
        return $classname;
    }

}

new HF_Product_Legacy();