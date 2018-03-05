<?php
if (!defined('ABSPATH')) {
    exit;
}

// interface to provide accessing objects as arrays.

class HF_Post_Meta_Property implements ArrayAccess {

    protected $product_id;

    public function __construct($product_id) {
        $this->product_id = $product_id;
    }

    public function offsetGet($key) {
        return get_post_meta($this->product_id, $this->maybe_prefix_meta_key($key));
    }

    public function offsetSet($key, $value) {
        update_post_meta($this->product_id, $this->maybe_prefix_meta_key($key), $value);
    }

    public function offsetExists($key) {
        return metadata_exists('post', $this->product_id, $this->maybe_prefix_meta_key($key));
    }

    public function offsetUnset($key) {        
    }

    protected function maybe_prefix_meta_key($key) {
        if ('_' != substr($key, 0, 1)) {
            $key = '_' . $key;
        }
        return $key;
    }
}