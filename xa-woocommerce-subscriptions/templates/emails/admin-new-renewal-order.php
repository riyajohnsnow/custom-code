<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_header', $email_heading, $email ); 
?>
<p><?php
	printf( esc_html_x( 'You have received a subscription renewal order from %1$s. Their order is as follows:', 'Used in admin email: new renewal order', HF_Subscriptions::TEXT_DOMAIN ), esc_html( $order->get_formatted_billing_full_name() ) );
    ?>
</p>
<?php
do_action( 'hf_subscription_email_order_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_order_meta', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_customer_details', $order, $sent_to_admin, $plain_text, $email );
do_action( 'woocommerce_email_footer', $email );
?>