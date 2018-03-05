<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Cart_Resubscribe extends HF_Cart_Renewal {

    public $cart_item_key = 'subscription_resubscribe';

    public function __construct() {

        $this->init_hooks();

        add_action('woocommerce_checkout_subscription_created', array(&$this, 'maybe_record_resubscribe'), 10, 3);
        add_filter('hf_subscription_recurring_cart_key', array(&$this, 'get_recurring_cart_key'), 10, 2);
        add_filter('hf_recurring_cart_next_payment_date', array(&$this, 'recurring_cart_next_payment_date'), 100, 2);
        add_filter('woocommerce_before_calculate_totals', array(&$this, 'maybe_set_free_trial'), 100, 1);
        add_action('hf_subscription_cart_before_grouping', array(&$this, 'maybe_unset_free_trial'));
        add_action('hf_subscription_cart_after_grouping', array(&$this, 'maybe_set_free_trial'));
        add_action('hf_recurring_cart_start_date', array(&$this, 'maybe_unset_free_trial'), 0, 1);
        add_action('hf_recurring_cart_end_date', array(&$this, 'maybe_set_free_trial'), 100, 1);
        add_filter('hf_subscription_calculated_total', array(&$this, 'maybe_unset_free_trial'), 10000, 1);
        add_action('woocommerce_cart_totals_before_shipping', array(&$this, 'maybe_set_free_trial'));
        add_action('woocommerce_cart_totals_after_shipping', array(&$this, 'maybe_unset_free_trial'));
        add_action('woocommerce_review_order_before_shipping', array(&$this, 'maybe_set_free_trial'));
        add_action('woocommerce_review_order_after_shipping', array(&$this, 'maybe_unset_free_trial'));
        add_action('woocommerce_order_status_changed', array(&$this, 'maybe_cancel_existing_subscription'), 10, 3);
       
    }

    public function maybe_setup_cart() {
        global $wp;

        if (isset($_GET['resubscribe']) && isset($_GET['_wpnonce'])) {

            $subscription = hf_get_subscription($_GET['resubscribe']);
            $redirect_to = get_permalink(wc_get_page_id('myaccount'));

            if (wp_verify_nonce($_GET['_wpnonce'], $subscription->get_id()) === false) {

                wc_add_notice(__('There was an error with your request to resubscribe. Please try again.', HF_Subscriptions::TEXT_DOMAIN), 'error');
            } elseif (empty($subscription)) {

                wc_add_notice(__('That subscription does not exist. Has it been deleted?', HF_Subscriptions::TEXT_DOMAIN), 'error');
            } elseif (!current_user_can('subscribe_again', $subscription->get_id())) {

                wc_add_notice(__('That doesn\'t appear to be one of your subscriptions.', HF_Subscriptions::TEXT_DOMAIN), 'error');
            } elseif (!hf_can_user_resubscribe_to($subscription)) {

                wc_add_notice(__('You can not resubscribe to that subscription. Please contact us if you need assistance.', HF_Subscriptions::TEXT_DOMAIN), 'error');
            } else {

                $this->setup_cart($subscription, array(
                    'subscription_id' => $subscription->get_id(),
                ));

                if (WC()->cart->get_cart_contents_count() != 0) {
                    wc_add_notice(__('Complete checkout to resubscribe.', HF_Subscriptions::TEXT_DOMAIN), 'success');
                }

                $redirect_to = wc_get_checkout_url();
            }

            wp_safe_redirect($redirect_to);
            exit;
        } elseif (isset($_GET['pay_for_order']) && isset($_GET['key']) && isset($wp->query_vars['order-pay'])) {

            $order_id = ( isset($wp->query_vars['order-pay']) ) ? $wp->query_vars['order-pay'] : absint($_GET['order_id']);
            $order = wc_get_order($wp->query_vars['order-pay']);
            $order_key = $_GET['key'];

            if (hf_get_objects_property($order, 'order_key') == $order_key && $order->has_status(array('pending', 'failed')) && hf_order_contains_resubscribe($order)) {

                if (!is_user_logged_in()) {

                    $redirect = add_query_arg(array(
                        'hf_redirect' => 'pay_for_order',
                        'hf_redirect_id' => $order_id,
                            ), get_permalink(wc_get_page_id('myaccount')));

                    wp_safe_redirect($redirect);
                    exit;
                }

                wc_add_notice(__('Complete checkout to resubscribe.', HF_Subscriptions::TEXT_DOMAIN), 'success');

                $subscriptions = hf_get_subscriptions_for_resubscribe_order($order);

                foreach ($subscriptions as $subscription) {
                    if (current_user_can('subscribe_again', $subscription->get_id())) {
                        $this->setup_cart($subscription, array(
                            'subscription_id' => $subscription->get_id(),
                        ));
                    } else {
                        wc_add_notice(__('That doesn\'t appear to be one of your subscriptions.', HF_Subscriptions::TEXT_DOMAIN), 'error');
                        wp_safe_redirect(get_permalink(wc_get_page_id('myaccount')));
                        exit;
                    }
                }

                $redirect_to = wc_get_checkout_url();
                wp_safe_redirect($redirect_to);
                exit;
            }
        }
    }

    public function maybe_record_resubscribe($new_subscription, $order, $recurring_cart) {

        $cart_item = $this->cart_contains($recurring_cart);
        if (false !== $cart_item) {
            hf_set_objects_property($order, 'subscription_resubscribe', $cart_item[$this->cart_item_key]['subscription_id']);
            $new_subscription->update_meta_data('_subscription_resubscribe', $cart_item[$this->cart_item_key]['subscription_id']);
            $new_subscription->save();
        }
    }

    public function get_cart_item_from_session($cart_item_session_data, $cart_item, $key) {
        if (isset($cart_item[$this->cart_item_key]['subscription_id'])) {

            $cart_item_session_data = parent::get_cart_item_from_session($cart_item_session_data, $cart_item, $key);

            $subscription = hf_get_subscription($cart_item[$this->cart_item_key]['subscription_id']);
            if ($subscription) {
                $_product = $cart_item_session_data['data'];
                hf_set_objects_property($_product, 'subscription_period', $subscription->get_billing_period(), 'set_prop_only');
                hf_set_objects_property($_product, 'subscription_period_interval', $subscription->get_billing_interval(), 'set_prop_only');
                hf_set_objects_property($_product, 'subscription_trial_length', 0, 'set_prop_only');
            }
        }

        return $cart_item_session_data;
    }

    protected function cart_contains($cart = '') {
        return hf_cart_contains_resubscribe($cart);
    }

    protected function get_order($cart_item = '') {
        $subscription = false;
        if (empty($cart_item)) {
            $cart_item = $this->cart_contains();
        }
        if (false !== $cart_item && isset($cart_item[$this->cart_item_key])) {
            $subscription = hf_get_subscription($cart_item[$this->cart_item_key]['subscription_id']);
        }
        return $subscription;
    }

    public function get_recurring_cart_key($cart_key, $cart_item) {
        $subscription = $this->get_order($cart_item);
        if (false !== $subscription && $subscription->has_status('pending-cancel')) {
            remove_filter('hf_subscription_recurring_cart_key', array(&$this, 'get_recurring_cart_key'), 10, 2);
            $cart_key = HF_Subscription_Cart::get_recurring_cart_key($cart_item, $subscription->get_time('end'));
            add_filter('hf_subscription_recurring_cart_key', array(&$this, 'get_recurring_cart_key'), 10, 2);
        }
        return $cart_key;
    }

    public function recurring_cart_next_payment_date($first_renewal_date, $cart) {
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            $subscription = $this->get_order($cart_item);
            if (false !== $subscription && $subscription->has_status('pending-cancel')) {
                $first_renewal_date = ( '1' != HF_Subscriptions_Product::get_length($cart_item['data']) ) ? $subscription->get_date('end') : 0;
                break;
            }
        }
        return $first_renewal_date;
    }

    public function maybe_set_free_trial($total = '') {

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $subscription = $this->get_order($cart_item);
            if (false !== $subscription && $subscription->has_status('pending-cancel')) {
                hf_set_objects_property(WC()->cart->cart_contents[$cart_item_key]['data'], 'subscription_trial_length', 1, 'set_prop_only');
                break;
            }
        }
        return $total;
    }

    public function maybe_unset_free_trial($total = '') {

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $subscription = $this->get_order($cart_item);
            if (false !== $subscription && $subscription->has_status('pending-cancel')) {
                hf_set_objects_property(WC()->cart->cart_contents[$cart_item_key]['data'], 'subscription_trial_length', 0, 'set_prop_only');
                break;
            }
        }

        return $total;
    }

    public function maybe_cancel_existing_subscription($order_id, $old_order_status, $new_order_status) {
        if (hf_order_contains_subscription($order_id) && hf_order_contains_resubscribe($order_id)) {
            $order = wc_get_order($order_id);
            $order_completed = in_array($new_order_status, array(apply_filters('woocommerce_payment_complete_order_status', 'processing', $order_id), 'processing', 'completed'));
            $order_needed_payment = in_array($old_order_status, apply_filters('woocommerce_valid_order_statuses_for_payment', array('pending', 'on-hold', 'failed'), $order));

            foreach (hf_get_subscriptions_for_resubscribe_order($order_id) as $subscription) {
                if ($subscription->has_status('pending-cancel')) {
                    $cancel_note = sprintf(__('Customer resubscribed in order #%s', HF_Subscriptions::TEXT_DOMAIN), $order->get_order_number());
                    $subscription->update_status('cancelled', $cancel_note);
                }
            }
        }
    }

}

new HF_Cart_Resubscribe();