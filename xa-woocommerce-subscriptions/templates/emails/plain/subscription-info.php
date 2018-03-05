<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!empty($subscriptions)) {

    echo "\n\n" . __('Subscription Information:', HF_Subscriptions::TEXT_DOMAIN) . "\n\n";
    foreach ($subscriptions as $subscription) {
        echo sprintf(__('Subscription: %s', HF_Subscriptions::TEXT_DOMAIN), $subscription->get_order_number()) . "\n";
        echo sprintf(__('View Subscription: %s', HF_Subscriptions::TEXT_DOMAIN), $is_admin_email ? hf_get_edit_post_link($subscription->get_id()) : $subscription->get_view_order_url() ) . "\n";
        echo sprintf(__('Start Date: %s', HF_Subscriptions::TEXT_DOMAIN), date_i18n(wc_date_format(), $subscription->get_time('date_created', 'site'))) . "\n";

        $end_date = ( 0 < $subscription->get_time('end') ) ? date_i18n(wc_date_format(), $subscription->get_time('end', 'site')) : __('When Cancelled', HF_Subscriptions::TEXT_DOMAIN);
        echo sprintf(__('End Date: %s', HF_Subscriptions::TEXT_DOMAIN), $end_date) . "\n";
        echo sprintf(__('Price: %s', HF_Subscriptions::TEXT_DOMAIN), $subscription->get_formatted_order_total());
        echo "\n\n";
    }
}