<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscription_Manager {

    public static $users_meta_key = 'hf_subscription';

    public function __construct() {

        add_action('woocommerce_order_status_cancelled', array($this, 'cancel_subscriptions_for_order'));
        add_action('woocommerce_order_status_failed', array($this, 'failed_subscription_sign_ups_for_order'));
        add_action('woocommerce_order_status_on-hold', array($this, 'put_subscription_on_hold_for_order'));
        add_action('woocommerce_scheduled_subscription_expiration',  array($this, 'expire_subscription'), 10, 1);
        add_action('woocommerce_scheduled_subscription_end_of_prepaid_term',  array($this, 'subscription_end_of_prepaid_term'), 10, 1);
        add_action('woocommerce_scheduled_subscription_payment', array($this, 'maybe_process_failed_renewal_for_repair'), 0, 1);
        add_action('woocommerce_scheduled_subscription_payment', array($this, 'prepare_renewal_process'), 1, 1);
        add_action('wp_trash_post',  array($this, 'maybe_trash_subscription'), 10);
        add_action('delete_user', __CLASS__. '::trash_users_subscriptions');
        add_action('wpmu_delete_user', array($this, 'trash_users_subscriptions_for_network'));
        add_action('wp_trash_post', __CLASS__. '::maybe_cancel_subscription', 10, 1);
        add_action('before_delete_post', __CLASS__. '::maybe_cancel_subscription', 10, 1);
        add_action('trashed_post', array($this,'fix_trash_meta_status'));
        add_action('trashed_post', array($this,'trigger_subscription_trashed_hook'));
        add_action('deleted_post',  array($this,'trigger_subscription_deleted_hook'));
    }

    
    public function prepare_renewal_process($subscription_id) {

        $subscription = hf_get_subscription($subscription_id);
        if (empty($subscription) || !$subscription->has_status('active')) {
            return false;
        }

        if (0 == $subscription->get_total() || $subscription->is_manual() || '' == $subscription->get_payment_method() || !$subscription->payment_method_supports('gateway_scheduled_payments')) {

            $subscription->update_status('on-hold', __('Subscription renewal payment due:', HF_Subscriptions::TEXT_DOMAIN));
            $renewal_order = hf_create_renewal_order($subscription);

            if (is_wp_error($renewal_order)) {
                $renewal_order = hf_create_renewal_order($subscription);

                if (is_wp_error($renewal_order)) {
                    throw new Exception(__('Error: Unable to create renewal order from scheduled payment. Please try again.', HF_Subscriptions::TEXT_DOMAIN));
                }
            }

            if (0 == $renewal_order->get_total()) {

                $renewal_order->payment_complete();
                $subscription->update_status('active');
            } else {

                if ($subscription->is_manual()) {
                    do_action('woocommerce_generated_manual_renewal_order', hf_get_objects_property($renewal_order, 'id'));
                } else {
                    $renewal_order->set_payment_method(wc_get_payment_gateway_by_order($subscription));

                    if (is_callable(array($renewal_order, 'save'))) {
                        $renewal_order->save();
                    }
                }
            }
        }
    }

    public function expire_subscription($subscription_id, $deprecated = null) {

        if (null !== $deprecated) {
            $subscription = hf_get_subscription_from_key($deprecated);
        } else {
            $subscription = hf_get_subscription($subscription_id);
        }

        if (false === $subscription) {
            throw new InvalidArgumentException(sprintf(__('Subscription doesn\'t exist in scheduled action: %d', HF_Subscriptions::TEXT_DOMAIN), $subscription_id));
        }

        $subscription->update_status('expired');
    }

    public function subscription_end_of_prepaid_term($subscription_id, $deprecated = null) {

        if (null !== $deprecated) {
            $subscription = hf_get_subscription_from_key($deprecated);
        } else {
            $subscription = hf_get_subscription($subscription_id);
        }

        $subscription->update_status('cancelled');
    }

    public static function process_subscription_payment($user_id, $subscription_key) {

        $subscription = hf_get_subscription_from_key($subscription_key);
        $subscription->payment_complete();
        $subscription = array();
        $subscription['failed_payments'] = $subscription['suspension_count'] = 0;
        self::update_users_subscriptions($user_id, array($subscription_key => $subscription));
    }

    public static function process_subscription_payment_failure($user_id, $subscription_key) {
        

        $subscription = hf_get_subscription_from_key($subscription_key);

        if (apply_filters('hf_subscription_max_failed_payments_exceeded', false, $user_id, $subscription_key)) {
            $new_status = 'cancelled';
        } else {
            $new_status = 'on-hold';
        }

        $subscription->payment_failed($new_status);

        $subscription = array();
        $subscription['failed_payments'] = $subscription['failed_payments'] + 1;
        self::update_users_subscriptions($user_id, array($subscription_key => $subscription));
    }

    public static function process_subscription_payments_on_order($order, $product_id = '') {

        $subscriptions = hf_get_subscriptions_for_order($order);
        if (!empty($subscriptions)) {

            foreach ($subscriptions as $subscription) {
                $subscription->payment_complete();
            }

            do_action('processed_subscription_payments_for_order', $order);
        }
    }

    public static function process_subscription_payment_failure_on_order($order, $product_id = '') {

        $subscriptions = hf_get_subscriptions_for_order($order);
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                $subscription->payment_failed();
            }
            do_action('processed_subscription_payment_failure_for_order', $order);
        }
    }

    public static function activate_subscriptions_for_order($order) {

        $subscriptions = hf_get_subscriptions_for_order($order);
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                try {
                    $subscription->update_status('active');
                } catch (Exception $e) {
                    $subscription->add_order_note(sprintf(__('Failed to activate subscription status for order #%1$s: %2$s', HF_Subscriptions::TEXT_DOMAIN), is_object($order) ? $order->get_order_number() : $order, $e->getMessage()));
                }
            }
            do_action('subscriptions_activated_for_order', $order);
        }
    }

    public function put_subscription_on_hold_for_order($order) {

        $subscriptions = hf_get_subscriptions_for_order($order, array('order_type' => 'parent'));
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {

                try {
                    if (!$subscription->has_status(hf_get_subscription_ended_statuses())) {
                        $subscription->update_status('on-hold');
                    }
                } catch (Exception $e) {
                    $subscription->add_order_note(sprintf(__('Failed to update subscription status after order #%1$s was put on-hold: %2$s', HF_Subscriptions::TEXT_DOMAIN), is_object($order) ? $order->get_order_number() : $order, $e->getMessage()));
                }
            }
            do_action('subscriptions_put_on_hold_for_order', $order);
        }
    }

    public function cancel_subscriptions_for_order($order) {

        $subscriptions = hf_get_subscriptions_for_order($order, array('order_type' => 'parent'));
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                try {
                    if (!$subscription->has_status(hf_get_subscription_ended_statuses())) {
                        $subscription->cancel_order();
                    }
                } catch (Exception $e) {
                    $subscription->add_order_note(sprintf(__('Failed to cancel subscription after order #%1$s was cancelled: %2$s', HF_Subscriptions::TEXT_DOMAIN), is_object($order) ? $order->get_order_number() : $order, $e->getMessage()));
                }
            }

            do_action('subscriptions_cancelled_for_order', $order);
        }
    }

    public static function expire_subscriptions_for_order($order) {

        $subscriptions = hf_get_subscriptions_for_order($order);
        if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {
                try {
                    if (!$subscription->has_status(hf_get_subscription_ended_statuses())) {
                        $subscription->update_status('expired');
                    }
                } catch (Exception $e) {
                    $subscription->add_order_note(sprintf(__('Failed to set subscription as expired for order #%1$s: %2$s', HF_Subscriptions::TEXT_DOMAIN), is_object($order) ? $order->get_order_number() : $order, $e->getMessage()));
                }
            }
            do_action('subscriptions_expired_for_order', $order);
        }
    }

    public function failed_subscription_sign_ups_for_order($order) {

        $subscriptions = hf_get_subscriptions_for_order($order, array('order_type' => 'parent'));

        if (!empty($subscriptions)) {

            if (!is_object($order)) {
                $order = new WC_Order($order);
            }

            if ($order->has_status('failed')) {
                $order->update_status('failed', __('Subscription sign up failed.', HF_Subscriptions::TEXT_DOMAIN));
            }

            foreach ($subscriptions as $subscription) {

                try {
                    $subscription->payment_failed();
                } catch (Exception $e) {
                    $subscription->add_order_note(sprintf(__('Failed to process failed payment on subscription for order #%1$s: %2$s', HF_Subscriptions::TEXT_DOMAIN), is_object($order) ? $order->get_order_number() : $order, $e->getMessage()));
                }
            }

            do_action('failed_subscription_sign_ups_for_order', $order);
        }
    }

    public static function create_pending_subscription_for_order($order, $product_id, $args = array()) {
        _deprecated_function(__METHOD__, '2.0', 'hf_create_subscription()');

        if (!is_object($order)) {
            $order = new WC_Order($order);
        }

        if (!HF_Subscriptions_Product::is_subscription($product_id)) {
            return;
        }

        $args = wp_parse_args($args, array(
            'start_date' => hf_get_datetime_utc_string(hf_get_objects_property($order, 'date_created')), // get_date_created() can return null, but if it does, we have an error anyway
            'expiry_date' => '',
                ));

        $billing_period = HF_Subscriptions_Product::get_period($product_id);
        $billing_interval = HF_Subscriptions_Product::get_interval($product_id);

        $args['start_date'] = is_numeric($args['start_date']) ? gmdate('Y-m-d H:i:s', $args['start_date']) : $args['start_date'];

        $product = wc_get_product($product_id);

        $subscriptions = hf_get_subscriptions(array('order_id' => hf_get_objects_property($order, 'id'), 'product_id' => $product_id));

        if (!empty($subscriptions)) {

            $subscription = array_pop($subscriptions);

            wp_update_post(array(
                'ID' => $subscription->get_id(),
                'post_status' => 'wc-' . apply_filters('woocommerce_default_subscription_status', 'pending'),
                'post_date' => get_date_from_gmt($args['start_date']),
            ));
        } else {

            $subscription = hf_create_subscription(array(
                'start_date' => get_date_from_gmt($args['start_date']),
                'order_id' => hf_get_objects_property($order, 'id'),
                'customer_id' => $order->get_user_id(),
                'billing_period' => $billing_period,
                'billing_interval' => $billing_interval,
                'customer_note' => hf_get_objects_property($order, 'customer_note'),
                    ));

            if (is_wp_error($subscription)) {
                throw new Exception(__('Error: Unable to create subscription. Please try again.', HF_Subscriptions::TEXT_DOMAIN));
            }

            $item_id = $subscription->add_product(
                    $product, 1, array(
                'variation' => ( method_exists($product, 'get_variation_attributes') ) ? $product->get_variation_attributes() : array(),
                'totals' => array(
                    'subtotal' => $product->get_price(),
                    'subtotal_tax' => 0,
                    'total' => $product->get_price(),
                    'tax' => 0,
                    'tax_data' => array('subtotal' => array(), 'total' => array()),
                ),
                    )
            );

            if (!$item_id) {
                throw new Exception(__('Error: Unable to add product to created subscription. Please try again.', HF_Subscriptions::TEXT_DOMAIN));
            }
        }

        if (hf_get_objects_property($order, 'prices_include_tax')) {
            $prices_include_tax = 'yes';
        } else {
            $prices_include_tax = 'no';
        }
        update_post_meta($subscription->get_id(), '_order_currency', hf_get_objects_property($order, 'currency'));
        update_post_meta($subscription->get_id(), '_prices_include_tax', $prices_include_tax);

        if (!empty($args['expiry_date'])) {
            if (is_numeric($args['expiry_date'])) {
                $args['expiry_date'] = gmdate('Y-m-d H:i:s', $args['expiry_date']);
            }

            $expiration = $args['expiry_date'];
        } else {
            $expiration = HF_Subscriptions_Product::get_expiration_date($product_id, $args['start_date']);
        }


        $dates_to_update = array();


        if ($expiration > 0) {
            $dates_to_update['end'] = $expiration;
        }

        if (!empty($dates_to_update)) {
            $subscription->update_dates($dates_to_update);
        }

        $subscription->set_total(0, 'tax');
        $subscription->set_total($product->get_price(), 'total');

        $subscription->add_order_note(__('Pending subscription created.', HF_Subscriptions::TEXT_DOMAIN));

        do_action('pending_subscription_created_for_order', $order, $product_id);
    }

    public static function process_subscriptions_on_checkout($order) {
        
        if (!empty($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'woocommerce-process_checkout')) {
            HF_Subscription_Checkout::process_checkout($order, $_POST);
        }
    }

    public static function update_users_subscriptions_for_order($order, $status = 'pending') {

        if (!is_object($order)) {
            $order = new WC_Order($order);
        }

        if ('suspend' === $status) {
            $status = 'on-hold';
            
        }

        foreach (hf_get_subscriptions_for_order(hf_get_objects_property($order, 'id'), array('order_type' => 'parent')) as $subscription_id => $subscription) {

            switch ($status) {
                case 'cancelled' :
                    $subscription->cancel_order();
                    break;
                case 'active' :
                case 'expired' :
                case 'on-hold' :
                    $subscription->update_status($status);
                    break;
                case 'failed' :
                    self::failed_subscription_signup($order->get_user_id(), $subscription_id);
                    break;
                case 'pending' :
                default :
                    self::create_pending_subscription_for_order($order);
                    break;
            }
        }
        do_action('updated_users_subscriptions_for_order', $order, $status);
    }

    public static function update_users_subscriptions($user_id, $subscriptions) {

        foreach ($subscriptions as $subscription_key => $new_subscription_details) {
            $subscription = hf_get_subscription_from_key($subscription_key);
            if (isset($new_subscription_details['status']) && 'deleted' == $new_subscription_details['status']) {
                wp_delete_post($subscription->get_id());
            } else {
                self::update_subscription($subscription_key, $new_subscription_details);
            }
        }
        do_action('updated_users_subscriptions', $user_id, $subscriptions);
        return self::get_users_subscriptions($user_id);
    }

    public static function update_subscription($subscription_key, $new_subscription_details) {

        $subscription = hf_get_subscription_from_key($subscription_key);
        if (isset($new_subscription_details['status']) && 'deleted' == $new_subscription_details['status']) {
            wp_delete_post($subscription->get_id());
        } else {

            foreach ($new_subscription_details as $meta_key => $meta_value) {
                switch ($meta_key) {
                    case 'start_date' :
                        $subscription->update_dates(array('date_created' => $meta_value));
                        break;
                    case 'trial_expiry_date' :
                        $subscription->update_dates(array('trial_end' => $meta_value));
                        break;
                    case 'expiry_date' :
                        $subscription->update_dates(array('end' => $meta_value));
                        break;
                    case 'failed_payments' :
                        break;
                    case 'completed_payments' :
                        break;
                    case 'suspension_count' :
                        $subscription->set_suspension_count($subscription->get_suspension_count() + 1);
                        break;
                }
            }
        }

        do_action('updated_users_subscription', $subscription_key, $new_subscription_details);

        return hf_get_subscription_in_deprecated_structure($subscription);
    }

    public static function cancel_users_subscriptions($user_id) {

        $subscriptions = hf_get_users_subscriptions($user_id);

        if (!empty($subscriptions)) {

            foreach ($subscriptions as $subscription) {
                if ($subscription->can_be_updated_to('cancelled')) {
                    $subscription->update_status('cancelled');
                }
            }

            do_action('cancelled_users_subscriptions', $user_id);
        }
    }

    public static function cancel_users_subscriptions_for_network($user_id) {

        $sites = get_blogs_of_user($user_id);
        if (!empty($sites)) {
            foreach ($sites as $site) {
                switch_to_blog($site->userblog_id);
                self::cancel_users_subscriptions($user_id);
                restore_current_blog();
            }
        }
        do_action('cancelled_users_subscriptions_for_network', $user_id);
    }

    public static function clear_users_subscriptions_from_order($order) {

        foreach (hf_get_subscriptions_for_order($order, array('order_type' => 'parent')) as $subscription_id => $subscription) {
            wp_delete_post($subscription->get_id());
        }
        do_action('cleared_users_subscriptions_from_order', $order);
    }

    public function maybe_trash_subscription($post_id) {

        if ('shop_order' == get_post_type($post_id)) {
            foreach (hf_get_subscriptions_for_order($post_id, array('order_type' => 'parent')) as $subscription) {
                wp_trash_post($subscription->get_id());
            }
        }
    }

    public static function maybe_cancel_subscription($post_id) {

        if ('hf_shop_subscription' == get_post_type($post_id) && 'auto-draft' !== get_post_status($post_id)) {
            $subscription = hf_get_subscription($post_id);
            if ($subscription->can_be_updated_to('cancelled')) {
                $subscription->update_status('cancelled');
            }
        }
    }

    public function fix_trash_meta_status($post_id) {

        if ('hf_shop_subscription' == get_post_type($post_id) && !in_array(get_post_meta($post_id, '_wp_trash_meta_status', true), array('wc-pending', 'wc-expired', 'wc-cancelled'))) {
            update_post_meta($post_id, '_wp_trash_meta_status', 'wc-cancelled');
        }
    }

    public function trigger_subscription_trashed_hook($post_id) {

        if ('hf_shop_subscription' == get_post_type($post_id)) {
            do_action('hf_subscription_trashed', $post_id);
        }
    }

    public static function trash_users_subscriptions($user_id) {

        $subscriptions = hf_get_users_subscriptions($user_id);

        if (!empty($subscriptions)) {

            foreach ($subscriptions as $subscription) {
                wp_delete_post($subscription->get_id());
            }
        }
    }

    public function trash_users_subscriptions_for_network($user_id) {

        $sites = get_blogs_of_user($user_id);
        if (!empty($sites)) {
            foreach ($sites as $site) {
                switch_to_blog($site->userblog_id);
                self::trash_users_subscriptions($user_id);
                restore_current_blog();
            }
        }
    }

    public function trigger_subscription_deleted_hook($post_id) {

        if ('hf_shop_subscription' == get_post_type($post_id)) {
            do_action('hf_subscription_deleted', $post_id);
        }
    }

    public static function maybe_change_users_subscription() {
        HF_User_Change_Status_Handler::maybe_change_users_subscription();
    }

    public static function can_subscription_be_changed_to($new_status_or_meta, $subscription_key, $user_id = '') {

        if ('suspended' == $new_status_or_meta) {
            $new_status_or_meta = 'on-hold';
        }

        try {
            $subscription = hf_get_subscription_from_key($subscription_key);

            switch ($new_status_or_meta) {
                case 'new-payment-date' :
                    $subscription_can_be_changed = $subscription->can_date_be_updated('next_payment');
                    break;
                case 'active' :
                case 'on-hold' :
                case 'cancelled' :
                case 'expired' :
                case 'trash' :
                case 'deleted' :
                case 'failed' :
                default :
                    $subscription_can_be_changed = $subscription->can_be_updated_to($new_status_or_meta);
                    break;
            }
        } catch (Exception $e) {
            $subscription_can_be_changed = false;
        }

        return $subscription_can_be_changed;
    }

    public static function get_subscription($subscription_key, $deprecated = null) {

        try {
            $subscription = hf_get_subscription_from_key($subscription_key);
            $subscription = hf_get_subscription_in_deprecated_structure($subscription);
        } catch (Exception $e) {
            $subscription = array();
        }

        return apply_filters('hf_get_subscription', $subscription, $subscription_key, $deprecated);
    }

    public static function get_status_to_display($status, $subscription_key = '', $user_id = 0) {

        switch ($status) {
            case 'active' :
                $status_string = __('Active', HF_Subscriptions::TEXT_DOMAIN);
                break;
            case 'cancelled' :
                $status_string = __('Cancelled', HF_Subscriptions::TEXT_DOMAIN);
                break;
            case 'expired' :
                $status_string = __('Expired', HF_Subscriptions::TEXT_DOMAIN);
                break;
            case 'pending' :
                $status_string = __('Pending', HF_Subscriptions::TEXT_DOMAIN);
                break;
            case 'failed' :
                $status_string = __('Failed', HF_Subscriptions::TEXT_DOMAIN);
                break;
            case 'on-hold' :
            case 'suspend' :
                $status_string = __('On-hold', HF_Subscriptions::TEXT_DOMAIN);
                break;
            default :
                $status_string = apply_filters('hf_subscription_custom_status_string', ucfirst($status), $subscription_key, $user_id);
        }

        return apply_filters('hf_subscription_status_string', $status_string, $status, $subscription_key, $user_id);
    }

    public static function get_subscription_key($order_id, $product_id = '') {

        if (hf_order_contains_renewal($order_id)) {
            $order_id = HF_Subscription_Renewal_Order::get_parent_order_id($order_id);
        }

        if (empty($product_id)) {

            $subscriptions = hf_get_subscriptions_for_order($order_id, array('order_type' => 'parent'));

            foreach ($subscriptions as $subscription) {
                $subscription_items = $subscription->get_items();
                if (!empty($subscription_items)) {
                    break;
                }
            }

            if (!empty($subscription_items)) {
                $first_item = reset($subscription_items);
                $product_id = HF_Subscriptions_Order::get_items_product_id($first_item);
            } else {
                $product_id = '';
            }
        }

        $subscription_key = $order_id . '_' . $product_id;

        return apply_filters('hf_subscription_key', $subscription_key, $order_id, $product_id);
    }

    public static function get_users_subscriptions($user_id = 0, $order_ids = array()) {

        $subscriptions_in_old_format = array();

        foreach (hf_get_users_subscriptions($user_id) as $subscription) {
            $subscriptions_in_old_format[hf_get_old_subscription_key($subscription)] = hf_get_subscription_in_deprecated_structure($subscription);
        }

        return apply_filters('hf_users_subscriptions', $subscriptions_in_old_format, $user_id);
    }

    public static function get_users_trashed_subscriptions($user_id = '') {

        $subscriptions = self::get_users_subscriptions($user_id);
        foreach ($subscriptions as $key => $subscription) {
            if ('trash' != $subscription['status']) {
                unset($subscriptions[$key]);
            }
        }
        return apply_filters('hf_users_trashed_subscriptions', $subscriptions, $user_id);
    }

    public static function touch_time($args = array()) {
        global $wp_locale;

        $args = wp_parse_args($args, array(
            'date' => true,
            'tab_index' => 0,
            'multiple' => false,
            'echo' => true,
            'include_time' => true,
            'include_buttons' => true,
                )
        );

        if (empty($args['date'])) {
            return;
        }

        $tab_index_attribute = ( (int) $args['tab_index'] > 0 ) ? ' tabindex="' . $args['tab_index'] . '"' : '';
        $month = mysql2date('n', $args['date'], false);
        $month_input = '<select ' . ( $args['multiple'] ? '' : 'id="edit-month" ' ) . 'name="edit-month"' . $tab_index_attribute . '>';
        for ($i = 1; $i < 13; $i = $i + 1) {
            $month_numeral = zeroise($i, 2);
            $month_input .= '<option value="' . $month_numeral . '"';
            $month_input .= ( $i == $month ) ? ' selected="selected"' : '';
            $month_input .= '>' . sprintf(__('%1$s-%2$s', HF_Subscriptions::TEXT_DOMAIN), $month_numeral, $wp_locale->get_month_abbrev($wp_locale->get_month($i))) . "</option>\n";
        }
        $month_input .= '</select>';

        $day_input = '<input type="text" ' . ( $args['multiple'] ? '' : 'id="edit-day" ' ) . 'name="edit-day" value="' . mysql2date('d', $args['date'], false) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
        $year_input = '<input type="text" ' . ( $args['multiple'] ? '' : 'id="edit-year" ' ) . 'name="edit-year" value="' . mysql2date('Y', $args['date'], false) . '" size="4" maxlength="4"' . $tab_index_attribute . ' autocomplete="off" />';

        if ($args['include_time']) {

            $hour_input = '<input type="text" ' . ( $args['multiple'] ? '' : 'id="edit-hour" ' ) . 'name="edit-hour" value="' . mysql2date('H', $args['date'], false) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';
            $minute_input = '<input type="text" ' . ( $args['multiple'] ? '' : 'id="edit-minute" ' ) . 'name="edit-minute" value="' . mysql2date('i', $args['date'], false) . '" size="2" maxlength="2"' . $tab_index_attribute . ' autocomplete="off" />';

            $touch_time = sprintf(__('%1$s%2$s, %3$s @ %4$s : %5$s', HF_Subscriptions::TEXT_DOMAIN), $month_input, $day_input, $year_input, $hour_input, $minute_input);
        } else {
            $touch_time = sprintf(__('%1$s%2$s, %3$s', HF_Subscriptions::TEXT_DOMAIN), $month_input, $day_input, $year_input);
        }

        if ($args['include_buttons']) {
            $touch_time .= '<p>';
            $touch_time .= '<a href="#edit_timestamp" class="save-timestamp hide-if-no-js button">' . __('Change', HF_Subscriptions::TEXT_DOMAIN) . '</a>';
            $touch_time .= '<a href="#edit_timestamp" class="cancel-timestamp hide-if-no-js">' . __('Cancel', HF_Subscriptions::TEXT_DOMAIN) . '</a>';
            $touch_time .= '</p>';
        }

        $allowed_html = array(
            'select' => array(
                'id' => array(),
                'name' => array(),
                'tabindex' => array(),
            ),
            'option' => array(
                'value' => array(),
                'selected' => array(),
            ),
            'input' => array(
                'type' => array(),
                'id' => array(),
                'name' => array(),
                'value' => array(),
                'size' => array(),
                'tabindex' => array(),
                'maxlength' => array(),
                'autocomplete' => array(),
            ),
            'p' => array(),
            'a' => array(
                'href' => array(),
                'title' => array(),
                'class' => array(),
            ),
        );

        if ($args['echo']) {
            echo wp_kses($touch_time, $allowed_html);
        }

        return $touch_time;
    }

    public function maybe_process_failed_renewal_for_repair($subscription_id) {

        if ('true' == get_post_meta($subscription_id, '_hf_repaired_2_0_2_needs_failed_payment', true)) {

            $subscription = hf_get_subscription($subscription_id);
            $subscription->update_status('on-hold', __('Subscription renewal payment due:', HF_Subscriptions::TEXT_DOMAIN));
            $renewal_order = hf_create_renewal_order($subscription);
            $subscription->payment_failed();
            update_post_meta($subscription_id, '_hf_repaired_2_0_2_needs_failed_payment', 'false');
            remove_action('woocommerce_scheduled_subscription_payment', array($this, 'prepare_renewal_process'));
            remove_action('woocommerce_scheduled_subscription_payment', 'HF_Subscription_Payment_Gateways::gateway_scheduled_subscription_payment', 10, 1);
        }
    }

    public static function failed_subscription_signup($user_id, $subscription_key) {
        try {
            $subscription = hf_get_subscription_from_key($subscription_key);
            if ($subscription->has_status('on-hold')) {
                return false;
            }
        } catch (Exception $e) {
            return false;
        }

        $subscription->update_status('on-hold');
        $subscription->get_parent()->add_order_note(sprintf(__('Failed sign-up for subscription %s.', HF_Subscriptions::TEXT_DOMAIN), $subscription->get_id()));
        do_action('subscription_sign_up_failed', $user_id, $subscription_key);
    }

}

new HF_Subscription_Manager();