<?php
if (!defined('ABSPATH')) {
    exit;
}
// customer processing renewal order email
 
do_action('woocommerce_email_header', $email_heading, $email); 
?>

<p>
    <?php esc_html_e('Your subscription renewal order has been received and is now being processed. Your order details are shown below for your reference:', HF_Subscriptions::TEXT_DOMAIN); ?>
</p>

<?php
    do_action('hf_subscription_email_order_details', $order, $sent_to_admin, $plain_text, $email);
    do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);
    do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
    do_action('woocommerce_email_footer', $email);
?>