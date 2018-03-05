<?php
if (!defined('ABSPATH')) {
    exit;
}
// customer renewal invoice email

do_action('woocommerce_email_header', $email_heading, $email);
?>

<?php if ('pending' == $order->get_status()) : ?>
    <p>
        <?php
        echo wp_kses(sprintf(__('An invoice has been created for you to renew your subscription with %1$s. To pay for this invoice please use the following link: %2$s', HF_Subscriptions::TEXT_DOMAIN), esc_html(get_bloginfo('name')), '<a href="' . esc_url($order->get_checkout_payment_url()) . '">' . esc_html__('Pay Now &raquo;', HF_Subscriptions::TEXT_DOMAIN) . '</a>'), array('a' => array('href' => true)));
        ?>
    </p>
<?php elseif ('failed' == $order->get_status()) : ?>
    <p>
        <?php echo wp_kses(sprintf(__('The automatic payment to renew your subscription with %1$s has failed. To reactivate the subscription, please login and pay for the renewal from your account page: %2$s', HF_Subscriptions::TEXT_DOMAIN), esc_html(get_bloginfo('name')), '<a href="' . esc_url($order->get_checkout_payment_url()) . '">' . esc_html__('Pay Now &raquo;', HF_Subscriptions::TEXT_DOMAIN) . '</a>'), array('a' => array('href' => true))); ?>
    </p>
<?php endif; ?>

<?php
do_action('hf_subscription_email_order_details', $order, $sent_to_admin, $plain_text, $email);
do_action('woocommerce_email_footer', $email);
?>