<?php
if (!defined('ABSPATH')) {
    exit;
}

class HF_Add_Remove_Item {

    public function __construct() {

        add_action('init', array($this, 'maybe_remove_or_add_item_to_subscription'), 100);
    }

    public static function get_remove_url($subscription_id, $order_item_id) {

        $remove_link = add_query_arg(array('subscription_id' => $subscription_id, 'remove_item' => $order_item_id));
        $remove_link = wp_nonce_url($remove_link, $subscription_id);

        return $remove_link;
    }

    public static function get_undo_remove_url($subscription_id, $order_item_id, $base_url) {

        $undo_remove_link = add_query_arg(array('subscription_id' => $subscription_id, 'undo_remove_item' => $order_item_id), $base_url);
        $undo_remove_link = wp_nonce_url($undo_remove_link, $subscription_id);

        return $undo_remove_link;
    }

    public function maybe_remove_or_add_item_to_subscription() {

        if (isset($_GET['subscription_id']) && ( isset($_GET['remove_item']) || isset($_GET['undo_remove_item']) ) && isset($_GET['_wpnonce'])) {

            $subscription = ( hf_is_subscription($_GET['subscription_id']) ) ? hf_get_subscription($_GET['subscription_id']) : false;
            $undo_request = ( isset($_GET['undo_remove_item']) ) ? true : false;
            $item_id = ( $undo_request ) ? $_GET['undo_remove_item'] : $_GET['remove_item'];

            if (false === $subscription) {

                wc_add_notice(sprintf(__('Subscription #%d does not exist.', HF_Subscriptions::TEXT_DOMAIN), $_GET['subscription_id']), 'error');
                wp_safe_redirect(wc_get_page_permalink('myaccount'));
                exit;
            }

            if (self::validate_remove_items_request($subscription, $item_id, $undo_request)) {

                if ($undo_request) {
                    $removed_item = WC()->session->get('removed_subscription_items', array());

                    if (!empty($removed_item[$item_id]) && $subscription->get_id() == $removed_item[$item_id]) {

                        wc_update_order_item($item_id, array('order_item_type' => 'line_item'));
                        unset($removed_item[$item_id]);

                        WC()->session->set('removed_subscription_items', $removed_item);

                        $subscription = hf_get_subscription($subscription->get_id());
                        $line_items = $subscription->get_items();
                        $line_item = $line_items[$item_id];
                        $_product = $subscription->get_product_from_item($line_item);
                        $product_id = hf_get_canonical_product_id($line_item);

                        if ($_product && $_product->exists() && $_product->is_downloadable()) {

                            $downloads = hf_get_objects_property($_product, 'downloads');

                            foreach (array_keys($downloads) as $download_id) {
                                wc_downloadable_file_permission($download_id, $product_id, $subscription, $line_item['qty']);
                            }
                        }

                        $subscription->add_order_note(sprintf(__('Customer added "%1$s" (Product ID: #%2$d) via the My Account page.', HF_Subscriptions::TEXT_DOMAIN), hf_get_line_item_name($line_item), $product_id));
                    } else {
                        wc_add_notice(__('Your request to undo your previous action was unsuccessful.', HF_Subscriptions::TEXT_DOMAIN));
                    }
                } else {

                    WC()->session->set('removed_subscription_items', array($item_id => $subscription->get_id()));
                    $line_items = $subscription->get_items();
                    $line_item = $line_items[$item_id];
                    $product_id = hf_get_canonical_product_id($line_item);

                    HF_Download_Handler::revoke_downloadable_file_permission($product_id, $subscription->get_id(), $subscription->get_user_id());

                    wc_update_order_item($item_id, array('order_item_type' => 'line_item_removed'));
                    $subscription->add_order_note(sprintf(__('Customer removed "%1$s" (Product ID: #%2$d) via the My Account page.', HF_Subscriptions::TEXT_DOMAIN), hf_get_line_item_name($line_item), $product_id));
                    wc_add_notice(sprintf(__('You have successfully removed "%1$s" from your subscription. %2$sUndo?%3$s', HF_Subscriptions::TEXT_DOMAIN), $line_item['name'], '<a href="' . esc_url(self::get_undo_remove_url($subscription->get_id(), $item_id, $subscription->get_view_order_url())) . '" >', '</a>'));
                }
            }

            $subscription = hf_get_subscription($subscription->get_id());
            $subscription->calculate_totals();
            wp_safe_redirect($subscription->get_view_order_url());
            exit;
        }
    }

    private static function validate_remove_items_request($subscription, $order_item_id, $undo_request = false) {

        $subscription_items = $subscription->get_items();
        $response = false;

        if (!wp_verify_nonce($_GET['_wpnonce'], $_GET['subscription_id'])) {
            wc_add_notice(__('Security error. Please contact us if you need assistance.', HF_Subscriptions::TEXT_DOMAIN), 'error');
        } elseif (!current_user_can('edit_hf_shop_subscription_line_items', $subscription->get_id())) {
            wc_add_notice(__('You cannot modify a subscription that does not belong to you.', HF_Subscriptions::TEXT_DOMAIN), 'error');
        } elseif (!$undo_request && !isset($subscription_items[$order_item_id])) {
            wc_add_notice(__('You cannot remove an item that does not exist. ', HF_Subscriptions::TEXT_DOMAIN), 'error');
        } elseif (!$subscription->payment_method_supports('subscription_amount_changes')) {
            wc_add_notice(__('The item was not removed because this Subscription\'s payment method does not support removing an item.', HF_Subscriptions::TEXT_DOMAIN));
        } else {
            $response = true;
        }
        return $response;
    }

}

new HF_Add_Remove_Item();