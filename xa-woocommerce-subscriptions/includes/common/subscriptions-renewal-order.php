<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscription_Renewal_Order {

    public function __construct() {

        add_action('woocommerce_payment_complete', array($this, 'trigger_renewal_payment_complete'), 10);
        add_filter('woocommerce_order_status_changed', __CLASS__. '::maybe_record_subscription_payment', 10, 3);
        add_filter('hf_renewal_order_created',  __CLASS__. '::add_order_note', 10, 2);
        add_filter('wp_loaded', array($this, 'prevent_cancelling_renewal_orders'), 19, 3);
    }

    public function trigger_renewal_payment_complete($order_id) {
        
        if (hf_order_contains_renewal($order_id)) {
            do_action('woocommerce_renewal_order_payment_complete', $order_id);
        }
    }

    public static function get_failed_order_replaced_by($renewal_order_id) {
        global $wpdb;

        $failed_order_id = $wpdb->get_var($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_failed_order_replaced_by' AND meta_value = %s", $renewal_order_id));

        return ( null === $failed_order_id ) ? false : $failed_order_id;
    }

    public static function maybe_record_subscription_payment($order_id, $orders_old_status, $orders_new_status) {

        if (!hf_order_contains_renewal($order_id)) {
            return;
        }

        $subscriptions = hf_get_subscriptions_for_renewal_order($order_id);
        $was_activated = false;
        $order = wc_get_order($order_id);
        $order_completed = in_array($orders_new_status, array(apply_filters('woocommerce_payment_complete_order_status', 'processing', $order_id), 'processing', 'completed'));
        $order_needed_payment = in_array($orders_old_status, apply_filters('woocommerce_valid_order_statuses_for_payment', array('pending', 'on-hold', 'failed'), $order));

        if ($order_completed && $order_needed_payment) {

            if (HF_Subscriptions::is_woocommerce_prior_to('3.0')) {
                $update_post_data = array(
                    'ID' => $order_id,
                    'post_date' => current_time('mysql', 0),
                    'post_date_gmt' => current_time('mysql', 1),
                );

                wp_update_post($update_post_data);
                update_post_meta($order_id, '_paid_date', current_time('mysql'));
            } else {
                $order->set_date_paid(current_time('timestamp', 1));
                $order->save();
            }
        }

        foreach ($subscriptions as $subscription) {

            if ($order_completed && !$subscription->has_status(hf_get_subscription_ended_statuses()) && !$subscription->has_status('active')) {

                $is_failed_renewal_order = ( 'failed' === $orders_old_status ) ? true : false;
                $is_failed_renewal_order = apply_filters('hf_subscriptions_is_failed_renewal_order', $is_failed_renewal_order, $order_id, $orders_old_status);

                if ($order_needed_payment) {
                    $subscription->payment_complete();
                    $was_activated = true;
                }

                if ($is_failed_renewal_order) {
                    do_action('hf_subscription_paid_for_failed_renewal_order', wc_get_order($order_id), $subscription);
                }
            } elseif ('failed' == $orders_new_status) {
                $subscription->payment_failed();
            }
        }

        if ($was_activated) {
            do_action('subscriptions_activated_for_order', $order_id);
        }
    }

    public static function add_order_note($renewal_order, $subscription) {
        if (!is_object($subscription)) {
            $subscription = hf_get_subscription($subscription);
        }
        if (!is_object($renewal_order)) {
            $renewal_order = wc_get_order($renewal_order);
        }
        if (is_a($renewal_order, 'WC_Order') && hf_is_subscription($subscription)) {
            $order_number = sprintf(__('#%s', HF_Subscriptions::TEXT_DOMAIN), $renewal_order->get_order_number());
            $subscription->add_order_note(sprintf(__('Order %s created to record renewal.', HF_Subscriptions::TEXT_DOMAIN), sprintf('<a href="%s">%s</a> ', esc_url(hf_get_edit_post_link(hf_get_objects_property($renewal_order, 'id'))), $order_number)));
        }
        return $renewal_order;
    }

    public function prevent_cancelling_renewal_orders() {
        
        if (isset($_GET['cancel_order']) && isset($_GET['order']) && isset($_GET['order_id'])) {

            $order_id = absint($_GET['order_id']);
            $order = wc_get_order($order_id);
            $redirect = $_GET['redirect'];

            if (hf_order_contains_renewal($order)) {
                remove_action('wp_loaded', 'WC_Form_Handler::cancel_order', 20);
                wc_add_notice(__('Subscription renewal orders cannot be cancelled.', HF_Subscriptions::TEXT_DOMAIN), 'notice');

                if ($redirect) {
                    wp_safe_redirect($redirect);
                    exit;
                }
            }
        }
    }


    public static function get_parent_order_id($renewal_order) {
        
        $parent_order = self::get_parent_order($renewal_order);
        return ( null === $parent_order ) ? null : hf_get_objects_property($parent_order, 'id');
    }

    public static function get_parent_order($renewal_order) {
        

        if (!is_object($renewal_order)) {
            $renewal_order = new WC_Order($renewal_order);
        }

        $subscriptions = hf_get_subscriptions_for_renewal_order($renewal_order);
        $subscription = array_pop($subscriptions);

        if (false == $subscription->get_parent_id()) {
            $parent_order = null;
        } else {
            $parent_order = $subscription->get_parent();
        }

        return apply_filters('hf_subscription_parent_order', $parent_order, $renewal_order);
    }

}

new HF_Subscription_Renewal_Order();