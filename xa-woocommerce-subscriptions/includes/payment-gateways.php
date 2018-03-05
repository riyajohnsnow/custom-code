<?php
if (!defined('ABSPATH')) {
    exit;
}

// payment gateways

class HF_Subscription_Payment_Gateways {

    protected static $one_gateway_supports = array();

    public function __construct() {

        //add_action('init', array($this, 'init_stripe'), 5);
        add_filter('woocommerce_available_payment_gateways', array($this, 'get_available_payment_gateways'));
        add_filter('woocommerce_no_available_payment_methods_message', array($this, 'no_available_payment_methods_message'));
        add_action('woocommerce_scheduled_subscription_payment', __CLASS__ . '::gateway_scheduled_subscription_payment', 10, 1);
        add_action('hf_subscription_status_updated', array($this, 'trigger_gateway_status_updated_hook'), 10, 2);
    }

    public function init_stripe() {

        require_once( 'eh-stripe-payment-gateway/stripe-payment-gateway.php' );
        eh_stripe_check();
    }

    public static function get_payment_gateway($gateway_id) {

        $found_gateway = false;
        if (WC()->payment_gateways) {
            foreach (WC()->payment_gateways->payment_gateways() as $gateway) {
                if ($gateway_id == $gateway->id) {
                    $found_gateway = $gateway;
                }
            }
        }
        return $found_gateway;
    }

    public function get_available_payment_gateways($available_gateways) {

        if (HF_Subscription_Cart::cart_contains_subscription() || ( isset($_GET['order_id']) && hf_order_contains_subscription($_GET['order_id']) )) {

            $accept_manual_renewals = true;
            $subscriptions_in_cart = count(WC()->cart->recurring_carts);

            foreach ($available_gateways as $gateway_id => $gateway) {

                $supports_subscriptions = $gateway->supports('subscriptions');

                if ($subscriptions_in_cart > 1 && $gateway->supports('multiple_subscriptions') !== true && ( $supports_subscriptions || !$accept_manual_renewals )) {
                    unset($available_gateways[$gateway_id]);
                } elseif (!$supports_subscriptions && !$accept_manual_renewals) {
                    unset($available_gateways[$gateway_id]);
                }
            }
        }

        return $available_gateways;
    }

    public static function one_gateway_supports($supports_flag) {

        if (!isset(self::$one_gateway_supports[$supports_flag])) {

            self::$one_gateway_supports[$supports_flag] = false;

            foreach (WC()->payment_gateways->get_available_payment_gateways() as $gateway) {
                if ($gateway->supports($supports_flag)) {
                    self::$one_gateway_supports[$supports_flag] = true;
                    break;
                }
            }
        }

        return self::$one_gateway_supports[$supports_flag];
    }

    public function no_available_payment_methods_message($no_gateways_message) {

        $accept_manual_renewals = 'yes' ;
        if (HF_Subscription_Cart::cart_contains_subscription() && 'no' == $accept_manual_renewals) {
            $no_gateways_message = __('Sorry, it seems there are no available payment methods which support subscriptions. Please contact us if you require assistance or wish to make alternate arrangements.', HF_Subscriptions::TEXT_DOMAIN);
        }

        return $no_gateways_message;
    }

    public function trigger_gateway_status_updated_hook($subscription, $new_status) {

        if ($subscription->is_manual()) {
            return;
        }

        switch ($new_status) {
            case 'active' :
                $hook_prefix = 'hf_subscription_activated_';
                break;
            case 'on-hold' :
                $hook_prefix = 'hf_subscription_on-hold_';
                break;
            case 'pending-cancel' :
                $hook_prefix = 'hf_subscription_pending-cancel_';
                break;
            case 'cancelled' :
                $hook_prefix = 'hf_subscription_cancelled_';
                break;
            case 'expired' :
                $hook_prefix = 'hf_subscription_expired_';
                break;
            default :
                $hook_prefix = apply_filters('hf_subscription_gateway_status_updated_hook_prefix', 'hf_subscription_status_updated_', $subscription, $new_status);
                break;
        }

        do_action($hook_prefix . $subscription->get_payment_method(), $subscription);
    }

    public static function trigger_gateway_renewal_payment_hook($renewal_order) {

        $renewal_order_payment_method = hf_get_objects_property($renewal_order, 'payment_method');

        if (!empty($renewal_order) && $renewal_order->get_total() > 0 && !empty($renewal_order_payment_method)) {

            WC()->payment_gateways();

            do_action('woocommerce_scheduled_subscription_payment_' . $renewal_order_payment_method, $renewal_order->get_total(), $renewal_order);
        }
    }

    public static function gateway_scheduled_subscription_payment($subscription_id, $deprecated = null) {

        if (null != $deprecated) {
            $subscription = hf_get_subscription_from_key($deprecated);
        } elseif (!is_object($subscription_id)) {
            $subscription = hf_get_subscription($subscription_id);
        } else {
            $subscription = $subscription_id;
        }

        if (false === $subscription) {
            throw new InvalidArgumentException(sprintf(__('Subscription doesn\'t exist in scheduled action: %d', HF_Subscriptions::TEXT_DOMAIN), $subscription_id));
        }

        if (!$subscription->is_manual()) {
            self::trigger_gateway_renewal_payment_hook($subscription->get_last_order('all', 'renewal'));
        }
    }

}

new HF_Subscription_Payment_Gateways();