<?php
if (!defined('ABSPATH')) {
    exit;
}

// customer completed renewal order email

do_action('woocommerce_email_header', $email_heading, $email);
?>
<p>
    <?php
    printf(esc_html__('Hi there. Your subscription renewal order with %s has been completed. Your order details are shown below for your reference:', HF_Subscriptions::TEXT_DOMAIN), esc_html(get_option('blogname')));
    ?>
</p>

<?php
do_action('hf_subscription_email_order_details', $order, $sent_to_admin, $plain_text, $email);
do_action('woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email);
do_action('woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email);
do_action('woocommerce_email_footer', $email);
?>