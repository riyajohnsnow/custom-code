<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Download_Handler {

    public function __construct() {

        add_action('woocommerce_grant_product_download_permissions', array($this, 'save_downloadable_product_permissions'));
        add_filter('woocommerce_get_item_downloads', array($this, 'get_item_downloads'), 10, 3);
        add_action('woocommerce_process_shop_order_meta', array($this, 'correct_permission_data'), 60, 1);
        add_action('deleted_post', array($this, 'delete_subscription_permissions'));
        add_action('woocommerce_process_product_file_download_paths', array($this, 'grant_new_file_product_permissions'), 11, 3);
    }

    public function save_downloadable_product_permissions($order_id) {
        
        global $wpdb;
        $order = wc_get_order($order_id);

        if (hf_order_contains_subscription($order, 'any')) {
            $subscriptions = hf_get_subscriptions_for_order($order, array('order_type' => array('any')));
        } else {
            return;
        }

        foreach ($subscriptions as $subscription) {
            if (sizeof($subscription->get_items()) > 0) {
                foreach ($subscription->get_items() as $item) {
                    $_product = $subscription->get_product_from_item($item);

                    if ($_product && $_product->exists() && $_product->is_downloadable()) {
                        $downloads = hf_get_objects_property($_product, 'downloads');
                        $product_id = hf_get_canonical_product_id($item);

                        foreach (array_keys($downloads) as $download_id) {
                            if (!$wpdb->get_var($wpdb->prepare("SELECT download_id FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions WHERE `order_id` = %d AND `product_id` = %d AND `download_id` = '%s'", $subscription->get_id(), $product_id, $download_id))) {
                                wc_downloadable_file_permission($download_id, $product_id, $subscription, $item['qty']);
                            }
                            self::revoke_downloadable_file_permission($product_id, $order_id, $order->get_user_id());
                        }
                    }
                }
            }
            update_post_meta($subscription->get_id(), '_download_permissions_granted', 1);
        }
    }

    public static function revoke_downloadable_file_permission($product_id, $order_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'woocommerce_downloadable_product_permissions';
        $where = array(
            'product_id' => $product_id,
            'order_id' => $order_id,
            'user_id' => $user_id,
        );

        $format = array('%d', '%d', '%d');
        return $wpdb->delete($table, $where, $format);
    }

    public function get_item_downloads($files, $item, $order) {
        
        global $wpdb;

        if (hf_order_contains_subscription($order, array('parent', 'renewal', 'switch'))) {
            $subscriptions = hf_get_subscriptions_for_order($order, array('order_type' => array('parent', 'renewal', 'switch')));
        } else {
            return $files;
        }
        $product_id = hf_get_canonical_product_id($item);
        foreach ($subscriptions as $subscription) {
            foreach ($subscription->get_items() as $subscription_item) {
                if (hf_get_canonical_product_id($subscription_item) === $product_id) {
                    if (is_callable(array($subscription_item, 'get_item_downloads'))) {
                        $files = $subscription_item->get_item_downloads($subscription_item);
                    } else {
                        $files = $subscription->get_item_downloads($subscription_item);
                    }
                }
            }
        }

        return $files;
    }

    public function correct_permission_data($post_id) {
        
        if (absint($post_id) !== $post_id) {
            return;
        }
        if ('hf_shop_subscription' !== get_post_type($post_id)) {
            return;
        }
        global $wpdb;
        $wpdb->query($wpdb->prepare("
			UPDATE {$wpdb->prefix}woocommerce_downloadable_product_permissions
			SET access_expires = null
			WHERE order_id = %d
			AND access_expires = %s
		", $post_id, '0000-00-00 00:00:00'));
    }

    public function delete_subscription_permissions($post_id) {
        
        global $wpdb;
        if ('hf_shop_subscription' == get_post_type($post_id)) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}woocommerce_downloadable_product_permissions WHERE order_id = %d", $post_id));
        }
    }

    public function grant_new_file_product_permissions($product_id, $variation_id, $downloadable_files) {
        
        global $wpdb;
        $product_id = ( $variation_id ) ? $variation_id : $product_id;
        $product = wc_get_product($product_id);
        $existing_download_ids = array_keys((array) hf_get_objects_property($product, 'downloads'));
        $downloadable_ids = array_keys((array) $downloadable_files);
        $new_download_ids = array_filter(array_diff($downloadable_ids, $existing_download_ids));

        if (!empty($new_download_ids)) {

            $existing_permissions = $wpdb->get_col($wpdb->prepare("SELECT order_id from {$wpdb->prefix}woocommerce_downloadable_product_permissions WHERE product_id = %d GROUP BY order_id", $product_id));
            $subscriptions = hf_get_subscriptions_for_product($product_id);

            foreach ($subscriptions as $subscription_id) {

                if (!in_array($subscription_id, $existing_permissions) || false === HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
                    $subscription = hf_get_subscription($subscription_id);

                    foreach ($new_download_ids as $download_id) {
                        if ($subscription && apply_filters('woocommerce_process_product_file_download_paths_grant_access_to_new_file', true, $download_id, $product_id, $subscription)) {
                            wc_downloadable_file_permission($download_id, $product_id, $subscription);
                        }
                    }
                }
            }
        }
    }

}

new HF_Download_Handler();