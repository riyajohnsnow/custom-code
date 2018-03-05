<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscription_Addresses {

    public function __construct() {

        add_filter('hf_view_subscription_actions', array($this, 'add_subscription_shipping_address_edit_action'), 10, 2);
        add_action('woocommerce_after_edit_address_form_billing', array($this, 'maybe_add_edit_address_checkbox'), 10);
        add_action('woocommerce_after_edit_address_form_shipping', array($this, 'maybe_add_edit_address_checkbox'), 10);
        add_action('woocommerce_customer_save_address', array($this, 'maybe_update_subscription_addresses'), 10, 2);
        add_filter('woocommerce_address_to_edit', array($this, 'maybe_populate_subscription_addresses'), 10);
    }

    public function add_subscription_shipping_address_edit_action($actions, $subscription) {

        if ($subscription->needs_shipping_address() && $subscription->has_status(array('active', 'on-hold'))) {
            $actions['change_address'] = array(
                'url' => add_query_arg(array('subscription' => $subscription->get_id()), wc_get_endpoint_url('edit-address', 'shipping')),
                'name' => __('Change Address', HF_Subscriptions::TEXT_DOMAIN),
            );
        }
        return $actions;
    }

    public function maybe_add_edit_address_checkbox() {
        
        global $wp;
        if (hf_user_has_subscription()) {
            if (isset($_GET['subscription'])) {
                echo '<p>' . esc_html__('Both the shipping address used for the subscription and your default shipping address for future purchases will be updated.', HF_Subscriptions::TEXT_DOMAIN) . '</p>';
                echo '<input type="hidden" name="update_subscription_address" value="' . absint($_GET['subscription']) . '" id="update_subscription_address" />';
            } elseif (( ( isset($wp->query_vars['edit-address']) && !empty($wp->query_vars['edit-address']) ) || isset($_GET['address']))) {

                if (isset($wp->query_vars['edit-address'])) {
                    $address_type = esc_attr($wp->query_vars['edit-address']) . ' ';
                } else {
                    $address_type = (!isset($_GET['address']) ) ? esc_attr($_GET['address']) . ' ' : '';
                }

                $label = sprintf(__('Update the %1$s used for all of my active subscriptions', HF_Subscriptions::TEXT_DOMAIN), hf_get_address_type_to_display($address_type));
                woocommerce_form_field('update_all_subscriptions_addresses', array(
                            'type' => 'checkbox',
                            'class' => array('form-row-wide'),
                            'label' => $label,
                        )
                );
            }
            wp_nonce_field('hf_edit_address', '_hfnonce');
        }
    }

    public function maybe_update_subscription_addresses($user_id, $address_type) {

        if (!hf_user_has_subscription($user_id) || wc_notice_count('error') > 0 || empty($_POST['_hfnonce']) || !wp_verify_nonce($_POST['_hfnonce'], 'hf_edit_address')) {
            return;
        }
        $address_type = ( 'billing' == $address_type || 'shipping' == $address_type ) ? $address_type : '';
        $address_fields = WC()->countries->get_address_fields(esc_attr($_POST[$address_type . '_country']), $address_type . '_');
        $address = array();
        foreach ($address_fields as $key => $field) {
            if (isset($_POST[$key])) {
                $address[str_replace($address_type . '_', '', $key)] = wc_clean($_POST[$key]);
            }
        }
        if (isset($_POST['update_all_subscriptions_addresses'])) {
            $users_subscriptions = hf_get_users_subscriptions($user_id);
            foreach ($users_subscriptions as $subscription) {
                if ($subscription->has_status(array('active', 'on-hold'))) {
                    $subscription->set_address($address, $address_type);
                }
            }
        } elseif (isset($_POST['update_subscription_address'])) {
            $subscription = hf_get_subscription(intval($_POST['update_subscription_address']));
            if (!empty($subscription)) {
                $subscription->set_address($address, $address_type);
            }
            wp_safe_redirect($subscription->get_view_order_url());
            exit();
        }
    }

    public function maybe_populate_subscription_addresses($address) {

        if (isset($_GET['subscription'])) {
            $subscription = hf_get_subscription(absint($_GET['subscription']));
            foreach (array_keys($address) as $key) {
                $function_name = 'get_' . $key;
                if (is_callable(array($subscription, $function_name))) {
                    $address[$key]['value'] = $subscription->$function_name();
                }
            }
        }
        return $address;
    }

}

new HF_Subscription_Addresses();