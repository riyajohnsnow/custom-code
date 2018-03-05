<?php
if (!defined('ABSPATH')) {
    exit;
}

echo $email_heading . "\n\n";

printf(__('A subscription belonging to %1$s has been cancelled. Their subscription\'s details are as follows:', HF_Subscriptions::TEXT_DOMAIN), $subscription->get_formatted_billing_full_name());

echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

do_action('hf_subscription_email_order_details', $subscription, $sent_to_admin, $plain_text, $email);

echo "\n----------\n\n";

$last_order_time_created = $subscription->get_time('last_order_date_created', 'site');

if (!empty($last_order_time_created)) {
    echo sprintf(__('Last Order Date: %s', HF_Subscriptions::TEXT_DOMAIN), date_i18n(wc_date_format(), $last_order_time_created)) . "\n";
}

$end_time = $subscription->get_time('end', 'site');

if (!empty($end_time)) {
    echo sprintf(__('End of Prepaid Term: %s', HF_Subscriptions::TEXT_DOMAIN), date_i18n(wc_date_format(), $end_time)) . "\n";
}

do_action('woocommerce_email_order_meta', $subscription, $sent_to_admin, $plain_text, $email);

echo "\n" . sprintf(__('View Subscription: %s', HF_Subscriptions::TEXT_DOMAIN), hf_get_edit_post_link($subscription->get_id())) . "\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

do_action('woocommerce_email_customer_details', $subscription, $sent_to_admin, $plain_text, $email);

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters('woocommerce_email_footer_text', get_option('woocommerce_email_footer_text'));