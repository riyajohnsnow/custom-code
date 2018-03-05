<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

echo $email_heading . "\n\n";

if ( 'pending' == $order->get_status() ) {
	printf( esc_html_x( 'An invoice has been created for you to renew your subscription with %1$s. To pay for this invoice please use the following link: %2$s', 'In customer renewal invoice email', HF_Subscriptions::TEXT_DOMAIN ), esc_html( get_bloginfo( 'name' ) ), esc_attr( $order->get_checkout_payment_url() ) ) . "\n\n";
} elseif ( 'failed' == $order->get_status() ) {
	printf( esc_html_x( 'The automatic payment to renew your subscription with %1$s has failed. To reactivate the subscription, please login and pay for the renewal from your account page: %2$s', 'In customer renewal invoice email', HF_Subscriptions::TEXT_DOMAIN ), esc_html( get_bloginfo( 'name' ) ), esc_attr( $order->get_checkout_payment_url() ) );
}

echo "\n\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n";

do_action( 'hf_subscription_email_order_details', $order, $sent_to_admin, $plain_text, $email );

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );