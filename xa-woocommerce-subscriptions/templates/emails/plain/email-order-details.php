<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_email_before_' . $order_type . '_table', $order, $sent_to_admin, $plain_text, $email );

if ( 'order' == $order_type ) {
	echo sprintf( __( 'Order number: %s', HF_Subscriptions::TEXT_DOMAIN ), $order->get_order_number() ) . "\n";
	echo sprintf( __( 'Order date: %s', HF_Subscriptions::TEXT_DOMAIN ), hf_format_datetime( hf_get_objects_property( $order, 'date_created' ) ) ) . "\n";
} else {
	echo sprintf( __( 'Subscription Number: %s', HF_Subscriptions::TEXT_DOMAIN ), $order->get_order_number() ) . "\n";
}
echo "\n" . HF_Subscriptions_Email::email_order_items_table( $order, $order_items_table_args );

echo "----------\n\n";

if ( $totals = $order->get_order_item_totals() ) {
	foreach ( $totals as $total ) {
		echo $total['label'] . "\t " . $total['value'] . "\n";
	}
}

do_action( 'woocommerce_email_after_' . $order_type . '_table', $order, $sent_to_admin, $plain_text, $email );