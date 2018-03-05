<?php

if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscriptions_Email {

    public function __construct() {

        add_action('woocommerce_email_classes', array($this, 'add_hf_emails'), 10, 1);
        add_action('woocommerce_init', array($this, 'hook_transactional_emails'));
        add_filter('woocommerce_resend_order_emails_available', array($this, 'renewal_order_emails_available'), -1);
        add_action('hf_subscription_email_order_details', array($this, 'order_details'), 10, 4);
    }

    public function add_hf_emails($email_classes) {

        require_once( 'email_notification/new-renewal-order.php' );
        require_once( 'email_notification/processing-renewal-order.php' );
        require_once( 'email_notification/completed-renewal-order.php' );
        require_once( 'email_notification/renewal-invoice.php' );
        require_once( 'email_notification/cancelled-subscription.php' );
        require_once( 'email_notification/expired-subscription.php' );
        require_once( 'email_notification/on-hold-subscription.php' );

        $email_classes['HF_Email_New_Renewal_Order'] = new HF_Email_New_Renewal_Order();
        $email_classes['HF_Email_Processing_Renewal_Order'] = new HF_Email_Processing_Renewal_Order();
        $email_classes['HF_Email_Completed_Renewal_Order'] = new HF_Email_Completed_Renewal_Order();
        $email_classes['HF_Email_Renewal_Due_Invoice'] = new HF_Email_Renewal_Due_Invoice();
        $email_classes['HF_Email_Cancelled_Subscription'] = new HF_Email_Cancelled_Subscription();
        $email_classes['HF_Email_Expired_Subscription'] = new HF_Email_Expired_Subscription();
        $email_classes['HF_Email_On_Hold_Subscription'] = new HF_Email_On_Hold_Subscription();

        return $email_classes;
    }

    public function hook_transactional_emails() {

        add_action('hf_subscription_status_updated', array($this, 'send_cancelled_email'), 10, 2);
        add_action('hf_subscription_status_expired',  array($this, 'send_expired_email'), 10, 2);
        add_action('hf_customer_changed_subscription_to_on-hold',  array($this, 'send_on_hold_email'), 10, 2);

        $order_email_actions = array(
            'woocommerce_order_status_pending_to_processing',
            'woocommerce_order_status_pending_to_completed',
            'woocommerce_order_status_pending_to_on-hold',
            'woocommerce_order_status_failed_to_processing',
            'woocommerce_order_status_failed_to_completed',
            'woocommerce_order_status_failed_to_on-hold',
            'woocommerce_order_status_completed',
            'woocommerce_generated_manual_renewal_order',
            'woocommerce_order_status_failed',
        );

        foreach ($order_email_actions as $action) {
            add_action($action,  array($this, 'maybe_remove_woocommerce_email'), 9);
            add_action($action, array($this, 'send_renewal_order_email'), 10);
            add_action($action,  array($this, 'maybe_reattach_woocommerce_email'), 11);
        }
    }

    public function send_cancelled_email($subscription) {
        
        WC()->mailer();
        if ($subscription->has_status(array('pending-cancel', 'cancelled')) && 'true' !== get_post_meta($subscription->get_id(), '_cancelled_email_sent', true)) {
            do_action('cancelled_subscription_notification', $subscription);
        }
    }

    public function send_expired_email($subscription) {
        
        WC()->mailer();
        do_action('expired_subscription_notification', $subscription);
    }

    public function send_on_hold_email($subscription) {
        
        WC()->mailer();
        do_action('on-hold_subscription_notification', $subscription);
    }

    public function send_renewal_order_email($order_id) {
        
        WC()->mailer();
        if (hf_order_contains_renewal($order_id)) {
            do_action(current_filter() . '_renewal_notification', $order_id);
        }
    }

    public function maybe_remove_woocommerce_email($order_id) {
        
        if (hf_order_contains_renewal($order_id) || hf_order_contains_switch($order_id)) {
            self::detach_woocommerce_transactional_email();
        }
    }

    public function maybe_reattach_woocommerce_email($order_id) {
        
        if (hf_order_contains_renewal($order_id) || hf_order_contains_switch($order_id)) {
            self::attach_woocommerce_transactional_email();
        }
    }

    public function renewal_order_emails_available($available_emails) {
        
        global $theorder;

        if (hf_order_contains_renewal(hf_get_objects_property($theorder, 'id'))) {
            $available_emails = array(
                'new_renewal_order',
                'customer_processing_renewal_order',
                'customer_completed_renewal_order',
            );

            if ($theorder->needs_payment()) {
                array_push($available_emails, 'renewal_due_invoice');
            }
        }
        return $available_emails;
    }

    public static function email_order_items_table($order, $args = array()) {
        
        $items_table = '';

        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (is_a($order, 'WC_Abstract_Order')) {

            if (HF_Subscriptions::is_woocommerce_prior_to('2.5')) {

                $items_table = call_user_func_array(array($order, 'email_order_items_table'), $args);
            } else {

                $show_download_links_callback = ( isset($args['show_download_links']) && $args['show_download_links'] ) ? '__return_true' : '__return_false';
                $show_purchase_note_callback = ( isset($args['show_purchase_note']) && $args['show_purchase_note'] ) ? '__return_true' : '__return_false';

                unset($args['show_download_links']);
                unset($args['show_purchase_note']);

                add_filter('woocommerce_order_is_download_permitted', $show_download_links_callback);
                add_filter('woocommerce_order_is_paid', $show_purchase_note_callback);

                if (function_exists('wc_get_email_order_items')) {
                    $items_table = wc_get_email_order_items($order, $args);
                } else {
                    $items_table = $order->email_order_items_table($args);
                }

                remove_filter('woocommerce_order_is_download_permitted', $show_download_links_callback);
                remove_filter('woocommerce_order_is_paid', $show_purchase_note_callback);
            }
        }

        return $items_table;
    }

    public function order_details($order, $sent_to_admin = false, $plain_text = false, $email = '') {

        $order_items_table_args = array(
            'show_download_links' => ( $sent_to_admin ) ? false : $order->is_download_permitted(),
            'show_sku' => $sent_to_admin,
            'show_purchase_note' => ( $sent_to_admin ) ? false : $order->has_status(apply_filters('woocommerce_order_is_paid_statuses', array('processing', 'completed'))),
            'show_image' => '',
            'image_size' => '',
            'plain_text' => $plain_text,
        );

        $template_path = ( $plain_text ) ? 'emails/plain/email-order-details.php' : 'emails/email-order-details.php';
        $order_type = ( hf_is_subscription($order) ) ? 'subscription' : 'order';

        wc_get_template(
                $template_path, array(
            'order' => $order,
            'sent_to_admin' => $sent_to_admin,
            'plain_text' => $plain_text,
            'email' => $email,
            'order_type' => $order_type,
            'order_items_table_args' => $order_items_table_args,
                ), '', plugin_dir_path(HF_Subscriptions::PLUGN_BASE_PATH) . 'templates/'
        );
    }

    public static function detach_woocommerce_transactional_email($hook = '', $priority = 10) {

        if ('' === $hook) {
            $hook = current_filter();
        }

        remove_action($hook, array('WC_Emails', 'queue_transactional_email'), $priority);
        remove_action($hook, array('WC_Emails', 'send_transactional_email'), $priority);
    }

    public static function attach_woocommerce_transactional_email($hook = '', $priority = 10, $accepted_args = 10) {

        if ('' === $hook) {
            $hook = current_filter();
        }

        if (false === HF_Subscriptions::is_woocommerce_prior_to('3.0') && apply_filters('woocommerce_defer_transactional_emails', true)) {
            add_action($hook, array('WC_Emails', 'queue_transactional_email'), $priority, $accepted_args);
        } else {
            add_action($hook, array('WC_Emails', 'send_transactional_email'), $priority, $accepted_args);
        }
    }

}

new HF_Subscriptions_Email();