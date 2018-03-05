<?php
if (!defined('ABSPATH')) {
    exit;
}


class HF_Cart_Initial_Payment extends HF_Cart_Renewal {

    public $cart_item_key = 'subscription_initial_payment';

    public function __construct() {
        $this->init_hooks();
    }

    public function maybe_setup_cart() {
        global $wp;

        if (isset($_GET['pay_for_order']) && isset($_GET['key']) && isset($wp->query_vars['order-pay'])) {

            $order_key = $_GET['key'];
            $order_id = ( isset($wp->query_vars['order-pay']) ) ? $wp->query_vars['order-pay'] : absint($_GET['order_id']);
            $order = wc_get_order($wp->query_vars['order-pay']);

            if (hf_get_objects_property($order, 'order_key') == $order_key && $order->has_status(array('pending', 'failed')) && hf_order_contains_subscription($order, 'parent') && !hf_order_contains_subscription($order, 'resubscribe')) {

                if (!is_user_logged_in()) {

                    $redirect = add_query_arg(array(
                        'hf_redirect' => 'pay_for_order',
                        'hf_redirect_id' => $order_id,
                            ), get_permalink(wc_get_page_id('myaccount')));
                    wp_safe_redirect($redirect);
                    exit;
                } elseif (!current_user_can('pay_for_order', $order_id)) {

                    wc_add_notice(__('That doesn\'t appear to be your order.', HF_Subscriptions::TEXT_DOMAIN), 'error');
                    wp_safe_redirect(get_permalink(wc_get_page_id('myaccount')));
                    exit;
                } else {
                    $this->setup_cart($order, array(
                        'order_id' => $order_id,
                    ));
                    WC()->session->set('order_awaiting_payment', $order_id);
                    $this->set_cart_hash($order_id);
                    wp_safe_redirect(wc_get_checkout_url());
                    exit;
                }
            }
        }
    }

    protected function cart_contains() {

        $contains_initial_payment = false;

        if (!empty(WC()->cart->cart_contents)) {
            foreach (WC()->cart->cart_contents as $cart_item) {
                if (isset($cart_item[$this->cart_item_key])) {
                    $contains_initial_payment = $cart_item;
                    break;
                }
            }
        }
        return apply_filters('hf_cart_contains_initial_payment', $contains_initial_payment);
    }

    protected function get_order($cart_item = '') {
        $order = false;
        if (empty($cart_item)) {
            $cart_item = $this->cart_contains();
        }
        if (false !== $cart_item && isset($cart_item[$this->cart_item_key])) {
            $order = wc_get_order($cart_item[$this->cart_item_key]['order_id']);
        }
        return $order;
    }

}

new HF_Cart_Initial_Payment();