<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Cached_Data_Manager extends HF_Cache_Manager {

    public $logger = null;

    public function __construct() {
        
        add_action('woocommerce_loaded', array($this, 'load_logger'));
        add_action('trashed_post', array($this, 'purge_delete'), 9999);
        add_action('untrashed_post', array($this, 'purge_delete'), 9999);
        add_action('deleted_post', array($this, 'purge_delete'), 9999);
        add_action('updated_post_meta', array($this, 'purge_from_metadata'), 9999, 4);
        add_action('deleted_post_meta', array($this, 'purge_from_metadata'), 9999, 4);
        add_action('added_post_meta', array($this, 'purge_from_metadata'), 9999, 4);

        add_action('admin_init', array($this, 'initialize_cron_check_size'));
        add_filter('cron_schedules', array($this, 'add_weekly_cron_schedule'));
    }

    public function load_logger() {
        $this->logger = new WC_Logger();
    }

    public function log($message) {
        if (defined('HF_DEBUG') && HF_DEBUG) {
            $this->logger->add('hf-cache', $message);
        }
    }

    public function cache_and_get($key, $callback, $params = array(), $expires = WEEK_IN_SECONDS) {
        $expires = absint($expires);
        $data = get_transient($key);

        if (false === $data && !empty($callback)) {
            $data = call_user_func_array($callback, $params);
            set_transient($key, $data, $expires);
        }
        return $data;
    }

    public function purge_delete($post_id) {
        if ('shop_order' !== get_post_type($post_id)) {
            return;
        }
        foreach (hf_get_subscriptions_for_order($post_id, array('order_type' => 'any')) as $subscription) {
            $this->log('Calling purge delete on ' . current_filter() . ' for ' . $subscription->get_id());
            $this->clear_related_order_cache($subscription);
        }
    }

    public function purge_from_metadata($meta_id, $object_id, $meta_key, $meta_value) {
        if (!in_array($meta_key, array('_subscription_renewal', '_subscription_resubscribe', '_subscription_switch')) || 'shop_order' !== get_post_type($object_id)) {
            return;
        }
        $this->log('Calling purge from ' . current_filter() . ' on object ' . $object_id . ' and meta value ' . $meta_value . ' due to ' . $meta_key . ' meta key.');
        $this->clear_related_order_cache($meta_value);
    }

    protected function clear_related_order_cache($subscription_id) {

        if (is_object($subscription_id) && $subscription_id instanceof HF_Subscription) {
            $subscription_id = $subscription_id->get_id();
        } elseif (is_numeric($subscription_id)) {
            $subscription_id = absint($subscription_id);
        } else {
            return;
        }

        $key = 'hf-related-orders-to-' . $subscription_id;
        $this->log('In the clearing, key being purged is this: ' . print_r($key, true));
        $this->delete_cached($key);
    }

    public function delete_cached($key) {
        if (!is_string($key) || empty($key)) {
            return;
        }
        delete_transient($key);
    }

    public static function cleanup_logs() {
        $file = wc_get_log_file_path('hf-cache');
        $max_cache_size = apply_filters('hf_max_log_size', 50 * 1024 * 1024);

        if (filesize($file) >= $max_cache_size) {
            $size_to_keep = apply_filters('hf_log_size_to_keep', 25 * 1024);
            $lines_to_keep = apply_filters('hf_log_lines_to_keep', 1000);

            $fp = fopen($file, 'r');
            fseek($fp, -1 * $size_to_keep, SEEK_END);
            $data = '';
            while (!feof($fp)) {
                $data .= fread($fp, $size_to_keep);
            }
            fclose($fp);

            $lines = explode("\n", $data);
            $lines = array_filter(array_slice($lines, 1));
            $lines = array_slice($lines, -1000);
            $lines[] = '---- log file automatically truncated ' . gmdate('Y-m-d H:i:s') . ' ---';

            file_put_contents($file, implode("\n", $lines), LOCK_EX);
        }
    }

    public function initialize_cron_check_size() {

        $hook = 'hf_cleanup_big_logs';
        if (!wp_next_scheduled($hook)) {
            wp_schedule_event(time(), 'weekly', $hook);
        }
        add_action($hook, __CLASS__ . '::cleanup_logs');
    }

    function add_weekly_cron_schedule($schedules) {

        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = array(
                'interval' => WEEK_IN_SECONDS,
                'display' => __('Weekly', HF_Subscriptions::TEXT_DOMAIN),
            );
        }
        return $schedules;
    }

}