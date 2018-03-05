<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Subscriptions_Order {

    public function __construct() {


        add_action('manage_shop_order_posts_custom_column', array( $this, 'order_contains_subscription_hidden_field_in_order_list'), 10, 1); // throw alert while deleting
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'order_contains_subscription_hidden_field_in_order_edit'), 10, 1); // throw alert while deleting
        add_filter('manage_edit-shop_order_columns', array($this, 'order_contains_subscription_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'order_contains_subscription_column_content'), 10, 1);
        add_action('woocommerce_order_status_changed', array($this, 'maybe_record_subscription_payment'), 9, 3);
        add_filter('woocommerce_order_needs_payment', array($this, 'order_needs_payment'), 10, 3);
        add_action('woocommerce_email_after_order_table', array($this, 'add_subscription_info_to_email'), 15, 3);
        add_action('restrict_manage_posts', array($this, 'restrict_manage_subscriptions'), 50);
        add_filter('request', array($this, 'filter_orders_by_type_query'), 11);
        add_action('woocommerce_order_partially_refunded', array($this, 'maybe_cancel_subscription_on_partial_refund'));
        add_action('woocommerce_order_fully_refunded', array($this, '::maybe_cancel_subscription_on_full_refund'));
        add_filter('woocommerce_order_needs_shipping_address', array($this, 'maybe_display_shipping_address'), 10, 3);
        add_filter('woocommerce_payment_complete_order_status', __CLASS__ . '::maybe_autocomplete_order', 10, 2);
    }



    public static function get_non_subscription_total($order) {

        if (!is_object($order)) {
            $order = new WC_Order($order);
        }
        $non_subscription_total = 0;
        foreach ($order->get_items() as $order_item) {
            if (!self::is_item_subscription($order, $order_item)) {
                $non_subscription_total += $order_item['line_total'];
            }
        }

        return apply_filters('hf_subscription_order_non_subscription_total', $non_subscription_total, $order);
    }

    public static function get_items_product_id($order_item) {
        return ( isset($order_item['product_id']) ) ? $order_item['product_id'] : $order_item['id'];
    }

    public static function get_item_by_product_id($order, $product_id = '') {

        if (!is_object($order)) {
            $order = new WC_Order($order);
        }

        foreach ($order->get_items() as $item) {
            if (( self::get_items_product_id($item) == $product_id || empty($product_id) ) && self::is_item_subscription($order, $item)) {
                return $item;
            }
        }

        return array();
    }

    public static function get_item_by_subscription_key($subscription_key) {

        $item_id = self::get_item_id_by_subscription_key($subscription_key);
        $item = self::get_item_by_id($item_id);
        return $item;
    }

    public static function get_item_id_by_subscription_key($subscription_key) {
        global $wpdb;
        $order_and_product_ids = explode('_', $subscription_key);
        $item_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT `{$wpdb->prefix}woocommerce_order_items`.order_item_id FROM `{$wpdb->prefix}woocommerce_order_items`
				INNER JOIN `{$wpdb->prefix}woocommerce_order_itemmeta` on `{$wpdb->prefix}woocommerce_order_items`.order_item_id = `{$wpdb->prefix}woocommerce_order_itemmeta`.order_item_id
				AND `{$wpdb->prefix}woocommerce_order_itemmeta`.meta_key = '_product_id'
				AND `{$wpdb->prefix}woocommerce_order_itemmeta`.meta_value = %d
			WHERE `{$wpdb->prefix}woocommerce_order_items`.order_id = %d", $order_and_product_ids[1], $order_and_product_ids[0]
                ));

        return $item_id;
    }

    public static function get_item_by_id($order_item_id) {
        global $wpdb;

        $item = $wpdb->get_row($wpdb->prepare("
			SELECT order_item_id, order_item_name, order_item_type, order_id
			FROM   {$wpdb->prefix}woocommerce_order_items
			WHERE  order_item_id = %d
		", $order_item_id), ARRAY_A);

        $order = new WC_Order(absint($item['order_id']));

        $item['name'] = $item['order_item_name'];
        $item['type'] = $item['order_item_type'];
        $item['item_meta'] = wc_get_order_item_meta($item['order_item_id'], '');

        if (is_array($item['item_meta'])) {
            foreach ($item['item_meta'] as $meta_name => $meta_value) {
                $key = substr($meta_name, 0, 1) == '_' ? substr($meta_name, 1) : $meta_name;
                $item[$key] = maybe_unserialize($meta_value[0]);
            }
        }

        return $item;
    }

    public static function get_item_meta($order, $meta_key, $product_id = '', $default = 0) {

        $meta_value = $default;

        if ('' == $product_id) {
            $items = self::get_recurring_items($order);
            foreach ($items as $item) {
                $product_id = $item['product_id'];
                break;
            }
        }

        $item = self::get_item_by_product_id($order, $product_id);
        if (!empty($item) && isset($item['item_meta'][$meta_key])) {
            $meta_value = $item['item_meta'][$meta_key][0];
        }

        return apply_filters('hf_subscription_item_meta', $meta_value, $meta_key, $order, $product_id);
    }

    public static function get_item_meta_data($meta_id) {
        global $wpdb;

        $item_meta = $wpdb->get_row($wpdb->prepare("
			SELECT *
			FROM   {$wpdb->prefix}woocommerce_order_itemmeta
			WHERE  meta_id = %d
		", $meta_id));

        return $item_meta;
    }

    public static function get_item_name($order, $product_id = '') {

        $item = self::get_item_by_product_id($order, $product_id);
        if (isset($item['name'])) {
            return $item['name'];
        } else {
            return '';
        }
    }

    public static function get_meta($order, $meta_key, $default = 0) {

        if (!is_object($order)) {
            $order = new WC_Order($order);
        }

        $meta_key = preg_replace('/^_/', '', $meta_key);

        if (isset($order->$meta_key)) {
            $meta_value = $order->$meta_key;
        } elseif (is_array($order->order_custom_fields) && isset($order->order_custom_fields['_' . $meta_key][0]) && $order->order_custom_fields['_' . $meta_key][0]) {  // < WC 2.1+
            $meta_value = maybe_unserialize($order->order_custom_fields['_' . $meta_key][0]);
        } else {
            $meta_value = get_post_meta(hf_get_objects_property($order, 'id'), '_' . $meta_key, true);

            if (empty($meta_value)) {
                $meta_value = $default;
            }
        }

        return $meta_value;
    }

    public function order_contains_subscription_hidden_field_in_order_list($column) {

        global $post;
        if ('order_status' == $column) {
            $contains_subscription = hf_order_contains_subscription($post->ID, 'parent') ? 'true' : 'false';
            printf('<span class="contains_subscription" data-contains_subscription="%s" style="display: none;"></span>', esc_attr($contains_subscription));
        }
    }

    public function order_contains_subscription_hidden_field_in_order_edit($order_id) {
        $has_subscription = hf_order_contains_subscription($order_id, 'parent') ? 'true' : 'false';
        echo '<input type="hidden" name="contains_subscription" value="' . esc_attr($has_subscription) . '">';
    }

    public  function order_contains_subscription_column($columns) {

        $column_header = '<span class="subscription_head tips" data-tip="' . esc_attr__('Subscription Relationship', HF_Subscriptions::TEXT_DOMAIN) . '">' . esc_attr__('Subscription Relationship', HF_Subscriptions::TEXT_DOMAIN) . '</span>';
        $new_columns = hf_array_insert_after('shipping_address', $columns, 'subscription_relationship', $column_header);
        return $new_columns;
    }

    public  function order_contains_subscription_column_content($column) {
        global $post;

        if ('subscription_relationship' == $column) {
            if (hf_order_contains_subscription($post->ID, 'renewal')) {
                echo '<span class="subscription_renewal_order tips" data-tip="' . esc_attr__('Renewal Order', HF_Subscriptions::TEXT_DOMAIN) . '"></span>';
            } elseif (hf_order_contains_subscription($post->ID, 'resubscribe')) {
                echo '<span class="subscription_resubscribe_order tips" data-tip="' . esc_attr__('Resubscribe Order', HF_Subscriptions::TEXT_DOMAIN) . '"></span>';
            } elseif (hf_order_contains_subscription($post->ID, 'parent')) {
                echo '<span class="subscription_parent_order tips" data-tip="' . esc_attr__('Parent Order', HF_Subscriptions::TEXT_DOMAIN) . '"></span>';
            } else {
                echo '<span class="normal_order">&ndash;</span>';
            }
        }
    }

    public function maybe_record_subscription_payment($order_id, $old_order_status, $new_order_status) {

        if (hf_order_contains_subscription($order_id, 'parent')) {

            $subscriptions = hf_get_subscriptions_for_order($order_id, array('order_type' => 'parent'));
            $was_activated = false;
            $order = wc_get_order($order_id);
            $order_completed = in_array($new_order_status, array(apply_filters('woocommerce_payment_complete_order_status', 'processing', $order_id), 'processing', 'completed')) && in_array($old_order_status, apply_filters('woocommerce_valid_order_statuses_for_payment', array('pending', 'on-hold', 'failed'), $order));

            foreach ($subscriptions as $subscription) {

                if ($order_completed && !$subscription->has_status(hf_get_subscription_ended_statuses()) && !$subscription->has_status('active')) {

                    $new_start_date_offset = current_time('timestamp', true) - $subscription->get_time('date_created');

                    if ($new_start_date_offset > HOUR_IN_SECONDS) {

                        $dates = array('date_created' => current_time('mysql', true));

                        
                            foreach (array('next_payment', 'end') as $date_type) {
                                if (0 != $subscription->get_time($date_type)) {
                                    $dates[$date_type] = gmdate('Y-m-d H:i:s', $subscription->get_time($date_type) + $new_start_date_offset);
                                }
                            }
                        

                        $subscription->update_dates($dates);
                    }

                    $subscription->payment_complete();
                    $was_activated = true;
                } elseif ('failed' == $new_order_status) {
                    $subscription->payment_failed();
                }
            }

            if ($was_activated) {
                do_action('subscriptions_activated_for_order', $order_id);
            }
        }
    }

    public static function is_item_subscription($order, $order_item) {

        if (!is_array($order_item)) {
            $order_item = self::get_item_by_product_id($order, $order_item);
        }

        $order_items_product_id = hf_get_canonical_product_id($order_item);
        $item_is_subscription = false;

        foreach (hf_get_subscriptions_for_order($order, array('order_type' => 'parent')) as $subscription) {
            foreach ($subscription->get_items() as $line_item) {
                if (hf_get_canonical_product_id($line_item) == $order_items_product_id) {
                    $item_is_subscription = true;
                    break 2;
                }
            }
        }

        return $item_is_subscription;
    }

    public static function get_users_subscription_orders($user_id = 0) {
        global $wpdb;

        if (0 === $user_id) {
            $user_id = get_current_user_id();
        }

        $order_ids = get_posts(array(
            'posts_per_page' => 1,
            'post_type' => 'shop_order',
            'post_status' => 'any',
            'fields' => 'ids',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => '_customer_user',
                    'compare' => '=',
                    'value' => $user_id,
                    'type' => 'numeric',
                ),
                array(
                    'key' => '_subscription_renewal',
                    'compare' => 'NOT EXISTS',
                ),
            ),
                ));

        foreach ($order_ids as $index => $order_id) {
            if (!hf_order_contains_subscription($order_id, 'parent')) {
                unset($order_ids[$index]);
            }
        }

        $order_ids = array_values($order_ids);

        return apply_filters('users_subscription_orders', $order_ids, $user_id);
    }

    public function order_needs_payment($needs_payment, $order, $valid_order_statuses) {

        if (false === $needs_payment && 0 == $order->get_total() && in_array($order->get_status(), $valid_order_statuses) && hf_order_contains_subscription($order) && self::get_recurring_total($order) > 0 ) {
            $needs_payment = true;
        }
        return $needs_payment;
    }

    public function add_subscription_info_to_email($order, $is_admin_email, $plaintext = false) {

        $subscriptions = hf_get_subscriptions_for_order($order, array('order_type' => 'any'));

        if (!empty($subscriptions)) {

            $template_base = plugin_dir_path(HF_Subscriptions::PLUGN_BASE_PATH) . 'templates/';
            $template = ( $plaintext ) ? 'emails/plain/subscription-info.php' : 'emails/subscription-info.php';

            wc_get_template(
                    $template, array(
                'order' => $order,
                'subscriptions' => $subscriptions,
                'is_admin_email' => $is_admin_email,
                    ), '', $template_base
            );
        }
    }

    public function restrict_manage_subscriptions() {
        global $typenow;

        if ('shop_order' != $typenow) {
            return;
        }
        ?>
        <select name='shop_order_subtype' id='dropdown_shop_order_subtype'>
            <option value=""><?php esc_html_e('All orders types', HF_Subscriptions::TEXT_DOMAIN); ?></option>
        <?php
        $order_types = apply_filters('hf_subscription_order_type_dropdown', array(
                'original' => __('Original', HF_Subscriptions::TEXT_DOMAIN),
                'parent' => __('Subscription Parent', HF_Subscriptions::TEXT_DOMAIN),
                'renewal' => __('Subscription Renewal', HF_Subscriptions::TEXT_DOMAIN),
                'resubscribe' => __('Subscription Resubscribe', HF_Subscriptions::TEXT_DOMAIN),
                'switch' => __('Subscription Switch', HF_Subscriptions::TEXT_DOMAIN),
                'regular' => __('Non-subscription', HF_Subscriptions::TEXT_DOMAIN),
            ));

        foreach ($order_types as $order_type_key => $order_type_description) {
            echo '<option value="' . esc_attr($order_type_key) . '"';

            if (isset($_GET['shop_order_subtype']) && $_GET['shop_order_subtype']) {
                selected($order_type_key, $_GET['shop_order_subtype']);
            }

            echo '>' . esc_html($order_type_description) . '</option>';
        }
        ?>
        </select>
            <?php
        }

        public static function filter_orders_by_type_query($vars) {
            
            global $typenow, $wpdb;

            if ('shop_order' == $typenow && !empty($_GET['shop_order_subtype'])) {

                if ('original' == $_GET['shop_order_subtype'] || 'regular' == $_GET['shop_order_subtype']) {

                    $vars['meta_query']['relation'] = 'AND';
                    $vars['meta_query'][] = array(
                        'key' => '_subscription_renewal',
                        'compare' => 'NOT EXISTS',
                    );

                    $vars['meta_query'][] = array(
                        'key' => '_subscription_switch',
                        'compare' => 'NOT EXISTS',
                    );
                } elseif ('parent' == $_GET['shop_order_subtype']) {

                    $vars['post__in'] = hf_get_subscription_orders();
                } else {

                    switch ($_GET['shop_order_subtype']) {
                        case 'renewal' :
                            $meta_key = '_subscription_renewal';
                            break;
                        case 'resubscribe' :
                            $meta_key = '_subscription_resubscribe';
                            break;
                        case 'switch' :
                            $meta_key = '_subscription_switch';
                            break;
                    }

                    $vars['meta_query'][] = array(
                        'key' => $meta_key,
                        'compare' => 'EXISTS',
                    );
                }

                if ('regular' == $_GET['shop_order_subtype']) {
                    $vars['post__not_in'] = hf_get_subscription_orders();
                }
            }

            return $vars;
        }

        public static function is_order_editable($is_editable, $order) {
            //_deprecated_function(__METHOD__, '2.0', 'HF_Subscription::is_editable()');
            return $is_editable;
        }

        private static function get_matching_subscription($order, $product_id = '') {

            $subscriptions = hf_get_subscriptions_for_order($order, array('order_type' => 'parent'));
            $matching_subscription = null;

            if (!empty($product_id)) {
                foreach ($subscriptions as $subscription) {
                    foreach ($subscription->get_items() as $line_item) {
                        if (hf_get_canonical_product_id($line_item) == $product_id) {
                            $matching_subscription = $subscription;
                            break 2;
                        }
                    }
                }
            }
            if (null === $matching_subscription && !empty($subscriptions)) {
                $matching_subscription = array_pop($subscriptions);
            }
            return $matching_subscription;
        }

        private static function get_matching_subscription_item($order, $product_id = '') {

            $matching_item = array();
            $subscription = self::get_matching_subscription($order, $product_id);

            foreach ($subscription->get_items() as $line_item) {
                if (hf_get_canonical_product_id($line_item) == $product_id) {
                    $matching_item = $line_item;
                    break;
                }
            }
            return $matching_item;
        }
        
        public static function maybe_cancel_subscription_on_full_refund($order) {

            if (!is_object($order)) {
                $order = new WC_Order($order);
            }

            if (hf_order_contains_subscription($order, array('parent', 'renewal'))) {
                $subscriptions = hf_get_subscriptions_for_order(hf_get_objects_property($order, 'id'), array('order_type' => array('parent', 'renewal')));
                foreach ($subscriptions as $subscription) {
                    $latest_order = $subscription->get_last_order();

                    if (hf_get_objects_property($order, 'id') == $latest_order && $subscription->has_status('pending-cancel') && $subscription->can_be_updated_to('cancelled')) {
                        $subscription->update_status('cancelled', wp_kses(sprintf(__('Subscription cancelled for refunded order %1$s#%2$s%3$s.', HF_Subscriptions::TEXT_DOMAIN), sprintf('<a href="%s">', esc_url(hf_get_edit_post_link(hf_get_objects_property($order, 'id')))), $order->get_order_number(), '</a>'), array('a' => array('href' => true))));
                    }
                }
            }
        }

        // handle partial refunds on orders in WC versions prior to 2.5 which would be considered as full refunds in > WC 2.5.

        public static function maybe_cancel_subscription_on_partial_refund($order_id) {

            if (HF_Subscriptions::is_woocommerce_prior_to('2.5') && hf_order_contains_subscription($order_id, array('parent', 'renewal'))) {

                $order = wc_get_order($order_id);
                $remaining_order_total = wc_format_decimal($order->get_total() - $order->get_total_refunded());
                $remaining_order_items = absint($order->get_item_count() - $order->get_item_count_refunded());
                $order_has_free_item = false;

                foreach ($order->get_items() as $item) {
                    if (!$item['line_total']) {
                        $order_has_free_item = true;
                        break;
                    }
                }
                if (!( $remaining_order_total > 0 || ( $order_has_free_item && $remaining_order_items > 0 ) )) {
                    self::maybe_cancel_subscription_on_full_refund($order);
                }
            }
        }

        public function maybe_display_shipping_address($needs_shipping, $hidden_shipping_methods, $order) {
            $order_shipping_methods = $order->get_shipping_methods();
            if (!$needs_shipping && hf_order_contains_subscription($order) && empty($order_shipping_methods)) {
                $subscriptions = hf_get_subscriptions_for_order($order);
                foreach ($subscriptions as $subscription) {
                    foreach ($subscription->get_shipping_methods() as $shipping_method) {
                        if (!in_array($shipping_method['method_id'], $hidden_shipping_methods)) {
                            $needs_shipping = true;
                            break 2;
                        }
                    }
                }
            }
            return $needs_shipping;
        }

        public static function maybe_autocomplete_order($new_order_status, $order_id) {

            remove_filter('woocommerce_payment_complete_order_status', __METHOD__, 10);
            $order = wc_get_order($order_id);
            add_filter('woocommerce_payment_complete_order_status', __METHOD__, 10, 2);

            if ('processing' == $new_order_status && $order->get_total() == 0 && hf_order_contains_subscription($order)) {

                if (hf_order_contains_resubscribe($order)) {
                    $new_order_status = 'completed';
                } elseif (hf_order_contains_switch($order)) {
                    $all_switched = true;

                    foreach ($order->get_items() as $item) {
                        if (!isset($item['switched_subscription_price_prorated'])) {
                            $all_switched = false;
                            break;
                        }
                    }

                    if ($all_switched || 1 == count($order->get_items())) {
                        $new_order_status = 'completed';
                    }
                } else {
                    $subscriptions = hf_get_subscriptions_for_order($order_id);
                    $all_synced = false;

                    if ($all_synced) {
                        $new_order_status = 'completed';
                    }
                }
            }

            return $new_order_status;
        }

        public static function get_recurring_total($order) {
            $recurring_total = 0;

            foreach (hf_get_subscriptions_for_order($order, array('order_type' => 'parent')) as $subscription) {

                if (empty($product_id)) {
                    $recurring_total += $subscription->get_total();
                } else {
                    foreach ($subscription->get_items() as $line_item) {
                        if (hf_get_canonical_product_id($line_item) == $product_id) {
                            $recurring_total += $subscription->get_total();
                            break;
                        }
                    }
                }
            }
            return $recurring_total;
        }

        public static function get_recurring_items($order) {

            if (!is_object($order)) {
                $order = new WC_Order($order);
            }

            $items = array();

            foreach ($order->get_items() as $item_id => $item_details) {

                if (!self::is_item_subscription($order, $item_details)) {
                    continue;
                }

                $items[$item_id] = $item_details;
                $order_items_product_id = hf_get_canonical_product_id($item_details);
                $matching_subscription = self::get_matching_subscription($order, $order_items_product_id);

                if (null !== $matching_subscription) {
                    foreach ($matching_subscription->get_items() as $line_item) {
                        if (hf_get_canonical_product_id($line_item) == $order_items_product_id) {
                            $items[$item_id]['line_subtotal'] = $line_item['line_subtotal'];
                            $items[$item_id]['line_subtotal_tax'] = $line_item['line_subtotal_tax'];
                            $items[$item_id]['line_total'] = $line_item['line_total'];
                            $items[$item_id]['line_tax'] = $line_item['line_tax'];
                            break;
                        }
                    }
                }
            }

            return $items;
        }

    }

    new HF_Subscriptions_Order();